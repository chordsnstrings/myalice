<?php

namespace App\Ai;

use App\Models\Order;

/** No processor wired yet — returns null so the agent tells the customer a teammate will follow up. */
class NullPaymentLinkGenerator implements PaymentLinkGenerator
{
    public function generate(Order $order): ?string
    {
        return null;
    }
}
