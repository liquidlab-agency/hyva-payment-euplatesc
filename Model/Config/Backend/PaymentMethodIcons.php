<?php

/**
 * Copyright © - LiquidLab Agency - All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Liquidlab\HyvaEuPlatesc\Model\Config\Backend;

use Magento\Framework\App\Config\Value;

/**
 * Normalises the "Payment Method Icons" value to a clean comma-separated string — the same storage
 * format a native multiselect uses, so {@see \Liquidlab\HyvaEuPlatesc\ViewModel\CheckoutInformation::getIcons}
 * is unchanged.
 *
 * The visual selector ({@see \Liquidlab\HyvaEuPlatesc\Block\Adminhtml\System\Config\PaymentIconSelector})
 * posts a checkbox array plus one empty "guard" element so that de-selecting everything still submits the
 * field (and thus clears it). Filtering empties here keeps the stored value tidy (no stray leading comma)
 * and makes "clear all" store an empty string.
 */
class PaymentMethodIcons extends Value
{
    /**
     * @return $this
     */
    public function beforeSave()
    {
        $value = $this->getValue();
        if (is_array($value)) {
            $value = implode(',', array_values(array_filter(
                array_map(static fn ($v): string => trim((string) $v), $value),
                static fn (string $v): bool => $v !== ''
            )));
            $this->setValue($value);
        }

        return parent::beforeSave();
    }
}
