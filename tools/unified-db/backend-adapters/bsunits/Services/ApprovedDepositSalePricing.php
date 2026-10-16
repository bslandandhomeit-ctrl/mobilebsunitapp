<?php
namespace App\Services;

/** Exact approved-request pricing. No current catalog reads and no float arithmetic. */
class ApprovedDepositSalePricing
{
    private function cents($value)
    {
        if (!is_string($value) && !is_int($value)) throw new \InvalidArgumentException('Use decimal strings for monetary values.');
        if (!preg_match('/^(0|[1-9][0-9]{0,9})(?:\.([0-9]{1,2}))?$/D', (string)$value, $parts)) {
            throw new \InvalidArgumentException('Invalid non-negative decimal.');
        }
        return (int)$parts[1] * 100 + (int)str_pad($parts[2] ?? '', 2, '0');
    }
    private function decimal($cents)
    {
        return intdiv($cents, 100).'.'.str_pad((string)($cents % 100), 2, '0', STR_PAD_LEFT);
    }
    public function calculate(array $request)
    {
        if (($request['status'] ?? null) !== 'APPROVED'
            || ($request['unit_controller_status'] ?? null) !== 'APPROVED'
            || !in_array($request['sale_manager_status'] ?? null, ['APPROVED', 'NOT_REQUIRED'], true)
            || !empty($request['deleted_at']) || !empty($request['to_unit_deposit_request_id'])) {
            throw new \LogicException('A current, fully approved deposit request is required.');
        }
        foreach (['unit_sale_price', 'discount_promotion', 'other_discount_allowance', 'deposit_amount'] as $field) {
            if (!array_key_exists($field, $request)) throw new \InvalidArgumentException('Missing approved monetary field.');
        }
        $price = $this->cents($request['unit_sale_price']);
        $promotion = $this->cents($request['discount_promotion']);
        $other = $this->cents($request['other_discount_allowance']);
        $deposit = $this->cents($request['deposit_amount']);
        $percentage = $this->cents($request['special_discount'] ?? '0');
        if ($percentage > 10000 || $promotion + $other > $price) throw new \InvalidArgumentException('Discounts exceed allowed bounds.');
        $base = $price - $promotion - $other;
        // Integer cents, percentage hundredths, rounded half-up to one cent.
        $paymentDiscount = intdiv($base * $percentage + 5000, 10000);
        $final = $base - $paymentDiscount;
        return [
            'unit_sale_price' => $this->decimal($price),
            'discount_promotion' => $this->decimal($promotion),
            'discount_other' => $this->decimal($other),
            'discount_payment_option' => $this->decimal($percentage),
            'amount_discount_payment_option' => $this->decimal($paymentDiscount),
            'price_before_payment_discount' => $this->decimal($base),
            // Existing Beloan create form submits its final net price in both fields.
            'price_after_discount' => $this->decimal($final),
            'final_price' => $this->decimal($final),
            'diposit_amount' => $this->decimal($deposit),
        ];
    }
}
