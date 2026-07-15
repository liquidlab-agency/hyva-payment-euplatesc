<?php

/**
 * Copyright © - LiquidLab Agency - All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Liquidlab\HyvaEuPlatesc\ViewModel;

use Hyva\Theme\ViewModel\SvgIcons;

/**
 * Self-contained payment-icon set for the EuPlatesc checkout method.
 *
 * Extends the Hyvä core SvgIcons view model directly (from hyva-themes/magento2-theme-module), so the module
 * needs no theme package of its own. The icon path prefix
 * (Liquidlab_HyvaEuPlatesc::svg/payment) is injected in etc/frontend/di.xml; the SVGs ship in this module's
 * view/frontend/web/svg/payment folder. Render with renderHtml('visa', '', $w, $h) — magic visaHtml() works.
 *
 * @method string visaHtml(string $classnames = '', ?int $width = 24, ?int $height = 24, array $attributes = [])
 * @method string mastercardHtml(string $classnames = '', ?int $width = 24, ?int $height = 24, array $attributes = [])
 * @method string maestroHtml(string $classnames = '', ?int $width = 24, ?int $height = 24, array $attributes = [])
 * @method string amexHtml(string $classnames = '', ?int $width = 24, ?int $height = 24, array $attributes = [])
 * @method string applePayHtml(string $classnames = '', ?int $width = 24, ?int $height = 24, array $attributes = [])
 */
class PaymentIcons extends SvgIcons
{
}
