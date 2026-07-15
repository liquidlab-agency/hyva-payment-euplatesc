<?php

/**
 * Copyright © - LiquidLab Agency - All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Liquidlab\HyvaEuPlatesc\Block\Adminhtml\System\Config;

use Liquidlab\HyvaEuPlatesc\Model\Payment\IconRegistry;
use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\App\Area;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Store\Model\App\Emulation;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Renders the "Payment Method Icons" admin field as a visual checkbox grid — every selectable icon
 * is shown as its actual rendered image beside its checkbox, so an admin sees exactly what they pick.
 *
 * The icon HTML comes from {@see IconRegistry} (this module's own bundled SVGs + any provider that answers
 * the shared "LiquidLab icon provider" events, e.g. Icon Manager), so this stays fully decoupled from every
 * provider. The own SVGs are FRONTEND assets, so the preview is rendered under frontend store emulation —
 * the config page runs in the adminhtml area — combined with the adminhtml iconPathPrefix wired in
 * etc/adminhtml/di.xml. Provider media URLs also resolve correctly against the emulated frontend store.
 *
 * Values serialise exactly like the native multiselect (comma-joined, via the field's backend_model), so the
 * stored config and the frontend consumer are unchanged. A hidden empty guard makes "de-select all" clear it.
 */
class PaymentIconSelector extends Field
{
    private const ICON_WIDTH = 40;
    private const ICON_HEIGHT = 26;

    public function __construct(
        Context $context,
        private readonly IconRegistry $iconRegistry,
        private readonly Emulation $emulation,
        private readonly StoreManagerInterface $storeManager,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    protected function _getElementHtml(AbstractElement $element): string
    {
        // Multiselect::getName() already appends "[]"; normalise to exactly one array suffix so the checkbox
        // group posts as groups[...][value][] — the native multiselect shape the backend_model expects.
        $arrayName = preg_replace('/\[\]$/', '', (string) $element->getName()) . '[]';
        $selected = $this->normalizeSelected($element->getValue());
        $storeId = $this->resolvePreviewStoreId();
        $options = $this->iconRegistry->getAvailableOptions($storeId);

        if ($options === []) {
            return '<div class="ll-eppay-icons ll-eppay-icons--empty">'
                . $this->escapeHtml(
                    __('No payment icons available yet. Drop an SVG into this module\'s svg/payment folder, '
                        . 'or create a Payment-type icon in Icon Manager (Admin → Icons).')
                )
                . '</div>';
        }

        $iconHtml = $this->renderIconMap($options, $storeId);

        $rows = '';
        foreach ($options as $option) {
            $value = (string) $option['value'];
            $label = (string) $option['label'];
            $isChecked = isset($selected[$value]);
            $icon = $iconHtml[$value] ?? '';

            $rows .= '<label class="ll-eppay-icon' . ($isChecked ? ' is-selected' : '') . '">'
                . '<input type="checkbox" name="' . $arrayName . '"'
                . ' value="' . $this->escapeHtmlAttr($value) . '"' . ($isChecked ? ' checked="checked"' : '') . '/>'
                . '<span class="ll-eppay-icon__img">' . ($icon !== '' ? $icon : $this->missingBadge()) . '</span>'
                . '<span class="ll-eppay-icon__label">' . $this->escapeHtml($label) . '</span>'
                . '</label>';
        }

        // Empty guard: keep the field present in the POST even when nothing is checked, so the backend_model
        // runs and clears the value on "de-select all". The empty element is filtered out on save + on read.
        return $this->styles()
            . '<div class="ll-eppay-icons">'
            . '<input type="hidden" name="' . $arrayName . '" value=""/>'
            . $rows
            . '</div>';
    }

    /**
     * Render each available icon to safe HTML, keyed by value, under frontend emulation so the module's own
     * frontend SVG assets (and provider media URLs) resolve while we sit on the adminhtml config page.
     *
     * @param array<int, array{value: string, label: string}> $options
     * @return array<string, string> value => safe icon HTML
     */
    private function renderIconMap(array $options, int $storeId): array
    {
        $values = array_map(static fn (array $o): string => (string) $o['value'], $options);

        try {
            $this->emulation->startEnvironmentEmulation($storeId, Area::AREA_FRONTEND, true);
            try {
                return $this->iconRegistry->renderIconsMap($values, self::ICON_WIDTH, self::ICON_HEIGHT, $storeId);
            } finally {
                $this->emulation->stopEnvironmentEmulation();
            }
        } catch (\Throwable $e) {
            // A preview hiccup must never break the config page; fall back to name-only chips.
            return [];
        }
    }

    /**
     * @param mixed $value stored config value (comma-separated string or array)
     * @return array<string, true> selected values as a lookup set
     */
    private function normalizeSelected($value): array
    {
        if (is_string($value)) {
            $value = explode(',', $value);
        }
        if (!is_array($value)) {
            return [];
        }

        $set = [];
        foreach ($value as $item) {
            $item = trim((string) $item);
            if ($item !== '') {
                $set[$item] = true;
            }
        }

        return $set;
    }

    /**
     * The store whose frontend the preview should reflect: the store/website being edited, else the default.
     */
    private function resolvePreviewStoreId(): int
    {
        $storeParam = $this->getRequest()->getParam('store');
        if ($storeParam !== null && $storeParam !== '') {
            return (int) $storeParam;
        }

        try {
            $website = $this->getRequest()->getParam('website');
            if ($website) {
                return (int) $this->storeManager->getWebsite($website)->getDefaultStore()->getId();
            }
            $default = $this->storeManager->getDefaultStoreView();
            if ($default !== null) {
                return (int) $default->getId();
            }
            foreach ($this->storeManager->getStores() as $store) {
                return (int) $store->getId();
            }
        } catch (\Throwable $e) {
            // fall through to the safe default
        }

        return 1;
    }

    private function missingBadge(): string
    {
        return '<span class="ll-eppay-icon__missing" title="'
            . $this->escapeHtmlAttr((string) __('Preview unavailable')) . '">?</span>';
    }

    /**
     * Scoped inline styles keep the field self-contained — no admin CSS build step to deploy.
     */
    private function styles(): string
    {
        return '<style>'
            . '.ll-eppay-icons{display:flex;flex-wrap:wrap;gap:8px;max-width:820px}'
            . '.ll-eppay-icons--empty{color:#767676;font-style:italic}'
            . '.ll-eppay-icon{display:inline-flex;align-items:center;gap:8px;padding:6px 10px;border:1px solid '
            . '#d6d6d6;border-radius:6px;background:#fff;cursor:pointer;min-width:150px;transition:border-color .1s}'
            . '.ll-eppay-icon:hover{border-color:#a3a3a3}'
            . '.ll-eppay-icon.is-selected,.ll-eppay-icon:has(input:checked){border-color:#1979c3;background:#eaf2fb}'
            . '.ll-eppay-icon input{margin:0;flex:0 0 auto}'
            . '.ll-eppay-icon__img{display:inline-flex;align-items:center;justify-content:center;'
            . 'width:40px;height:26px;flex:0 0 auto}'
            . '.ll-eppay-icon__img svg,.ll-eppay-icon__img img{max-width:40px;max-height:26px;width:auto;height:auto}'
            . '.ll-eppay-icon__label{font-size:1.2rem;color:#303030}'
            . '.ll-eppay-icon__missing{display:inline-flex;align-items:center;justify-content:center;width:26px;'
            . 'height:26px;border-radius:50%;background:#f2f2f2;color:#a3a3a3;font-weight:700;font-size:1.2rem}'
            . '</style>';
    }
}
