<?php

/**
 * Copyright © - LiquidLab Agency - All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Liquidlab\HyvaEuPlatesc\Model\Payment;

use Liquidlab\HyvaEuPlatesc\ViewModel\PaymentIcons;
use Magento\Framework\DataObject;
use Magento\Framework\DataObjectFactory;
use Magento\Framework\Event\ManagerInterface as EventManager;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\Module\Dir;
use Magento\Framework\Module\Dir\Reader;
use Psr\Log\LoggerInterface;

/**
 * Central registry for the EuPlatesc checkout payment icons.
 *
 * It merges TWO sources without EuPlatesc depending on either provider:
 *   1. This module's OWN bundled SVGs (view/frontend/web/svg/payment), rendered inline via Hyvä SvgIcons.
 *   2. Any external provider that answers the two shared "LiquidLab icon provider" events below — e.g.
 *      Liquidlab_IconManager contributes its admin-managed icons. Providers are wired purely through
 *      events, so this module has NO code dependency on them (and works fine when none are installed).
 *
 * The event NAMES are deliberately generic (NOT "euplatesc"-branded) and the payload is a plain
 * {@see DataObject} (a neutral framework contract — no provider references an EuPlatesc class). That is
 * the whole point of the contract: a provider registers ONE observer, once and globally, on these shared
 * events and thereby serves EVERY consumer that fires them — this module and any future module — with
 * zero per-consumer wiring on the provider side. The `category` field narrows the request (here 'payment').
 *
 *   EVENT_OPTIONS ('liquidlab_icons_collect_options'), data ['icons_collector' => DataObject]
 *     reads  : category (string), store_id (?int)
 *     appends: options[]  each ['value' => '<provider>:<id>', 'label' => '<label>']
 *
 *   EVENT_RENDER ('liquidlab_icons_render'), data ['icons_result' => DataObject]
 *     reads : requested_values (string[]), width (int), height (int), category (string), store_id (?int)
 *     fills : html_map  array<string,string>  value => safe, ready-to-echo HTML
 */
class IconRegistry
{
    public const EVENT_OPTIONS = 'liquidlab_icons_collect_options';
    public const EVENT_RENDER = 'liquidlab_icons_render';

    /** Neutral category hint providers MAY use to filter (Icon Manager maps it to an icon "type"). */
    public const CATEGORY = 'payment';

    private const MODULE_NAME = 'Liquidlab_HyvaEuPlatesc';
    private const ICON_RELATIVE_DIR = 'frontend/web/svg/payment';

    /** @var array<string, string>|null memoized own filename => label */
    private ?array $ownIcons = null;

    public function __construct(
        private readonly Reader $moduleReader,
        private readonly File $fileDriver,
        private readonly PaymentIcons $paymentIcons,
        private readonly EventManager $eventManager,
        private readonly DataObjectFactory $dataObjectFactory,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Selectable icons for the admin multiselect: this module's own SVGs plus every provider's icons.
     *
     * @return array<int, array{value: string, label: string}> sorted by label; own values win on collision
     */
    public function getAvailableOptions(?int $storeId = null): array
    {
        $merged = [];
        foreach ($this->getOwnIcons() as $value => $label) {
            $merged[$value] = ['value' => $value, 'label' => $label];
        }

        /** @var DataObject $collector */
        $collector = $this->dataObjectFactory->create([
            'data' => [
                'category' => self::CATEGORY,
                'store_id' => $storeId,
                'options' => [],
            ],
        ]);
        $this->dispatch(self::EVENT_OPTIONS, ['icons_collector' => $collector]);

        foreach ((array) $collector->getData('options') as $option) {
            if (!is_array($option) || !isset($option['value'], $option['label'])) {
                continue;
            }
            $value = (string) $option['value'];
            if ($value === '' || isset($merged[$value])) {
                continue; // own icons take precedence on a value collision
            }
            $merged[$value] = ['value' => $value, 'label' => (string) $option['label']];
        }

        $options = array_values($merged);
        usort($options, static fn (array $a, array $b): int => strcmp($a['label'], $b['label']));

        return $options;
    }

    /**
     * Resolve the admin-selected values to safe HTML, in the selected order, silently skipping any value
     * no provider could render (deleted/renamed icon, disabled provider, …).
     *
     * @param string[] $values
     * @return array<int, string> ready-to-echo HTML
     */
    public function renderIcons(array $values, int $width = 48, int $height = 32, ?int $storeId = null): array
    {
        $htmlByValue = $this->renderIconsMap($values, $width, $height, $storeId);
        if ($htmlByValue === []) {
            return [];
        }

        $ordered = [];
        foreach ($values as $value) {
            $value = trim((string) $value);
            if ($value !== '' && isset($htmlByValue[$value])) {
                $ordered[] = $htmlByValue[$value];
            }
        }

        return $ordered;
    }

    /**
     * Resolve values to a value => safe-HTML map (any value no provider can render is omitted). Unlike
     * {@see renderIcons()} this keeps the value association, so a consumer can pair each icon with its own
     * value — e.g. the admin "Payment Method Icons" selector, which shows every option's icon next to it.
     *
     * @param string[] $values
     * @return array<string, string> value => ready-to-echo HTML
     */
    public function renderIconsMap(array $values, int $width = 48, int $height = 32, ?int $storeId = null): array
    {
        $values = array_values(array_filter(
            array_map(static fn ($v): string => trim((string) $v), $values),
            static fn (string $v): bool => $v !== ''
        ));
        if ($values === []) {
            return [];
        }

        $htmlByValue = [];
        $own = $this->getOwnIcons();
        $external = [];

        foreach ($values as $value) {
            if (isset($own[$value])) {
                try {
                    $htmlByValue[$value] = $this->paymentIcons->renderHtml($value, '', $width, $height);
                } catch (\Throwable $e) {
                    // A removed/renamed own SVG must never break the checkout order flow.
                    $this->logger->warning('EuPlatesc own payment icon failed to render: ' . $value, [
                        'exception' => $e,
                    ]);
                }
            } else {
                $external[] = $value;
            }
        }

        if ($external !== []) {
            /** @var DataObject $result */
            $result = $this->dataObjectFactory->create([
                'data' => [
                    'category' => self::CATEGORY,
                    'store_id' => $storeId,
                    'width' => $width,
                    'height' => $height,
                    'requested_values' => $external,
                    'html_map' => [],
                ],
            ]);
            $this->dispatch(self::EVENT_RENDER, ['icons_result' => $result]);

            foreach ((array) $result->getData('html_map') as $value => $html) {
                $value = (string) $value;
                if (is_string($html) && $html !== '' && !isset($htmlByValue[$value])) {
                    $htmlByValue[$value] = $html;
                }
            }
        }

        return $htmlByValue;
    }

    /**
     * This module's bundled payment SVGs as filename => label. Store-independent, so memoized per request.
     *
     * @return array<string, string>
     */
    private function getOwnIcons(): array
    {
        if ($this->ownIcons !== null) {
            return $this->ownIcons;
        }

        $this->ownIcons = [];
        try {
            $iconDir = $this->moduleReader->getModuleDir(Dir::MODULE_VIEW_DIR, self::MODULE_NAME)
                . '/' . self::ICON_RELATIVE_DIR;

            if ($this->fileDriver->isExists($iconDir) && $this->fileDriver->isDirectory($iconDir)) {
                foreach ($this->fileDriver->readDirectory($iconDir) as $absolutePath) {
                    if (strtolower((string) pathinfo($absolutePath, PATHINFO_EXTENSION)) !== 'svg') {
                        continue;
                    }
                    $filename = pathinfo($absolutePath, PATHINFO_FILENAME);
                    $this->ownIcons[$filename] = ucwords(str_replace(['-', '_'], ' ', $filename));
                }
            }
        } catch (\Throwable $e) {
            $this->logger->warning('EuPlatesc own payment icon scan failed', ['exception' => $e]);
        }

        return $this->ownIcons;
    }

    /**
     * A misbehaving observer (broken provider) must never take down the admin config page or checkout.
     *
     * @param array<string, mixed> $data
     */
    private function dispatch(string $eventName, array $data): void
    {
        try {
            $this->eventManager->dispatch($eventName, $data);
        } catch (\Throwable $e) {
            $this->logger->warning('EuPlatesc payment-icon event failed: ' . $eventName, ['exception' => $e]);
        }
    }
}
