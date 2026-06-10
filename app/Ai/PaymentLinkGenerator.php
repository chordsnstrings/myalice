<?php

namespace App\Ai;

use App\Models\Order;

/**
 * Generates a checkout/payment link for an order. Swap the binding in
 * AppServiceProvider when a real processor (Stripe, etc.) is wired.
 */
interface PaymentLinkGenerator
{
    public function generate(Order $order): ?string;
}
