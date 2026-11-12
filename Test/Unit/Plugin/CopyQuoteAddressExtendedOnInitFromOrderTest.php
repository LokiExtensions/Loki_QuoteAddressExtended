<?php

declare(strict_types=1);

namespace Loki\QuoteAddressExtended\Test\Unit\Plugin;

use Loki\QuoteAddressExtended\Model\QuoteAddressExtended;
use Loki\QuoteAddressExtended\Plugin\CopyQuoteAddressExtendedOnInitFromOrder;
use Loki\QuoteAddressExtended\Repository\QuoteAddressExtendedRepository;
use Loki\QuoteAddressExtended\Util\AddressResolver;
use Loki\QuoteAddressExtended\Util\ExtendedDataCopier;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address as QuoteAddress;
use Magento\Sales\Model\AdminOrder\Create;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Address as OrderAddress;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class CopyQuoteAddressExtendedOnInitFromOrderTest extends TestCase
{
    private QuoteAddressExtendedRepository&MockObject $quoteAddressExtendedRepository;
    private ExtendedDataCopier&MockObject $extendedDataCopier;
    private LoggerInterface&MockObject $logger;

    protected function setUp(): void
    {
        $this->quoteAddressExtendedRepository = $this->createMock(QuoteAddressExtendedRepository::class);
        $this->extendedDataCopier = $this->createMock(ExtendedDataCopier::class);
        $this->logger = $this->createMock(LoggerInterface::class);
    }

    public function testCopiesOrderAddressRowsToQuoteAddresses(): void
    {
        $shippingOrderAddress = $this->createOrderAddress(7);
        $billingOrderAddress = $this->createOrderAddress(8);
        $shippingSource = $this->createQuoteAddressExtended(['entity_id' => 1, 'quote_address_id' => 11]);
        $billingSource = $this->createQuoteAddressExtended(['entity_id' => 2, 'quote_address_id' => 12]);
        $shippingTarget = $this->createQuoteAddressExtended(['quote_address_id' => 21]);
        $billingTarget = $this->createQuoteAddressExtended(['quote_address_id' => 22]);

        $this->quoteAddressExtendedRepository->method('getByOrderAddress')
            ->willReturnMap([[$shippingOrderAddress, $shippingSource], [$billingOrderAddress, $billingSource]]);
        $this->quoteAddressExtendedRepository->method('getOrCreateByQuoteAddressId')
            ->willReturnMap([[21, $shippingTarget], [22, $billingTarget]]);
        $this->extendedDataCopier->expects($this->exactly(2))->method('copyOrderToQuoteAddressValues')
            ->willReturnMap([[$shippingSource, $shippingTarget, true], [$billingSource, $billingTarget, true]]);

        $savedRows = [];
        $this->quoteAddressExtendedRepository->expects($this->exactly(2))->method('save')
            ->willReturnCallback(function (QuoteAddressExtended $row) use (&$savedRows) {
                $savedRows[] = $row;
                return $row;
            });
        $this->logger->expects($this->never())->method('error');

        $result = $this->createPlugin()->afterInitFromOrder(
            $this->createSubject(21, 22),
            'result',
            $this->createOrder($shippingOrderAddress, $billingOrderAddress)
        );

        $this->assertSame('result', $result);
        $this->assertSame([$shippingTarget, $billingTarget], $savedRows);
        $this->assertSame('shipping', $shippingTarget->getAddressType());
        $this->assertSame('billing', $billingTarget->getAddressType());
    }

    public function testSkipsMissingOrderAddress(): void
    {
        $billingOrderAddress = $this->createOrderAddress(8);

        $this->quoteAddressExtendedRepository->expects($this->once())->method('getByOrderAddress')
            ->with($billingOrderAddress)
            ->willThrowException(new NoSuchEntityException(__('Not found')));
        $this->quoteAddressExtendedRepository->expects($this->never())->method('save');

        $result = $this->createPlugin()->afterInitFromOrder(
            $this->createSubject(21, 22),
            null,
            $this->createOrder(null, $billingOrderAddress)
        );

        $this->assertNull($result);
    }

    public function testSkipsQuoteAddressWithoutId(): void
    {
        $this->quoteAddressExtendedRepository->expects($this->never())->method('getByOrderAddress');

        $this->createPlugin()->afterInitFromOrder(
            $this->createSubject(0, 0),
            true,
            $this->createOrder($this->createOrderAddress(7), $this->createOrderAddress(8))
        );
    }

    public function testSkipsSourceRowAlreadyLinkedToQuoteAddress(): void
    {
        $this->quoteAddressExtendedRepository->method('getByOrderAddress')
            ->willReturn($this->createQuoteAddressExtended(['entity_id' => 1, 'quote_address_id' => 21]));
        $this->quoteAddressExtendedRepository->expects($this->once())->method('getOrCreateByQuoteAddressId')
            ->with(22)->willReturn($this->createQuoteAddressExtended([]));
        $this->extendedDataCopier->method('copyOrderToQuoteAddressValues')->willReturn(false);
        $this->quoteAddressExtendedRepository->expects($this->never())->method('save');

        $this->createPlugin()->afterInitFromOrder(
            $this->createSubject(21, 22),
            true,
            $this->createOrder($this->createOrderAddress(7), $this->createOrderAddress(8))
        );
    }

    public function testSkipsSaveWhenNothingWasCopied(): void
    {
        $target = $this->createQuoteAddressExtended([]);

        $this->quoteAddressExtendedRepository->method('getByOrderAddress')
            ->willReturn($this->createQuoteAddressExtended(['entity_id' => 1, 'quote_address_id' => 11]));
        $this->quoteAddressExtendedRepository->method('getOrCreateByQuoteAddressId')->willReturn($target);
        $this->extendedDataCopier->method('copyOrderToQuoteAddressValues')->willReturn(false);
        $this->quoteAddressExtendedRepository->expects($this->never())->method('save');

        $this->createPlugin()->afterInitFromOrder(
            $this->createSubject(21, 22),
            true,
            $this->createOrder($this->createOrderAddress(7), $this->createOrderAddress(8))
        );

        $this->assertSame('', $target->getAddressType());
    }

    public function testLogsSaveFailure(): void
    {
        $this->quoteAddressExtendedRepository->method('getByOrderAddress')
            ->willReturn($this->createQuoteAddressExtended(['entity_id' => 1, 'quote_address_id' => 11]));
        $this->quoteAddressExtendedRepository->method('getOrCreateByQuoteAddressId')
            ->willReturn($this->createQuoteAddressExtended([]));
        $this->extendedDataCopier->method('copyOrderToQuoteAddressValues')->willReturn(true);
        $this->quoteAddressExtendedRepository->method('save')
            ->willThrowException(new CouldNotSaveException(__('Failure')));
        $this->logger->expects($this->once())->method('error')->with(
            'Loki_QuoteAddressExtended: Could not copy extended order address data to the admin quote',
            ['order_address_id' => 7, 'quote_address_id' => 21, 'exception' => 'Failure']
        );

        $this->createPlugin()->afterInitFromOrder(
            $this->createSubject(21, 22),
            true,
            $this->createOrder($this->createOrderAddress(7), null)
        );
    }

    private function createSubject(int $shippingAddressId, int $billingAddressId): Create
    {
        $quote = $this->createMock(Quote::class);
        $quote->method('getShippingAddress')->willReturn($this->createQuoteAddress($shippingAddressId));
        $quote->method('getBillingAddress')->willReturn($this->createQuoteAddress($billingAddressId));

        $subject = $this->createMock(Create::class);
        $subject->method('getQuote')->willReturn($quote);

        return $subject;
    }

    private function createOrder(?OrderAddress $shippingAddress, ?OrderAddress $billingAddress): Order
    {
        $order = $this->createMock(Order::class);
        $order->method('getShippingAddress')->willReturn($shippingAddress);
        $order->method('getBillingAddress')->willReturn($billingAddress);

        return $order;
    }

    private function createQuoteAddress(int $quoteAddressId): QuoteAddress
    {
        $quoteAddress = $this->getMockBuilder(QuoteAddress::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();
        $quoteAddress->setData(['id' => $quoteAddressId ?: null]);

        return $quoteAddress;
    }

    private function createOrderAddress(int $orderAddressId): OrderAddress
    {
        $orderAddress = $this->getMockBuilder(OrderAddress::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();
        $orderAddress->setData(['entity_id' => $orderAddressId]);

        return $orderAddress;
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

    private function createPlugin(): CopyQuoteAddressExtendedOnInitFromOrder
    {
        return new CopyQuoteAddressExtendedOnInitFromOrder(
            $this->quoteAddressExtendedRepository,
            new AddressResolver(),
            $this->extendedDataCopier,
            $this->logger
        );
    }
}
