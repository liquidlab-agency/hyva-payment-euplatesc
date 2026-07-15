<?php

/**
 * Copyright © - LiquidLab Agency - All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Liquidlab\HyvaEuPlatesc\Test\Unit\Model\Payment;

use Liquidlab\HyvaEuPlatesc\Model\Payment\IconRegistry;
use Liquidlab\HyvaEuPlatesc\ViewModel\PaymentIcons;
use Magento\Framework\DataObject;
use Magento\Framework\DataObjectFactory;
use Magento\Framework\Event\ManagerInterface as EventManager;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\Module\Dir;
use Magento\Framework\Module\Dir\Reader;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Unit coverage for the decoupled icon-provider seam: own bundled SVGs merged with any provider's icons via
 * the shared events, with own-wins precedence, label sort, order preservation, empty filtering, and the
 * "a broken provider must never break checkout" resilience guarantee.
 */
class IconRegistryTest extends TestCase
{
    private const ICON_DIR = '/module/view/frontend/web/svg/payment';

    private Reader $moduleReader;
    private File $fileDriver;
    private PaymentIcons $paymentIcons;
    private DataObjectFactory $dataObjectFactory;

    protected function setUp(): void
    {
        $this->moduleReader = $this->createMock(Reader::class);
        $this->moduleReader->method('getModuleDir')
            ->with(Dir::MODULE_VIEW_DIR, 'Liquidlab_HyvaEuPlatesc')
            ->willReturn('/module/view');

        // Two own SVGs (+ a non-svg that must be ignored).
        $this->fileDriver = $this->createMock(File::class);
        $this->fileDriver->method('isExists')->willReturn(true);
        $this->fileDriver->method('isDirectory')->willReturn(true);
        $this->fileDriver->method('readDirectory')->willReturn([
            self::ICON_DIR . '/visa.svg',
            self::ICON_DIR . '/mastercard.svg',
            self::ICON_DIR . '/readme.txt',
        ]);

        $this->paymentIcons = $this->createMock(PaymentIcons::class);
        $this->paymentIcons->method('renderHtml')
            ->willReturnCallback(static fn (string $name): string => '<svg data-icon="' . $name . '"></svg>');

        // Real DataObject instances (a plain value holder) keep the event-payload behaviour realistic.
        $this->dataObjectFactory = $this->createMock(DataObjectFactory::class);
        $this->dataObjectFactory->method('create')
            ->willReturnCallback(static fn (array $args = []): DataObject => new DataObject($args['data'] ?? []));
    }

    public function testGetAvailableOptionsMergesOwnAndProviderSortedByLabel(): void
    {
        $registry = $this->createRegistry($this->providerDispatch());

        $options = $registry->getAvailableOptions();

        $this->assertSame(['Alpha', 'Mastercard', 'Visa', 'Zeta'], array_column($options, 'label'));
        $byValue = array_column($options, 'label', 'value');
        $this->assertSame('Visa', $byValue['visa']);
        $this->assertSame('Alpha', $byValue['iconmanager:6']);
    }

    public function testOwnIconWinsOnValueCollision(): void
    {
        $registry = $this->createRegistry(static function (string $event, array $data): void {
            if ($event === IconRegistry::EVENT_OPTIONS) {
                // Provider tries to contribute a value that collides with an own icon.
                $data['icons_collector']->setData('options', [['value' => 'visa', 'label' => 'Provider Visa']]);
            }
        });

        $options = $registry->getAvailableOptions();

        $this->assertCount(2, $options);
        $this->assertSame('Visa', array_column($options, 'label', 'value')['visa']);
    }

    public function testRenderIconsPreservesOrderAndSkipsUnrenderable(): void
    {
        $registry = $this->createRegistry($this->providerDispatch());

        $html = $registry->renderIcons(['iconmanager:5', 'visa', 'ghost']);

        $this->assertCount(2, $html);
        $this->assertStringContainsString('iconmanager:5', $html[0]);
        $this->assertStringContainsString('data-icon="visa"', $html[1]);
    }

    public function testRenderIconsMapIsKeyedByValue(): void
    {
        $registry = $this->createRegistry($this->providerDispatch());

        $map = $registry->renderIconsMap(['visa', 'iconmanager:5']);

        $this->assertArrayHasKey('visa', $map);
        $this->assertArrayHasKey('iconmanager:5', $map);
        $this->assertStringContainsString('data-icon="visa"', $map['visa']);
    }

    public function testEmptyAndWhitespaceValuesAreFiltered(): void
    {
        $registry = $this->createRegistry($this->providerDispatch());

        $html = $registry->renderIcons(['', '   ', 'visa']);

        $this->assertCount(1, $html);
        $this->assertStringContainsString('data-icon="visa"', $html[0]);
    }

    public function testBrokenProviderNeverBreaksOptionsAndIsLogged(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning');

        $registry = $this->createRegistry(static function (): void {
            throw new \RuntimeException('provider blew up');
        }, $logger);

        // Provider threw, yet the module's own icons are still returned.
        $this->assertSame(['mastercard', 'visa'], array_column($registry->getAvailableOptions(), 'value'));
    }

    private function createRegistry(callable $dispatch, ?LoggerInterface $logger = null): IconRegistry
    {
        $eventManager = $this->createMock(EventManager::class);
        $eventManager->method('dispatch')->willReturnCallback($dispatch);

        return new IconRegistry(
            $this->moduleReader,
            $this->fileDriver,
            $this->paymentIcons,
            $eventManager,
            $this->dataObjectFactory,
            $logger ?? $this->createMock(LoggerInterface::class)
        );
    }

    /**
     * A well-behaved provider: contributes two options and renders any 'iconmanager:' value as an <img>.
     */
    private function providerDispatch(): callable
    {
        return static function (string $event, array $data): void {
            if ($event === IconRegistry::EVENT_OPTIONS) {
                /** @var DataObject $collector */
                $collector = $data['icons_collector'];
                $options = (array) $collector->getData('options');
                $options[] = ['value' => 'iconmanager:5', 'label' => 'Zeta'];
                $options[] = ['value' => 'iconmanager:6', 'label' => 'Alpha'];
                $collector->setData('options', $options);
            } elseif ($event === IconRegistry::EVENT_RENDER) {
                /** @var DataObject $result */
                $result = $data['icons_result'];
                $map = (array) $result->getData('html_map');
                foreach ((array) $result->getData('requested_values') as $value) {
                    if (str_starts_with((string) $value, 'iconmanager:')) {
                        $map[$value] = '<img alt="' . $value . '"/>';
                    }
                }
                $result->setData('html_map', $map);
            }
        };
    }
}
