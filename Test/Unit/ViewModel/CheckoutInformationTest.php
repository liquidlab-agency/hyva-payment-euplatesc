<?php

/**
 * Copyright © - LiquidLab Agency - All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Liquidlab\HyvaEuPlatesc\Test\Unit\ViewModel;

use Liquidlab\HyvaEuPlatesc\Model\Payment\IconRegistry;
use Liquidlab\HyvaEuPlatesc\ViewModel\CheckoutInformation;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;

/**
 * getIcons() turns the stored comma string (the multiselect / selector format) into the ordered list of
 * opaque icon values the checkout renders — trimming and dropping empties so a stray comma never yields a
 * blank icon.
 */
class CheckoutInformationTest extends TestCase
{
    private const XML_PATH_ICONS = 'payment/eppay/checkout_icons';

    public function testGetIconsSplitsTrimsAndFiltersStoredValue(): void
    {
        $checkoutInformation = $this->createCheckoutInformation(' apple-pay , visa ,, iconmanager:12 ,');

        $this->assertSame(['apple-pay', 'visa', 'iconmanager:12'], $checkoutInformation->getIcons());
    }

    public function testGetIconsReturnsEmptyArrayWhenUnset(): void
    {
        $checkoutInformation = $this->createCheckoutInformation('');

        $this->assertSame([], $checkoutInformation->getIcons());
    }

    private function createCheckoutInformation(string $storedIconsValue): CheckoutInformation
    {
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')
            ->willReturnCallback(
                static fn (string $path): string => $path === self::XML_PATH_ICONS ? $storedIconsValue : ''
            );

        return new CheckoutInformation(
            $scopeConfig,
            $this->createMock(IconRegistry::class),
            $this->createMock(StoreManagerInterface::class)
        );
    }
}
