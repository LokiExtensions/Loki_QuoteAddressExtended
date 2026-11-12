<?php

declare(strict_types=1);

namespace Loki\QuoteAddressExtended\Test\Integration\Trait;

use Loki\QuoteAddressExtended\Model\CustomerAddressExtended;
use Loki\QuoteAddressExtended\Model\QuoteAddressExtended;
use Loki\QuoteAddressExtended\Model\ResourceModel\QuoteAddressExtended as QuoteAddressExtendedResource;
use Loki\QuoteAddressExtended\Repository\CustomerAddressExtendedRepository;
use Loki\QuoteAddressExtended\Repository\QuoteAddressExtendedRepository;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\PaymentMethodManagementInterface;
use Magento\Quote\Model\Quote;
use Magento\Sales\Api\CreditmemoRepositoryInterface;
use Magento\Sales\Api\Data\CreditmemoInterface;
use Magento\Sales\Api\Data\InvoiceInterface;
use Magento\Sales\Api\Data\ShipmentInterface;
use Magento\Sales\Api\InvoiceOrderInterface;
use Magento\Sales\Api\InvoiceRepositoryInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Api\RefundOrderInterface;
use Magento\Sales\Api\ShipmentRepositoryInterface;
use Magento\Sales\Api\ShipOrderInterface;
use Magento\Sales\Model\Order;
use Magento\TestFramework\Helper\Bootstrap;

trait QuoteAddressExtendedTestTrait
{
    protected function getQuoteAddressExtendedRepository(): QuoteAddressExtendedRepository
    {
        return $this->om()->get(QuoteAddressExtendedRepository::class);
    }

    protected function saveQuoteAddressExtended(int $quoteAddressId, array $data = []): QuoteAddressExtended
    {
        $repository = $this->getQuoteAddressExtendedRepository();
        $quoteAddressExtended = $repository->getOrCreateByQuoteAddressId($quoteAddressId);
        foreach ($data as $key => $value) {
            $quoteAddressExtended->setData($key, $value);
        }

        return $repository->save($quoteAddressExtended);
    }

    protected function getCustomerAddressExtendedRepository(): CustomerAddressExtendedRepository
    {
        return $this->om()->get(CustomerAddressExtendedRepository::class);
    }

    protected function saveCustomerAddressExtended(int $customerAddressId, array $data = []): CustomerAddressExtended
    {
        $repository = $this->getCustomerAddressExtendedRepository();
        $customerAddressExtended = $repository->getOrCreateByCustomerAddressId($customerAddressId);
        foreach ($data as $key => $value) {
            $customerAddressExtended->setData($key, $value);
        }

        return $repository->save($customerAddressExtended);
    }

    protected function loadQuoteAddressExtended(int $entityId): QuoteAddressExtended
    {
        $quoteAddressExtended = $this->om()->create(QuoteAddressExtended::class);
        $this->om()->get(QuoteAddressExtendedResource::class)->load($quoteAddressExtended, $entityId);

        return $quoteAddressExtended;
    }

    protected function getQuote(int $cartId): Quote
    {
        $quote = $this->om()->create(CartRepositoryInterface::class)->get($cartId);
        $this->assertInstanceOf(Quote::class, $quote);

        return $quote;
    }

    protected function getShippingAddressId(int $cartId): int
    {
        $quoteAddressId = (int)$this->getQuote($cartId)->getShippingAddress()->getId();
        $this->assertGreaterThan(0, $quoteAddressId);

        return $quoteAddressId;
    }

    protected function getBillingAddressId(int $cartId): int
    {
        $quoteAddressId = (int)$this->getQuote($cartId)->getBillingAddress()->getId();
        $this->assertGreaterThan(0, $quoteAddressId);

        return $quoteAddressId;
    }

    protected function getCustomerAddressIds(int $customerId): array
    {
        $customer = $this->om()->create(CustomerRepositoryInterface::class)->getById($customerId);
        $addressIds = [];
        foreach ((array)$customer->getAddresses() as $address) {
            $addressIds[] = (int)$address->getId();
        }

        return $addressIds;
    }

    protected function placeOrder(int $cartId): Order
    {
        $paymentMethod = $this->om()->get(PaymentMethodManagementInterface::class)->get($cartId);
        $orderId = (int)$this->om()->get(CartManagementInterface::class)->placeOrder($cartId, $paymentMethod);

        $order = $this->om()->create(OrderRepositoryInterface::class)->get($orderId);
        $this->assertInstanceOf(Order::class, $order);

        return $order;
    }

    protected function restoreQuote(int $cartId): void
    {
        $cartRepository = $this->om()->create(CartRepositoryInterface::class);
        $quote = $cartRepository->get($cartId);
        $quote->setIsActive(true);
        $quote->setReservedOrderId(null);
        $cartRepository->save($quote);
    }

    protected function createInvoice(Order $order): InvoiceInterface
    {
        $invoiceId = $this->om()->get(InvoiceOrderInterface::class)->execute((int)$order->getEntityId());

        return $this->om()->create(InvoiceRepositoryInterface::class)->get((int)$invoiceId);
    }

    protected function createShipment(Order $order): ShipmentInterface
    {
        $shipmentId = $this->om()->get(ShipOrderInterface::class)->execute((int)$order->getEntityId());

        return $this->om()->create(ShipmentRepositoryInterface::class)->get((int)$shipmentId);
    }

    protected function createCreditmemo(Order $order): CreditmemoInterface
    {
        $creditmemoId = $this->om()->get(RefundOrderInterface::class)->execute((int)$order->getEntityId());

        return $this->om()->create(CreditmemoRepositoryInterface::class)->get((int)$creditmemoId);
    }

    private function om(): ObjectManagerInterface
    {
        return Bootstrap::getObjectManager();
    }
}
