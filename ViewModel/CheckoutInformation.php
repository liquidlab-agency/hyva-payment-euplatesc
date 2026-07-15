<?php

/**
 * Copyright © - LiquidLab Agency - All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Liquidlab\HyvaEuPlatesc\ViewModel;

use Liquidlab\HyvaEuPlatesc\Model\Payment\IconRegistry;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Exposes the admin-configurable checkout description text + rendered payment icons for the EuPlatesc
 * method template. Store-view scoped so the text/icons can be configured per language.
 *
 * The selected icon values are opaque here (bare SVG filename for this module's own icons, or a
 * provider-tagged value such as 'iconmanager:12'). Resolving them to safe HTML is delegated to
 * {@see IconRegistry}, which asks each provider to render its own values — so this module needs no
 * knowledge of, or dependency on, where an icon comes from.
 */
class CheckoutInformation implements ArgumentInterface
{
    private const XML_PATH_CHECKOUT_DESCRIPTION = 'payment/eppay/checkout_description';
    private const XML_PATH_CHECKOUT_ICONS = 'payment/eppay/checkout_icons';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly IconRegistry $iconRegistry,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * Admin-entered description text (plain text; rendered escaped). Empty when unset — the template then
     * falls back to the translated default so behaviour is unchanged out of the box.
     */
    public function getDescription(): string
    {
        return trim((string) $this->scopeConfig->getValue(
            self::XML_PATH_CHECKOUT_DESCRIPTION,
            ScopeInterface::SCOPE_STORE
        ));
    }

    /**
     * Selected payment-icon values (e.g. ['apple-pay', 'visa', 'iconmanager:12']), in admin order.
     *
     * @return string[]
     */
    public function getIcons(): array
    {
        $value = (string) $this->scopeConfig->getValue(
            self::XML_PATH_CHECKOUT_ICONS,
            ScopeInterface::SCOPE_STORE
        );

        if ($value === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $value)), static fn ($v) => $v !== ''));
    }

    /**
     * Ready-to-echo, safe HTML for each selected icon, in the admin-selected order. Any value no provider
     * can render (deleted/renamed icon, disabled provider) is silently skipped.
     *
     * @return string[]
     */
    public function getRenderedIcons(int $width = 48, int $height = 32): array
    {
        $values = $this->getIcons();
        if ($values === []) {
            return [];
        }

        try {
            $storeId = (int) $this->storeManager->getStore()->getId();
        } catch (\Throwable $e) {
            $storeId = null;
        }

        return $this->iconRegistry->renderIcons($values, $width, $height, $storeId);
    }
}
