<?php
declare(strict_types=1);

namespace Liquidlab\HyvaEuPlatesc\Plugin\Magewire\Checkout\Payment;

use Hyva\Checkout\Magewire\Checkout\Payment\MethodList;
use Magento\Checkout\Model\Session as SessionCheckout;
use Magento\Quote\Api\PaymentMethodManagementInterface;

class MethodListPlugin
{
    private const PAYMENT_METHOD_CODE = 'eppay';

    public function __construct(
        protected SessionCheckout $sessionCheckout,
        protected PaymentMethodManagementInterface $paymentMethodManagement
    ) {
    }

    public function afterBoot(MethodList $subject): void
    {
        if ($subject->method === null) {
            $quote = $this->sessionCheckout->getQuote();
            $paymentMethods = $this->paymentMethodManagement->getList((int)$quote->getId());
            
            foreach ($paymentMethods as $paymentMethod) {
                if ($paymentMethod->getCode() === self::PAYMENT_METHOD_CODE) {
                    $subject->method = self::PAYMENT_METHOD_CODE;
                    $subject->updatedMethod(self::PAYMENT_METHOD_CODE);
                    break;
                }
            }
        }
    }
}
