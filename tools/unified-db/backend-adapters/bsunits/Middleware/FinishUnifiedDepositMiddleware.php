<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Support\Facades\Log;
class FinishUnifiedDepositMiddleware
{
    public function handle($request, Closure $next)
    {
        $response=$next($request);
        if (app()->environment('local') && config('unified_catalog.deposit_conversion_enabled')) {
            try {
                app(\App\Services\UnifiedApprovedDepositOutbox::class)->drain();
                $remaining=\Illuminate\Support\Facades\DB::table('unified_approved_deposit_outbox')->count();
                $response->headers->set('X-Unified-Deposit-Sync',$remaining?'pending':'complete');
            } catch (\Throwable $e) {
                // The source approval is already durable; retain its event for retry.
                Log::warning('Local approved-deposit conversion pending; review mapping or retry worker.');
                $response->headers->set('X-Unified-Deposit-Sync','pending');
            }
        }
        return $response;
    }
}
