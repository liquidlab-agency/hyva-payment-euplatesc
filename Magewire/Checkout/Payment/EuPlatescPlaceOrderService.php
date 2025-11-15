<?php

/**
 * Copyright © - LiquidLab Agency - All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Liquidlab\HyvaEuPlatesc\Magewire\Checkout\Payment;

use Hyva\Checkout\Model\Magewire\Payment\AbstractPlaceOrderService;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Message\Manager as MessageManager;
use Magento\Framework\UrlInterface;
use Magento\Quote\Model\Quote;
use Magento\Sales\Api\OrderRepositoryInterface;
use EuroPayment\EuPlatesc\Model\EpPay;

class EuPlatescPlaceOrderService extends AbstractPlaceOrderService
{
    private UrlInterface $url;
    private CheckoutSession $checkoutSession;
    private MessageManager $messageManager;
    private OrderRepositoryInterface $orderRepository;
    private EpPay $epPay;

    public function __construct(
        \Magento\Quote\Api\CartManagementInterface $cartManagement,
        UrlInterface $url,
        CheckoutSession $checkoutSession,
        MessageManager $messageManager,
        OrderRepositoryInterface $orderRepository,
        EpPay $epPay
    ) {
        parent::__construct($cartManagement);
        $this->url = $url;
        $this->checkoutSession = $checkoutSession;
        $this->messageManager = $messageManager;
        $this->orderRepository = $orderRepository;
        $this->epPay = $epPay;
    }

    /**
     * Get redirect URL for payment method
     *
     * @param Quote $quote
     * @param int|null $orderId
     */
    public function getRedirectUrl(Quote $quote, ?int $orderId = null): string
    {
        try {
            if (!$orderId) {
                throw new LocalizedException(__('Unable to start EuPlatesc payment: missing order reference.'));
            }

            // Load the order to retrieve the increment ID expected by the EuPlatesc integration.
            $order = $this->orderRepository->get($orderId);
            $incrementId = (string) $order->getIncrementId();

            // Build a fully qualified EuPlatesc payment URL (GET form data) using
            // the existing EpPay implementation and official EuPlatesc docs.
            $data = $this->epPay->getPostData($incrementId);
            $baseUrl = rtrim($this->epPay->getGateUrl(), '?');

            return $baseUrl . '?' . http_build_query($data);
        } catch (LocalizedException $exception) {
            // Restore quote and bubble up localized exceptions so Hyvä can present the message
            $this->checkoutSession->restoreQuote();
            throw $exception;
        } catch (\Throwable $exception) {
            // Fallback: show a generic error and send the shopper back to the cart
            $this->messageManager->addErrorMessage(__('Something went wrong, please try again.'));
            $this->checkoutSession->restoreQuote();
            return $this->url->getUrl('checkout/cart');
        }
    }
}
