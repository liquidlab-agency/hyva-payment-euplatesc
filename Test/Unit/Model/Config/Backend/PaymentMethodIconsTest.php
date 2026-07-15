<?php

/**
 * Copyright © - LiquidLab Agency - All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Liquidlab\HyvaEuPlatesc\Test\Unit\Model\Config\Backend;

use Liquidlab\HyvaEuPlatesc\Model\Config\Backend\PaymentMethodIcons;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Model\Context;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use PHPUnit\Framework\TestCase;

/**
 * The backend model is the storage contract for the visual selector: the checkbox array (plus the empty
 * guard) must persist exactly like a native multiselect — a clean comma string, empties filtered, cleared
 * when nothing is selected.
 */
class PaymentMethodIconsTest extends TestCase
{
    private PaymentMethodIcons $model;

    protected function setUp(): void
    {
        $objectManager = new ObjectManager($this);
        // parent::beforeSave() dispatches model events — give the context a no-op event manager.
        $context = $this->createMock(Context::class);
        $context->method('getEventDispatcher')->willReturn($this->createMock(ManagerInterface::class));

        $this->model = $objectManager->getObject(PaymentMethodIcons::class, ['context' => $context]);
    }

    public function testBeforeSaveNormalisesArrayToCleanCommaString(): void
    {
        $this->model->setValue(['', 'visa', ' apple-pay ', '', 'iconmanager:12']);

        $this->model->beforeSave();

        $this->assertSame('visa,apple-pay,iconmanager:12', $this->model->getValue());
    }

    public function testBeforeSaveClearsValueWhenNothingSelected(): void
    {
        // "De-select all" posts only the hidden empty guard.
        $this->model->setValue(['']);

        $this->model->beforeSave();

        $this->assertSame('', $this->model->getValue());
    }

    public function testBeforeSaveLeavesPlainStringUntouched(): void
    {
        $this->model->setValue('visa,ideal');

        $this->model->beforeSave();

        $this->assertSame('visa,ideal', $this->model->getValue());
    }
}
