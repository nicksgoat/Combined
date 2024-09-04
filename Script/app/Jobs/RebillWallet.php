<?php

namespace App\Jobs;

use App\Models\Plans;
use App\Models\TaxRates;
use App\Models\Notifications;
use App\Models\Subscriptions;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use App\Http\Controllers\Traits\Functions;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class RebillWallet implements ShouldQueue
{
  use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, Functions;

  /**
   * Execute the job.
   *
   * @return void
   */
  public function handle()
  {
    Cache::lock('rebill', 10)->get(function () {

      $subscriptions = Subscriptions::with(['creator:id,username,free_subscription,custom_fee,balance', 'subscriber:id,username,wallet'])
        ->where('ends_at', '<=', now())
        ->whereRebillWallet('on')
        ->whereCancelled('no')
        ->get();

      if ($subscriptions) {
        foreach ($subscriptions as $subscription) {
          // Get price of Plan
          $plan = Plans::whereName($subscription->stripe_price)->first();

          if ($plan) {
            // Get Taxes
            $taxes = TaxRates::whereIn('id', collect(explode('_', $subscription->taxes)))->get();
            $totalTaxes = ($plan->price * $taxes->sum('percentage') / 100);
            $planPrice = ($plan->price + $totalTaxes);

            if ($subscription->subscriber->wallet >= $planPrice && $subscription->creator->free_subscription == 'no') {
              // Admin and user earnings calculation
              $earnings = $this->earningsAdminUser($subscription->creator->custom_fee, $plan->price, null, null);

              // Insert Transaction
              $this->transaction(
                'subw_' . str_random(25),
                $subscription->subscriber->id,
                $subscription->id,
                $subscription->creator->id,
                $plan->price,
                $earnings['user'],
                $earnings['admin'],
                'Wallet',
                'subscription',
                $earnings['percentageApplied'],
                $subscription->taxes
              );

              // Subtract user funds
              $subscription->subscriber->decrement('wallet', $planPrice);

              // Add Earnings to Creator
              $subscription->creator->increment('balance', $earnings['user']);

              // Send Notification to User --- destination, author, type, target
              Notifications::send($subscription->creator->id, $subscription->subscriber->id, 12, $subscription->subscriber->id);

              $subscription->update([
                'ends_at' => $subscription->creator->planInterval($plan->interval)
              ]);
            }
          }
        }
      }
    });
  }
}
