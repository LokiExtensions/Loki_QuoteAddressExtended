<?php

declare(strict_types=1);

namespace Loki\QuoteAddressExtended\Test\Unit\Observer;

use Loki\QuoteAddressExtended\Model\QuoteAddressExtended;
use Loki\QuoteAddressExtended\Observer\CopyQuoteAddressExtendedOnQuoteMerge;
use Loki\QuoteAddressExtended\Repository\QuoteAddressExtendedRepository;
use Loki\QuoteAddressExtended\Util\AddressResolver;
use Loki\QuoteAddressExtended\Util\CustomerAddressOwnership;
use Loki\QuoteAddressExtended\Util\ExtendedDataCopier;
use Magento\Framework\DataObject;
use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address as QuoteAddress;
use Magento\Quote\Model\ResourceModel\Quote\Address as QuoteAddressResource;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class CopyQuoteAddressExtendedOnQuoteMergeTest extends TestCase
{
    private QuoteAddressExtendedRepository&MockObject $quoteAddressExtendedRepository;
    private ExtendedDataCopier&MockObject $extendedDataCopier;
    private CustomerAddressOwnership&MockObject $customerAddressOwnership;
    private bool $isOwned = true;
    private QuoteAddressResource&MockObject $quoteAddressResource;
    private LoggerInterface&MockObject $logger;

    protected function setUp(): void
    {
        $this->quoteAddressExtendedRepository = $this->createMock(QuoteAddressExtendedRepository::class);
        $this->extendedDataCopier = $this->createMock(ExtendedDataCopier::class);
        $this->customerAddressOwnership = $this->createMock(CustomerAddressOwnership::class);
        $this->customerAddressOwnership->method('isOwnedBy')->willReturnCallback(fn () => $this->isOwned);
        $this->quoteAddressResource = $this->createMock(QuoteAddressResource::class);
        $this->logger = $this->createMock(LoggerInterface::class);
    }

    public function testCopiesShippingAndBillingRowsToTargetQuote(): void
    {
        $sourceShipping = $this->createQuoteAddressExtended(['entity_id' => 1, 'quote_address_id' => 11]);
        $sourceBilling = $this->createQuoteAddressExtended(['entity_id' => 2, 'quote_address_id' => 12]);
        $targetShipping = $this->createQuoteAddressExtended(['quote_address_id' => 21]);
        $targetBilling = $this->createQuoteAddressExtended(['quote_address_id' => 22]);

        $this->quoteAddressExtendedRepository->method('getByQuoteAddressId')
            ->willReturnMap([[11, $sourceShipping], [12, $sourceBilling]]);
        $this->quoteAddressExtendedRepository->method('getOrCreateByQuoteAddressId')
            ->willReturnMap([[21, $targetShipping], [22, $targetBilling]]);
        $this->extendedDataCopier->expects($this->exactly(2))->method('copyQuoteAddressValues')
            ->willReturnMap([[$sourceShipping, $targetShipping, true], [$sourceBilling, $targetBilling, true]]);

        $savedRows = [];
        $this->quoteAddressExtendedRepository->expects($this->exactly(2))->method('save')
            ->willReturnCallback(function (QuoteAddressExtended $row) use (&$savedRows) {
                $savedRows[] = $row;
                return $row;
            });
        $this->quoteAddressResource->expects($this->never())->method('save');
        $this->logger->expects($this->never())->method('error');

        $this->createObserver()->execute($this->createEventObserver(
            $this->createQuote(1, $this->createQuoteAddress(21), $this->createQuoteAddress(22)),
            $this->createQuote(2, $this->createQuoteAddress(11), $this->createQuoteAddress(12))
        ));

        $this->assertSame([$targetShipping, $targetBilling], $savedRows);
        $this->assertSame('shipping', $targetShipping->getAddressType());
        $this->assertSame('billing', $targetBilling->getAddressType());
    }

    public function testSkipsFilledInTargetAddress(): void
    {
        $this->quoteAddressExtendedRepository->method('getByQuoteAddressId')
            ->willReturn($this->createQuoteAddressExtended(['entity_id' => 1]));
        $this->quoteAddressExtendedRepository->expects($this->never())->method('getOrCreateByQuoteAddressId');
        $this->quoteAddressExtendedRepository->expects($this->never())->method('save');

        $targetShipping = $this->createQuoteAddress(21);
        $targetShipping->setData('street', 'Main street 1');
        $targetBilling = $this->createQuoteAddress(22);
        $targetBilling->setData('postcode', '1234 AB');

        $this->createObserver()->execute($this->createEventObserver(
            $this->createQuote(1, $targetShipping, $targetBilling),
            $this->createQuote(2, $this->createQuoteAddress(11), $this->createQuoteAddress(12))
        ));
    }

    public function testCopiesOnlyToTargetWithSameCustomerAddress(): void
    {
        $sourceRow = $this->createQuoteAddressExtended(['entity_id' => 1]);
        $targetRow = $this->createQuoteAddressExtended(['quote_address_id' => 21]);
        $this->quoteAddressExtendedRepository->method('getByQuoteAddressId')->willReturn($sourceRow);
        $this->quoteAddressExtendedRepository->expects($this->once())->method('getOrCreateByQuoteAddressId')
            ->with(21)->willReturn($targetRow);
        $this->extendedDataCopier->method('copyQuoteAddressValues')->willReturn(true);
        $this->quoteAddressExtendedRepository->expects($this->once())->method('save')->with($targetRow);

        $sourceShipping = $this->createQuoteAddress(11);
        $sourceShipping->setData('customer_address_id', 5);
        $sourceBilling = $this->createQuoteAddress(12);
        $sourceBilling->setData('customer_address_id', 5);
        $targetShipping = $this->createQuoteAddress(21);
        $targetShipping->addData(['customer_address_id' => 5, 'street' => 'Main street 1']);
        $targetBilling = $this->createQuoteAddress(22);
        $targetBilling->setData('customer_address_id', 6);

        $this->createObserver()->execute($this->createEventObserver(
            $this->createQuote(1, $targetShipping, $targetBilling),
            $this->createQuote(2, $sourceShipping, $sourceBilling)
        ));
    }

    public function testSkipsSameCustomerAddressOfAnotherCustomer(): void
    {
        $this->isOwned = false;
        $this->quoteAddressExtendedRepository->method('getByQuoteAddressId')
            ->willReturn($this->createQuoteAddressExtended(['entity_id' => 1]));
        $this->quoteAddressExtendedRepository->expects($this->never())->method('getOrCreateByQuoteAddressId');
        $this->quoteAddressExtendedRepository->expects($this->never())->method('save');

        $sourceShipping = $this->createQuoteAddress(11);
        $sourceShipping->setData('customer_address_id', 5);
        $targetShipping = $this->createQuoteAddress(21);
        $targetShipping->setData('customer_address_id', 5);

        $this->createObserver()->execute($this->createEventObserver(
            $this->createQuote(1, $targetShipping, $this->createQuoteAddress(22)),
            $this->createQuote(2, $sourceShipping, $this->createQuoteAddress(0))
        ));
    }

    public function testSavesTargetAddressWithoutId(): void
    {
        $sourceRow = $this->createQuoteAddressExtended(['entity_id' => 1, 'quote_address_id' => 11]);
        $targetRow = $this->createQuoteAddressExtended(['quote_address_id' => 21]);
        $targetAddress = $this->createQuoteAddress(0);

        $this->quoteAddressExtendedRepository->method('getByQuoteAddressId')->willReturnCallback(
            fn (int $id) => $id === 11 ? $sourceRow : throw new NoSuchEntityException(__('Not found'))
        );
        $this->quoteAddressResource->expects($this->once())->method('save')->with($targetAddress)
            ->willReturnCallback(function (QuoteAddress $address) {
                $address->setData('id', 21);
                return $this->quoteAddressResource;
            });
        $this->quoteAddressExtendedRepository->expects($this->once())->method('getOrCreateByQuoteAddressId')
            ->with(21)->willReturn($targetRow);
        $this->extendedDataCopier->method('copyQuoteAddressValues')->willReturn(true);
        $this->quoteAddressExtendedRepository->expects($this->once())->method('save')->with($targetRow);

        $this->createObserver()->execute($this->createEventObserver(
            $this->createQuote(1, $targetAddress, $this->createQuoteAddress(22)),
            $this->createQuote(2, $this->createQuoteAddress(11), $this->createQuoteAddress(12))
        ));

        $this->assertSame('shipping', $targetRow->getAddressType());
    }

    public function testSkipsSaveWhenNothingWasCopied(): void
    {
        $sourceRow = $this->createQuoteAddressExtended(['entity_id' => 1, 'quote_address_id' => 11]);
        $targetRow = $this->createQuoteAddressExtended(['quote_address_id' => 21]);

        $this->quoteAddressExtendedRepository->method('getByQuoteAddressId')->willReturn($sourceRow);
        $this->quoteAddressExtendedRepository->method('getOrCreateByQuoteAddressId')->willReturn($targetRow);
        $this->extendedDataCopier->method('copyQuoteAddressValues')->willReturn(false);
        $this->quoteAddressExtendedRepository->expects($this->never())->method('save');

        $this->createObserver()->execute($this->createEventObserver(
            $this->createQuote(1, $this->createQuoteAddress(21), $this->createQuoteAddress(22)),
            $this->createQuote(2, $this->createQuoteAddress(11), $this->createQuoteAddress(12))
        ));

        $this->assertSame('', $targetRow->getAddressType());
    }

    public function testSkipsSourceAddressWithoutId(): void
    {
        $this->quoteAddressExtendedRepository->expects($this->never())->method('getByQuoteAddressId');
        $this->quoteAddressExtendedRepository->expects($this->never())->method('save');

        $this->createObserver()->execute($this->createEventObserver(
            $this->createQuote(1, $this->createQuoteAddress(21), $this->createQuoteAddress(22)),
            $this->createQuote(2, $this->createQuoteAddress(0), $this->createQuoteAddress(0))
        ));
    }

    public function testSkipsSourceAddressWithoutRow(): void
    {
        $this->quoteAddressExtendedRepository->method('getByQuoteAddressId')
            ->willThrowException(new NoSuchEntityException(__('Not found')));
        $this->quoteAddressExtendedRepository->expects($this->never())->method('getOrCreateByQuoteAddressId');
        $this->quoteAddressExtendedRepository->expects($this->never())->method('save');

        $this->createObserver()->execute($this->createEventObserver(
            $this->createQuote(1, $this->createQuoteAddress(21), $this->createQuoteAddress(22)),
            $this->createQuote(2, $this->createQuoteAddress(11), $this->createQuoteAddress(12))
        ));
    }

    public function testSkipsTargetAddressIdenticalToSourceAddress(): void
    {
        $this->quoteAddressExtendedRepository->method('getByQuoteAddressId')
            ->willReturn($this->createQuoteAddressExtended(['entity_id' => 1]));
        $this->quoteAddressExtendedRepository->expects($this->never())->method('getOrCreateByQuoteAddressId');
        $this->quoteAddressResource->expects($this->never())->method('save');

        $this->createObserver()->execute($this->createEventObserver(
            $this->createQuote(1, $this->createQuoteAddress(11), $this->createQuoteAddress(12)),
            $this->createQuote(2, $this->createQuoteAddress(11), $this->createQuoteAddress(12))
        ));
    }

    public function testSkipsTargetQuoteWithoutId(): void
    {
        $this->quoteAddressExtendedRepository->method('getByQuoteAddressId')
            ->willReturn($this->createQuoteAddressExtended(['entity_id' => 1]));
        $this->quoteAddressExtendedRepository->expects($this->never())->method('getOrCreateByQuoteAddressId');
        $this->quoteAddressResource->expects($this->never())->method('save');

        $this->createObserver()->execute($this->createEventObserver(
            $this->createQuote(0, $this->createQuoteAddress(0), $this->createQuoteAddress(0)),
            $this->createQuote(2, $this->createQuoteAddress(11), $this->createQuoteAddress(12))
        ));
    }

    public function testDoesNothingWithoutQuote(): void
    {
        $this->quoteAddressExtendedRepository->expects($this->never())->method('getByQuoteAddressId');

        $this->createObserver()->execute($this->createEventObserver(
            new DataObject(),
            $this->createQuote(2, $this->createQuoteAddress(11), $this->createQuoteAddress(12))
        ));
    }

    public function testDoesNothingWithoutSource(): void
    {
        $this->quoteAddressExtendedRepository->expects($this->never())->method('getByQuoteAddressId');

        $this->createObserver()->execute($this->createEventObserver(
            $this->createQuote(1, $this->createQuoteAddress(21), $this->createQuoteAddress(22)),
            null
        ));
    }

    public function testLogsSaveFailure(): void
    {
        $this->quoteAddressExtendedRepository->method('getByQuoteAddressId')
            ->willReturn($this->createQuoteAddressExtended(['entity_id' => 1]));
        $this->quoteAddressExtendedRepository->method('getOrCreateByQuoteAddressId')
            ->willReturn($this->createQuoteAddressExtended([]));
        $this->extendedDataCopier->method('copyQuoteAddressValues')->willReturn(true);
        $this->quoteAddressExtendedRepository->method('save')
            ->willThrowException(new CouldNotSaveException(__('Failure')));
        $this->logger->expects($this->exactly(2))->method('error')->with(
            'Loki_QuoteAddressExtended: Could not copy extended quote address data on quote merge',
            $this->callback(fn (array $context) => array_diff_key($context, ['exception' => true])
                === ['quote_id' => 1]
                && $context['exception'] instanceof \Exception
                && $context['exception']->getMessage() === 'Failure')
        );

        $this->createObserver()->execute($this->createEventObserver(
            $this->createQuote(1, $this->createQuoteAddress(21), $this->createQuoteAddress(22)),
            $this->createQuote(2, $this->createQuoteAddress(11), $this->createQuoteAddress(12))
        ));
    }

    public function testLogsQuoteAddressSaveFailure(): void
    {
        $this->quoteAddressExtendedRepository->method('getByQuoteAddressId')
            ->willReturn($this->createQuoteAddressExtended(['entity_id' => 1]));
        $this->quoteAddressResource->method('save')->willThrowException(new \Exception('Database error'));
        $this->quoteAddressExtendedRepository->expects($this->never())->method('getOrCreateByQuoteAddressId');
        $this->logger->expects($this->exactly(2))->method('error')->with(
            'Loki_QuoteAddressExtended: Could not copy extended quote address data on quote merge',
            $this->callback(fn (array $context) => array_diff_key($context, ['exception' => true])
                === ['quote_id' => 1]
                && $context['exception'] instanceof \Exception
                && $context['exception']->getMessage() === 'Database error')
        );

        $this->createObserver()->execute($this->createEventObserver(
            $this->createQuote(1, $this->createQuoteAddress(0), $this->createQuoteAddress(0)),
            $this->createQuote(2, $this->createQuoteAddress(11), $this->createQuoteAddress(12))
        ));
    }

    private function createQuote(int $quoteId, QuoteAddress $shippingAddress, QuoteAddress $billingAddress): Quote
    {
        $quote = $this->createMock(Quote::class);
        $quote->method('getId')->willReturn($quoteId);
        $quote->method('getShippingAddress')->willReturn($shippingAddress);
        $quote->method('getBillingAddress')->willReturn($billingAddress);

        return $quote;
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

    private function createQuoteAddressExtended(array $data): QuoteAddressExtended
    {
        $quoteAddressExtended = $this->getMockBuilder(QuoteAddressExtended::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();
        $quoteAddressExtended->setData($data);

        return $quoteAddressExtended;
    }

    private function createObserver(): CopyQuoteAddressExtendedOnQuoteMerge
    {
        return new CopyQuoteAddressExtendedOnQuoteMerge(
            $this->quoteAddressExtendedRepository,
            new AddressResolver(),
            $this->extendedDataCopier,
            $this->customerAddressOwnership,
            $this->quoteAddressResource,
            $this->logger
        );
    }

    private function createEventObserver(?object $quote, ?object $source): Observer
    {
        return new Observer(['event' => new Event(['quote' => $quote, 'source' => $source])]);
    }
}
