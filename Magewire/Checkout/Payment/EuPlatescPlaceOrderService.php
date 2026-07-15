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
use Psr\Log\LoggerInterface;

class EuPlatescPlaceOrderService extends AbstractPlaceOrderService
{
    public function __construct(
        \Magento\Quote\Api\CartManagementInterface $cartManagement,
        private UrlInterface $url,
        private CheckoutSession $checkoutSession,
        private MessageManager $messageManager,
        private OrderRepositoryInterface $orderRepository,
        private EpPay $epPay,
        private LoggerInterface $logger
    ) {
        parent::__construct($cartManagement);
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
            $redirectUrl = $this->epPay->getPostData($incrementId);

            // Do NOT log $redirectUrl — it carries the fp_hash signature and PII
            // (name/email) in the query string. Log only a non-sensitive breadcrumb.
            $this->logger->info('EuPlatesc: Redirect URL generated for order ' . $incrementId);

            return $redirectUrl;
        } catch (LocalizedException $exception) {
            $this->logger->error('EuPlatesc: LocalizedException during redirect', [
                'message' => $exception->getMessage(),
                'order_id' => $orderId
            ]);

            // Restore quote and bubble up localized exceptions so Hyvä can present the message
            $this->checkoutSession->restoreQuote();
            throw $exception;
        } catch (\Throwable $exception) {
            $this->logger->critical('EuPlatesc: Error during redirect generation', [
                'message' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
                'order_id' => $orderId
            ]);

            // Fallback: show a generic error and send the shopper back to the cart
            $this->messageManager->addErrorMessage(__('Something went wrong, please try again.'));
            $this->checkoutSession->restoreQuote();
            return $this->url->getUrl('checkout/cart');
        }
    }
}
