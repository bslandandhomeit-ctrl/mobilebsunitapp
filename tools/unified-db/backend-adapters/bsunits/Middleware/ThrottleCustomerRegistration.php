<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Routing\Middleware\ThrottleRequests;

class ThrottleCustomerRegistration extends ThrottleRequests
{
    public function handle($request, Closure $next, $maxAttempts = 5, $decayMinutes = 1)
    {
        return parent::handle($request, $next, $maxAttempts, $decayMinutes);
    }

    protected function resolveRequestSignature($request)
    {
        // Laravel 5.8's default key is shared by all routes for an IP.
        return sha1('customer-registration|'.$request->ip());
    }
}
