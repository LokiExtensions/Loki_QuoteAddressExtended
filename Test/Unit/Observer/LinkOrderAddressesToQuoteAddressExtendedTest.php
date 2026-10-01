<?php

declare(strict_types=1);

namespace Loki\QuoteAddressExtended\Test\Unit\Observer;

use Loki\QuoteAddressExtended\Model\QuoteAddressExtended;
use Loki\QuoteAddressExtended\Observer\LinkOrderAddressesToQuoteAddressExtended;
use Loki\QuoteAddressExtended\Repository\QuoteAddressExtendedRepository;
use Magento\Framework\DataObject;
use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Address as OrderAddress;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class LinkOrderAddressesToQuoteAddressExtendedTest extends TestCase
{
    private QuoteAddressExtendedRepository&MockObject $quoteAddressExtendedRepository;
    private LoggerInterface&MockObject $logger;

    protected function setUp(): void
    {
        $this->quoteAddressExtendedRepository = $this->createMock(QuoteAddressExtendedRepository::class);
        $this->logger = $this->createMock(LoggerInterface::class);
    }

    public function testLinksEveryOrderAddressToItsQuoteAddressExtended(): void
    {
        $shipping = $this->createQuoteAddressExtended(['quote_address_id' => 41]);
        $billing = $this->createQuoteAddressExtended(['quote_address_id' => 42]);

        $this->quoteAddressExtendedRepository->method('getByQuoteAddressId')
            ->willReturnMap([[41, $shipping], [42, $billing]]);
        $this->quoteAddressExtendedRepository->expects($this->exactly(2))->method('save');

        $order = $this->createOrder([$this->createOrderAddress(7, 41), $this->createOrderAddress(8, 42)]);
        $this->createObserver()->execute($this->createEventObserver($order));

        $this->assertSame(7, $shipping->getOrderAddressId());
        $this->assertSame(8, $billing->getOrderAddressId());
    }

    public function testDetachesRowAlreadyLinkedToOtherOrderAddress(): void
    {
        $quoteAddressExtended = $this->createQuoteAddressExtended(
            ['entity_id' => 1, 'quote_address_id' => 42, 'order_address_id' => 6]
        );
        $copy = $this->createQuoteAddressExtended(['quote_address_id' => 42, 'example_address_field' => 'foobar']);

        $this->quoteAddressExtendedRepository->method('getByQuoteAddressId')->with(42)
            ->willReturn($quoteAddressExtended);
        $this->quoteAddressExtendedRepository->expects($this->once())->method('detachFromQuoteAddress')
            ->with($quoteAddressExtended)->willReturn($copy);
        $this->quoteAddressExtendedRepository->expects($this->once())->method('save')->with($copy);

        $order = $this->createOrder([$this->createOrderAddress(7, 42)]);
        $this->createObserver()->execute($this->createEventObserver($order));

        $this->assertSame(7, $copy->getOrderAddressId());
        $this->assertSame(6, $quoteAddressExtended->getOrderAddressId());
    }

    public function testSkipsRowAlreadyLinkedToSameOrderAddress(): void
    {
        $quoteAddressExtended = $this->createQuoteAddressExtended(
            ['entity_id' => 1, 'quote_address_id' => 42, 'order_address_id' => 7]
        );

        $this->quoteAddressExtendedRepository->method('getByQuoteAddressId')->willReturn($quoteAddressExtended);
        $this->quoteAddressExtendedRepository->expects($this->never())->method('save');

        $order = $this->createOrder([$this->createOrderAddress(7, 42)]);
        $this->createObserver()->execute($this->createEventObserver($order));
    }

    public function testDoesNothingWithoutQuoteAddressExtended(): void
    {
        $this->quoteAddressExtendedRepository->method('getByQuoteAddressId')
            ->willThrowException(new NoSuchEntityException(__('Not found')));
        $this->quoteAddressExtendedRepository->expects($this->never())->method('save');

        $order = $this->createOrder([$this->createOrderAddress(7, 42)]);
        $this->createObserver()->execute($this->createEventObserver($order));
    }

    public function testSkipsOrderAddressWithoutQuoteAddressId(): void
    {
        $this->quoteAddressExtendedRepository->expects($this->never())->method('getByQuoteAddressId');

        $order = $this->createOrder([$this->createOrderAddress(7, 0)]);
        $this->createObserver()->execute($this->createEventObserver($order));
    }

    public function testDoesNothingWithoutOrder(): void
    {
        $this->quoteAddressExtendedRepository->expects($this->never())->method('getByQuoteAddressId');

        $this->createObserver()->execute($this->createEventObserver(new DataObject()));
    }

    public function testLogsSaveFailure(): void
    {
        $quoteAddressExtended = $this->createQuoteAddressExtended(['quote_address_id' => 42]);

        $this->quoteAddressExtendedRepository->method('getByQuoteAddressId')->willReturn($quoteAddressExtended);
        $this->quoteAddressExtendedRepository->method('save')
            ->willThrowException(new CouldNotSaveException(__('Failure')));
        $this->logger->expects($this->once())->method('error');

        $order = $this->createOrder([$this->createOrderAddress(7, 42)]);
        $this->createObserver()->execute($this->createEventObserver($order));
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

    private function createOrderAddress(int $orderAddressId, int $quoteAddressId): OrderAddress
    {
        $orderAddress = $this->getMockBuilder(OrderAddress::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();
        $orderAddress->setData(['entity_id' => $orderAddressId, 'quote_address_id' => $quoteAddressId]);

        return $orderAddress;
    }

    private function createOrder(array $orderAddresses): Order
    {
        $order = $this->createMock(Order::class);
        $order->method('getAddresses')->willReturn($orderAddresses);

        return $order;
    }

    private function createObserver(): LinkOrderAddressesToQuoteAddressExtended
    {
        return new LinkOrderAddressesToQuoteAddressExtended($this->quoteAddressExtendedRepository, $this->logger);
    }

    private function createEventObserver(object $order): Observer
    {
        return new Observer(['event' => new Event(['order' => $order])]);
    }
}
