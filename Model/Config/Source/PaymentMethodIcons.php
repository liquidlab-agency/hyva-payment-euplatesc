<?php

/**
 * Copyright © - LiquidLab Agency - All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Liquidlab\HyvaEuPlatesc\Model\Config\Source;

use Liquidlab\HyvaEuPlatesc\Model\Payment\IconRegistry;
use Magento\Framework\Data\OptionSourceInterface;

/**
 * Multiselect options for the EuPlatesc "Payment Method Icons" admin field.
 *
 * Thin adapter over {@see IconRegistry}, which merges this module's own bundled SVGs with any icons
 * contributed by external providers through the decoupled payment-icon events (e.g. Liquidlab_IconManager
 * surfaces its admin-managed payment icons here). This module stays independent of every provider.
 */
class PaymentMethodIcons implements OptionSourceInterface
{
    public function __construct(
        private readonly IconRegistry $iconRegistry
    ) {
    }

    /**
     * @return array<int, array{label: string, value: string}>
     */
    public function toOptionArray(): array
    {
        return $this->iconRegistry->getAvailableOptions();
    }
}
