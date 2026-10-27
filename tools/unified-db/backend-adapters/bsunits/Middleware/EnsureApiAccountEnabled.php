<?php
namespace App\Http\Middleware;

use Closure;

class EnsureApiAccountEnabled
{
    public function handle($request, Closure $next)
    {
        $user = $request->user();
        if (!$user) return response()->json(['error' => ['code' => 401, 'message' => 'Unauthenticated.']], 401);
        if (!$user->active || !$user->verified) {
            return response()->json(['error' => ['code' => 401, 'message' => !$user->active
                ? 'Your account has been deactivated.' : 'Your account is not verified.']], 401);
        }
        if ($user->hasRole('customer')) {
            // Explicit read allow-list: arbitrary staff endpoints stay inaccessible.
            $readPaths = [
                'api/whoami', 'api/projects', 'api/projects/[0-9]+',
                'api/projects/[0-9]+/(units|units_for_plan|unit_type|unit_types)',
                'api/unit_types', 'api/unit_types/[0-9]+',
                'api/unit_types/[0-9]+/(units|payment_options|discountPromotion)',
                'api/units', 'api/units/[0-9]+',
                'api/payment_options', 'api/payment_options/[0-9]+',
            ];
            $allowed = $request->isMethod('GET') && preg_match(
                '#^(?:'.implode('|', $readPaths).')$#D', $request->path()
            );
            $allowed = $allowed || ($request->isMethod('POST') && $request->path() === 'api/whoami/logout');
            if (!$allowed) return response()->json(['message' => 'Customer accounts can only browse projects and units.'], 403);
            $embeds = $request->path() === 'api/whoami' ? ['roles'] : [
                'unitTypes', 'unitTypes.activeDiscountPromotions', 'unitType',
                'unitType.project', 'unitType.media', 'unitType.activeDiscountPromotions',
                'project', 'media', 'activeDiscountPromotions', 'paymentOptions',
            ];
            $requested = explode(',', (string) $request->input('embed', ''));
            $request->merge(['embed' => implode(',', array_intersect($requested, $embeds))]);
            $response = $next($request);
            // Units eagerly load action.createdBy even without an embed query.
            if ($request->path() !== 'api/whoami' && method_exists($response, 'getData') && method_exists($response, 'setData')) {
                $response->setData($this->catalogOnly($response->getData(true)));
            }
            return $response;
        }
        return $next($request);
    }

    private function catalogOnly($value)
    {
        if (!is_array($value)) return $value;
        foreach (['action', 'actions', 'created_by', 'createdBy', 'user_id', 'unit_action_id',
            'comments', 'construction_procedures', 'metadata', 'contract_template',
            'contract_template_id', 'customer', 'customers', 'buyer', 'buyers',
            'unit_hold_requests', 'unit_deposit_requests', 'unit_contract_requests'] as $key) {
            unset($value[$key]);
        }
        foreach ($value as $key => $child) $value[$key] = $this->catalogOnly($child);
        return $value;
    }
}
