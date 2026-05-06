<?php

/**
 * Copyright © - Liquidlab - All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Liquidlab\HyvaEuPlatesc\Observer;

use Magento\Framework\Component\ComponentRegistrar;
use Magento\Framework\Event\Observer as Event;
use Magento\Framework\Event\ObserverInterface;

class RegisterModuleForHyvaConfig implements ObserverInterface
{
    public function __construct(private ComponentRegistrar $componentRegistrar)
    {
    }

    public function execute(Event $event): void
    {
        $config = $event->getData('config');
        $extensions = $config->hasData('extensions') ? $config->getData('extensions') : [];

        $path = $this->componentRegistrar->getPath(
            ComponentRegistrar::MODULE,
            'Liquidlab_HyvaEuPlatesc'
        );

        // Only use the path relative to the Magento base dir
        if ($path !== null) {
            $extensions[] = ['src' => substr($path, strlen(BP) + 1)];
        }

        $config->setData('extensions', $extensions);
    }
}
