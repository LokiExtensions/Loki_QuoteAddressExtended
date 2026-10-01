<?php

declare(strict_types=1);

namespace Loki\QuoteAddressExtended\Test\Unit\Observer;

use Loki\QuoteAddressExtended\Model\QuoteAddressExtended;
use Loki\QuoteAddressExtended\Observer\AddQuoteAddressExtendedToEmailVars;
use Loki\QuoteAddressExtended\Repository\QuoteAddressExtendedRepository;
use Magento\Framework\DataObject;
use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Address as OrderAddress;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class AddQuoteAddressExtendedToEmailVarsTest extends TestCase
{
    private QuoteAddressExtendedRepository&MockObject $quoteAddressExtendedRepository;

    protected function setUp(): void
    {
        $this->quoteAddressExtendedRepository = $this->createMock(QuoteAddressExtendedRepository::class);
    }

    public function testAddsShippingAndBillingAddressExtendedToTransport(): void
    {
        $shippingAddress = $this->createOrderAddress();
        $billingAddress = $this->createOrderAddress();
        $shipping = $this->createQuoteAddressExtended(['example_address_field' => 'shipping']);
        $billing = $this->createQuoteAddressExtended(['example_address_field' => 'billing']);
        $this->quoteAddressExtendedRepository->method('getByOrderAddress')
            ->willReturnMap([[$shippingAddress, $shipping], [$billingAddress, $billing]]);

        $transport = new DataObject(['order' => $this->createOrder($shippingAddress, $billingAddress)]);
        $this->createObserver()->execute($this->createEventObserver($transport));

        $this->assertSame($shipping, $transport->getData('loki_shipping_address_extended'));
        $this->assertSame($billing, $transport->getData('loki_billing_address_extended'));
    }

    public function testSkipsMissingShippingAddress(): void
    {
        $billingAddress = $this->createOrderAddress();
        $billing = $this->createQuoteAddressExtended(['example_address_field' => 'billing']);
        $this->quoteAddressExtendedRepository->method('getByOrderAddress')->with($billingAddress)
            ->willReturn($billing);

        $transport = new DataObject(['order' => $this->createOrder(null, $billingAddress)]);
        $this->createObserver()->execute($this->createEventObserver($transport));

        $this->assertFalse($transport->hasData('loki_shipping_address_extended'));
        $this->assertSame($billing, $transport->getData('loki_billing_address_extended'));
    }

    public function testSkipsTransportWithoutQuoteAddressExtended(): void
    {
        $this->quoteAddressExtendedRepository->method('getByOrderAddress')
            ->willThrowException(new NoSuchEntityException(__('Not found')));

        $order = $this->createOrder($this->createOrderAddress(), $this->createOrderAddress());
        $transport = new DataObject(['order' => $order]);
        $this->createObserver()->execute($this->createEventObserver($transport));

        $this->assertFalse($transport->hasData('loki_shipping_address_extended'));
        $this->assertFalse($transport->hasData('loki_billing_address_extended'));
    }

    public function testSkipsTransportWithoutOrder(): void
    {
        $this->quoteAddressExtendedRepository->expects($this->never())->method('getByOrderAddress');

        $transport = new DataObject([]);
        $this->createObserver()->execute($this->createEventObserver($transport));

        $this->assertFalse($transport->hasData('loki_shipping_address_extended'));
    }

    private function createOrder(?OrderAddress $shippingAddress, ?OrderAddress $billingAddress): Order
    {
        $order = $this->createMock(Order::class);
        $order->method('getShippingAddress')->willReturn($shippingAddress);
        $order->method('getBillingAddress')->willReturn($billingAddress);

        return $order;
    }

    private function createOrderAddress(): OrderAddress
    {
        return $this->createMock(OrderAddress::class);
    }

    private function createQuoteAddressExtended(array $data): QuoteAddressExtended
    {
        $quoteAddressExtended = $this->getMockBuilder(QuoteAddressExtended::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();
        $quoteAddressExtended->setData($data);

        return $quoteAddressExtended;
    }

    private function createObserver(): AddQuoteAddressExtendedToEmailVars
    {
        return new AddQuoteAddressExtendedToEmailVars($this->quoteAddressExtendedRepository);
    }

    private function createEventObserver(DataObject $transport): Observer
    {
        return new Observer(['event' => new Event(['transportObject' => $transport])]);
    }
}
