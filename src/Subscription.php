<?php

namespace Msonowal\Razorpay\Cashier;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    const STATUS_CREATED = 'created';
    const STATUS_AUTHENTICATED = 'authenticated';
    const STATUS_ACTIVE = 'active';
    const STATUS_EXPIRED = 'expired';
    const STATUS_PENDING = 'pending';
    const STATUS_HALTED = 'halted';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_COMPLETED = 'completed';

    const VALID_STATUSES = [
        self::STATUS_AUTHENTICATED, self::STATUS_ACTIVE, self::STATUS_PENDING, self::STATUS_HALTED, self::STATUS_CANCELLED, self::STATUS_COMPLETED,
    ];

    const UNDER_BILLING_STATUSES = [
        self::STATUS_ACTIVE, self::STATUS_PENDING, self::STATUS_HALTED, self::STATUS_CANCELLED, self::STATUS_COMPLETED,
    ];

    /**
     * The attributes that are not mass assignable.
     *
     * @var array
     */
    protected $guarded = [];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = [
        'charge_at',
        'trial_ends_at', 'ends_at',
        'created_at', 'updated_at',
    ];

    /**
     * Indicates if the plan change should be prorated.
     *
     * @var bool
     */
    protected $prorate = true;

    /**
     * The date on which the billing cycle should be anchored.
     *
     * @var string|null
     */
    protected $billingCycleAnchor = null;

    /**
     * Get the user that owns the subscription.
     */
    public function user()
    {
        return $this->owner();
    }

    protected function getRazorpayClient()
    {
        return resolve('razorpay');
    }

    /**
     * Get the model related to the subscription.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function owner()
    {
        $model = getenv('RAZORPAY_MODEL') ?: config('services.razorpay.model', 'App\\User');

        $model = new $model();

        return $this->belongsTo(get_class($model), $model->getForeignKey());
    }

    public function hasValidStatus() : bool
    {
        return in_array($this->status, self::VALID_STATUSES);
    }

    public function isUnderBillingCycle() : bool
    {
        return Carbon::now()->gte($this->trial_ends_at) && in_array($this->status, self::UNDER_BILLING_STATUSES);
    }

    /**
     * Determine if the subscription is active, on trial, or within its grace period.
     *
     * @return bool
     */
    public function valid() : bool
    {
        return $this->active() || $this->onTrial() || $this->onGracePeriod();
    }

    /**
     * Determine if the subscription is active.
     *
     * @return bool
     */
    public function active() : bool
    {
        return is_null($this->ends_at) || $this->onGracePeriod();
    }

    /**
     * Determine if the subscription is no longer active.
     *
     * @return bool
     */
    public function cancelled() : bool
    {
        return !is_null($this->ends_at) || $this->status == self::STATUS_CANCELLED;
    }

    /**
     * Determine if the subscription is authenticated.
     *
     * @return bool
     */
    public function authenticated() : bool
    {
        return $this->status == self::STATUS_AUTHENTICATED;
    }

    /**
     * Determine if the subscription is within its trial period whcih has also passed the authenticated state.
     *
     * @return bool
     */
    public function onTrial() : bool
    {
        if (!is_null($this->trial_ends_at)) {
            return Carbon::now()->lt($this->trial_ends_at) && $this->authenticated();
        } else {
            return false;
        }
    }

    /**
     * Determine if the subscription is within its grace period after completion or cancellation.
     *
     * @return bool
     */
    public function onGracePeriod() : bool
    {
        if (!is_null($endsAt = $this->ends_at)) {
            return Carbon::now()->lt(Carbon::instance($endsAt));
        } else {
            return false;
        }
    }

    /**
     * Increment the quantity of the subscription.
     *
     * @param int $count
     *
     * @return $this
     */
    public function incrementQuantity($count = 1)
    {
        $this->updateQuantity($this->quantity + $count);

        return $this;
    }

    /**
     * Decrement the quantity of the subscription.
     *
     * @param int $count
     *
     * @return $this
     */
    public function decrementQuantity($count = 1)
    {
        $this->updateQuantity(max(1, $this->quantity - $count));

        return $this;
    }

    /**
     * Indicate that the plan change should not be prorated.
     *
     * @return $this
     */
    // public function noProrate()
    // {
    //     $this->prorate = false;

    //     return $this;
    // }

    /**
     * Force the trial to end immediately.
     *
     * This method must be combined with swap, resume, etc.
     *
     * @return $this
     */
    public function skipTrial()
    {
        $this->trial_ends_at = null;

        return $this;
    }

    public function isCancellable()
    {
        return (is_null($this->ends_at)) && ($this->status != self::STATUS_CANCELLED) && ($this->status != self::STATUS_COMPLETED);
    }

    /**
     * Cancel the subscription at the end of the billing period.
     *
     * @return $this
     */
    public function cancel($cancel_at_cycle_end = 1)
    {
        $subscription = $this->asRazorpaySubscription();

        $subscription = $subscription->cancel(['cancel_at_cycle_end' => $cancel_at_cycle_end]);
        //TODO pass as currently not taking

        // If the user was on trial, we will set the grace period to end when the trial
        // would have ended. Otherwise, we'll retrieve the end of the billing period
        // period and make that the end of the grace period for this current user.
        $ends_at = null;
        if ($this->onTrial()) {
            $ends_at = $this->trial_ends_at;
        } elseif ($cancel_at_cycle_end == 0) {
            $ends_at = Carbon::now();
        } else {
            $ends_at = Carbon::createFromTimestamp(
                $subscription->current_end
            );
        }

        $this->markAsCancelled($ends_at);

        return $this;
    }

    /**
     * Cancel the subscription immediately.
     *
     * @return $this
     */
    public function cancelNow()
    {
        $subscription = $this->asRazorpaySubscription();

        $subscription->cancel(0);

        $this->markAsCancelled();

        return $this;
    }

    /**
     * Mark the subscription as cancelled.
     *
     * @return void
     */
    public function markAsCancelled($ended_at = null)
    {
        $this->fill([
            'status'  => self::STATUS_CANCELLED,
            'ends_at' => $ended_at ?? Carbon::now(),
        ])->save();
    }

    /**
     * Mark the subscription as completed.
     *
     * @return void
     */
    public function markAsCompleted($ended_at = null)
    {
        $this->fill([
            'status'  => self::STATUS_COMPLETED,
            'ends_at' => $ended_at ?? Carbon::now(),
        ])->save();
    }

    /**
     * Mark the subscription as Authenticated.
     *
     * @return void
     */
    public function markAsAuthenticated() : bool
    {
        if ($this->status == self::STATUS_CREATED) {
            $this->status = self::STATUS_AUTHENTICATED;
            $this->save();
            return true;
        }
        return false;
    }

    /**
     * Mark the subscription as Charged.
     *
     * @return void
     */
    public function markAsCharged(array $payload)
    {
        $this->fill([
            'status'         => $payload['status'] ?? self::STATUS_ACTIVE,
            'charge_at'      => $payload['charge_at'] ? Carbon::createFromTimestamp($payload['charge_at']) : $this->charge_at,
            'auth_attempts'  => $payload['auth_attempts'] ?? $this->auth_attempts,
            'trial_ends_at'  => $payload['start_at'] ? Carbon::createFromTimestamp($payload['start_at']) : $this->trial_ends_at,
            'paid_count'     => $payload['paid_count'] ?? $this->paid_count,
            'total_count'    => $payload['total_count'] ?? $this->total_count,
        ])->save();
    }

    /**
     * Mark the subscription as Activated.
     *
     * @return void
     */
    public function markAsActivated(array $payload)
    {
        //as there are no difference
        $this->markAsCharged($payload);
    }

    /**
     * Mark the subscription as pending.
     *
     * @return void
     */
    public function markAsPending(array $payload = [])
    {
        //$status =
        $this->fill([
            'status'        => $payload['status'] ?? self::STATUS_PENDING,
            'charge_at'     => $payload['charge_at'] ? Carbon::createFromTimestamp($payload['charge_at']) : $this->charge_at,
            'auth_attempts' => $payload['auth_attempts'] ?? $this->auth_attempts,
        ])->save();
    }

    /**
     * Mark the subscription as halted.
     *
     * @return void
     */
    public function markAsHalted(array $payload)
    {
        $this->fill([
            'status'        => $payload['status'] ?? self::STATUS_HALTED,
            'charge_at'     => $payload['charge_at'] ? Carbon::createFromTimestamp($payload['charge_at']) : $this->charge_at,
            'auth_attempts' => $payload['auth_attempts'] ?? $this->auth_attempts,
        ])->save();
    }

    /**
     * Get the subscription as a Razorpay subscription object.
     *
     * @return \Razorpay\Api\Subscription
     */
    public function asRazorpaySubscription()
    {
        $razorpay = $this->getRazorpayClient();

        return $razorpay->subscription->fetch($this->razorpay_id);
    }
}
