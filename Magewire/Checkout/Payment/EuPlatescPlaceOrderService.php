<?php

/**
 * Copyright © - LiquidLab Agency - All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Liquidlab\HyvaEuPlatesc\Magewire\Checkout\Payment;

use Hyva\Checkout\Model\Magewire\Payment\AbstractPlaceOrderService;
use Magento\Quote\Model\Quote;

class EuPlatescPlaceOrderService extends AbstractPlaceOrderService
{
    /**
     * Get redirect URL for payment method
     *
     * @param Quote $quote
     * @param int|null $orderId
     */
    public function getRedirectUrl(Quote $quote, ?int $orderId = null): string
    {
        return 'checkout/onepage/success';
    }
}
