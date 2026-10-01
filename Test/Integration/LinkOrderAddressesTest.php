<?php

declare(strict_types=1);

namespace Loki\QuoteAddressExtended\Test\Integration;

use Loki\QuoteAddressExtended\Observer\AddQuoteAddressExtendedToEmailVars;
use Loki\QuoteAddressExtended\Repository\QuoteAddressExtendedRepository;
use Loki\QuoteAddressExtended\ViewModel\Adminhtml\OrderAddressExtendedView;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\DataObject;
use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\Framework\ObjectManagerInterface;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Model\Quote;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\Quote\Model\GetQuoteByReservedOrderId;
use PHPUnit\Framework\TestCase;

/**
 * @magentoAppArea frontend
 * @magentoAppIsolation enabled
 * @magentoDbIsolation enabled
 */
class LinkOrderAddressesTest extends TestCase
{
    private ObjectManagerInterface $objectManager;
    private QuoteAddressExtendedRepository $repository;

    protected function setUp(): void
    {
        $this->objectManager = Bootstrap::getObjectManager();
        $this->repository = $this->objectManager->get(QuoteAddressExtendedRepository::class);
    }

    /**
     * @magentoDataFixture Magento/Sales/_files/guest_quote_with_addresses.php
     */
    public function testPlacingOrderLinksOrderAddresses(): void
    {
        $quote = $this->createQuote();
        $shippingRowId = $this->createRow((int)$quote->getShippingAddress()->getId(), 'shipping');
        $billingRowId = $this->createRow((int)$quote->getBillingAddress()->getId(), 'billing');

        $order = $this->placeOrder($quote);
        $shippingAddress = $order->getShippingAddress();
        $billingAddress = $order->getBillingAddress();

        $shippingRow = $this->repository->getByOrderAddressId((int)$shippingAddress->getId());
        $this->assertSame($shippingRowId, (int)$shippingRow->getId());
        $this->assertSame((int)$quote->getShippingAddress()->getId(), $shippingRow->getQuoteAddressId());
        $this->assertSame('shipping', $shippingRow->getAddressType());

        $billingRow = $this->repository->getByOrderAddressId((int)$billingAddress->getId());
        $this->assertSame($billingRowId, (int)$billingRow->getId());
        $this->assertSame('billing', $billingRow->getAddressType());

        $this->assertSame($shippingRowId, (int)$this->repository->getByOrderAddress($shippingAddress)->getId());
        $this->assertSame($billingRowId, (int)$this->repository->getByOrderAddress($billingAddress)->getId());
    }

    /**
     * @magentoDataFixture Magento/Sales/_files/guest_quote_with_addresses.php
     */
    public function testValuesAreAvailableForOrderViewAndEmails(): void
    {
        $quote = $this->createQuote();
        $this->createRow((int)$quote->getShippingAddress()->getId(), 'shipping');
        $this->createRow((int)$quote->getBillingAddress()->getId(), 'billing');
        $order = $this->placeOrder($quote);

        $this->objectManager->get(RequestInterface::class)->setParams(['order_id' => $order->getId()]);
        $view = $this->objectManager->create(OrderAddressExtendedView::class);
        $this->assertSame('shipping', $view->getValue('address_type'));
        $this->assertSame('shipping', $view->getValue('address_type', 'shipping'));
        $this->assertSame('billing', $view->getValue('address_type', 'billing'));

        $transport = new DataObject(['order' => $order]);
        $this->objectManager->get(AddQuoteAddressExtendedToEmailVars::class)->execute(
            new Observer(['event' => new Event(['transportObject' => $transport])])
        );
        $this->assertSame('shipping', $transport->getData('loki_shipping_address_extended')->getAddressType());
        $this->assertSame('billing', $transport->getData('loki_billing_address_extended')->getAddressType());
    }

    /**
     * @magentoDataFixture Magento/Sales/_files/guest_quote_with_addresses.php
     */
    public function testOrderWithoutRowsIsNotAffected(): void
    {
        $order = $this->placeOrder($this->createQuote());

        $this->objectManager->get(RequestInterface::class)->setParams(['order_id' => $order->getId()]);
        $view = $this->objectManager->create(OrderAddressExtendedView::class);
        $this->assertSame('', $view->getValue('address_type'));
    }

    private function createRow(int $quoteAddressId, string $addressType): int
    {
        $this->assertGreaterThan(0, $quoteAddressId);
        $row = $this->repository->getOrCreateByQuoteAddressId($quoteAddressId);
        $row->setAddressType($addressType);
        $this->repository->save($row);

        return (int)$row->getId();
    }

    private function createQuote(): Quote
    {
        $quote = $this->objectManager->get(GetQuoteByReservedOrderId::class)->execute('guest_quote');
        $this->assertInstanceOf(Quote::class, $quote);

        return $quote;
    }

    private function placeOrder(Quote $quote): Order
    {
        $orderId = $this->objectManager->get(CartManagementInterface::class)->placeOrder($quote->getId());

        $order = $this->objectManager->get(OrderRepositoryInterface::class)->get((int)$orderId);
        $this->assertInstanceOf(Order::class, $order);

        return $order;
    }
}
