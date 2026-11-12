<?php

declare(strict_types=1);

namespace Loki\QuoteAddressExtended\Test\Unit\Observer;

use Loki\QuoteAddressExtended\Model\CustomerAddressExtended;
use Loki\QuoteAddressExtended\Model\QuoteAddressExtended;
use Loki\QuoteAddressExtended\Observer\CopyCustomerAddressExtendedToQuoteAddress;
use Loki\QuoteAddressExtended\Repository\CustomerAddressExtendedRepository;
use Loki\QuoteAddressExtended\Repository\QuoteAddressExtendedRepository;
use Loki\QuoteAddressExtended\Util\CustomerAddressOwnership;
use Loki\QuoteAddressExtended\Util\ExtendedDataCopier;
use Magento\Framework\DataObject;
use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Model\Quote\Address as QuoteAddress;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class CopyCustomerAddressExtendedToQuoteAddressTest extends TestCase
{
    private CustomerAddressExtendedRepository&MockObject $customerAddressExtendedRepository;
    private QuoteAddressExtendedRepository&MockObject $quoteAddressExtendedRepository;
    private ExtendedDataCopier&MockObject $extendedDataCopier;
    private CustomerAddressOwnership&MockObject $customerAddressOwnership;
    private bool $isOwned = true;
    private LoggerInterface&MockObject $logger;

    protected function setUp(): void
    {
        $this->customerAddressExtendedRepository = $this->createMock(CustomerAddressExtendedRepository::class);
        $this->quoteAddressExtendedRepository = $this->createMock(QuoteAddressExtendedRepository::class);
        $this->extendedDataCopier = $this->createMock(ExtendedDataCopier::class);
        $this->customerAddressOwnership = $this->createMock(CustomerAddressOwnership::class);
        $this->customerAddressOwnership->method('isOwnedByQuoteCustomer')->willReturnCallback(fn () => $this->isOwned);
        $this->logger = $this->createMock(LoggerInterface::class);
    }

    public function testCopiesCustomerRowToExistingQuoteRow(): void
    {
        $customerRow = $this->createCustomerAddressExtended(['entity_id' => 1, 'customer_address_id' => 5]);
        $quoteRow = $this->createQuoteAddressExtended(['entity_id' => 2, 'quote_address_id' => 42]);

        $this->extendedDataCopier->method('getSharedColumns')->willReturn(['example_address_field']);
        $this->customerAddressExtendedRepository->method('getByCustomerAddressId')->with(5)->willReturn($customerRow);
        $this->quoteAddressExtendedRepository->method('getByQuoteAddressId')->with(42)->willReturn($quoteRow);
        $this->extendedDataCopier->expects($this->once())->method('copyCustomerToQuoteAddressValues')
            ->with($customerRow, $quoteRow)->willReturn(true);
        $this->quoteAddressExtendedRepository->expects($this->once())->method('save')->with($quoteRow);
        $this->logger->expects($this->never())->method('error');

        $this->createObserver()->execute($this->createEventObserver($this->createQuoteAddress(42, 5, 'billing')));

        $this->assertSame('billing', $quoteRow->getAddressType());
    }

    public function testReplacesValuesWhenSwitchingCustomerAddress(): void
    {
        $customerRow = $this->createCustomerAddressExtended(['entity_id' => 1, 'customer_address_id' => 6]);
        $quoteRow = $this->createQuoteAddressExtended(['entity_id' => 2, 'quote_address_id' => 42]);
        $quoteAddress = $this->createQuoteAddress(42, 6, 'shipping');
        $quoteAddress->setOrigData('customer_address_id', 5);

        $this->extendedDataCopier->method('getSharedColumns')->willReturn(['example_address_field']);
        $this->customerAddressExtendedRepository->method('getByCustomerAddressId')->with(6)->willReturn($customerRow);
        $this->quoteAddressExtendedRepository->method('getByQuoteAddressId')->with(42)->willReturn($quoteRow);
        $this->extendedDataCopier->expects($this->once())->method('copyCustomerToQuoteAddressValues')
            ->with($customerRow, $quoteRow, true)->willReturn(true);
        $this->quoteAddressExtendedRepository->expects($this->once())->method('save')->with($quoteRow);

        $this->createObserver()->execute($this->createEventObserver($quoteAddress));
    }

    public function testClearsValuesWhenSwitchingToCustomerAddressWithoutRow(): void
    {
        $emptyRow = $this->createCustomerAddressExtended([]);
        $quoteRow = $this->createQuoteAddressExtended(['entity_id' => 2, 'quote_address_id' => 42]);
        $quoteAddress = $this->createQuoteAddress(42, 6, 'shipping');
        $quoteAddress->setOrigData('customer_address_id', 5);

        $this->extendedDataCopier->method('getSharedColumns')->willReturn(['example_address_field']);
        $this->customerAddressExtendedRepository->method('getByCustomerAddressId')
            ->willThrowException(new NoSuchEntityException(__('Not found')));
        $this->customerAddressExtendedRepository->method('create')->willReturn($emptyRow);
        $this->quoteAddressExtendedRepository->method('getByQuoteAddressId')->willReturn($quoteRow);
        $this->extendedDataCopier->expects($this->once())->method('copyCustomerToQuoteAddressValues')
            ->with($emptyRow, $quoteRow, true)->willReturn(true);
        $this->quoteAddressExtendedRepository->expects($this->once())->method('save')->with($quoteRow);

        $this->createObserver()->execute($this->createEventObserver($quoteAddress));
    }

    public function testSkipsUnchangedCustomerAddressId(): void
    {
        $quoteAddress = $this->createQuoteAddress(42, 5, 'shipping');
        $quoteAddress->setOrigData('customer_address_id', 5);

        $this->customerAddressExtendedRepository->expects($this->never())->method('getByCustomerAddressId');

        $this->createObserver()->execute($this->createEventObserver($quoteAddress));
    }

    public function testCopiesOnlyOncePerQuoteAddressAndCustomerAddress(): void
    {
        $customerRow = $this->createCustomerAddressExtended(['entity_id' => 1, 'customer_address_id' => 5]);
        $this->extendedDataCopier->method('getSharedColumns')->willReturn(['example_address_field']);
        $this->customerAddressExtendedRepository->expects($this->once())->method('getByCustomerAddressId')
            ->willReturn($customerRow);
        $this->quoteAddressExtendedRepository->method('getByQuoteAddressId')
            ->willReturn($this->createQuoteAddressExtended(['entity_id' => 2, 'quote_address_id' => 42]));
        $this->extendedDataCopier->method('copyCustomerToQuoteAddressValues')->willReturn(true);
        $this->quoteAddressExtendedRepository->expects($this->once())->method('save');

        $observer = $this->createObserver();
        $observer->execute($this->createEventObserver($this->createQuoteAddress(42, 5, 'shipping')));
        $observer->execute($this->createEventObserver($this->createQuoteAddress(42, 5, 'shipping')));
    }

    public function testRetriesWhenNothingWasCopiedBefore(): void
    {
        $this->extendedDataCopier->method('getSharedColumns')->willReturn(['example_address_field']);
        $this->customerAddressExtendedRepository->expects($this->exactly(2))->method('getByCustomerAddressId')
            ->willThrowException(new NoSuchEntityException(__('Not found')));

        $observer = $this->createObserver();
        $observer->execute($this->createEventObserver($this->createQuoteAddress(42, 5, 'shipping')));
        $observer->execute($this->createEventObserver($this->createQuoteAddress(42, 5, 'shipping')));
    }

    public function testSkipsCustomerAddressOfAnotherCustomer(): void
    {
        $this->isOwned = false;
        $this->extendedDataCopier->method('getSharedColumns')->willReturn(['example_address_field']);
        $this->customerAddressExtendedRepository->expects($this->never())->method('getByCustomerAddressId');
        $this->quoteAddressExtendedRepository->expects($this->never())->method('save');

        $this->createObserver()->execute($this->createEventObserver($this->createQuoteAddress(42, 5, 'shipping')));
    }

    public function testCreatesQuoteRowWhenMissing(): void
    {
        $quoteRow = $this->createQuoteAddressExtended([]);

        $this->extendedDataCopier->method('getSharedColumns')->willReturn(['example_address_field']);
        $this->customerAddressExtendedRepository->method('getByCustomerAddressId')
            ->willReturn($this->createCustomerAddressExtended(['entity_id' => 1]));
        $this->quoteAddressExtendedRepository->method('getByQuoteAddressId')
            ->willThrowException(new NoSuchEntityException(__('Not found')));
        $this->quoteAddressExtendedRepository->expects($this->once())->method('create')->willReturn($quoteRow);
        $this->extendedDataCopier->method('copyCustomerToQuoteAddressValues')->willReturn(true);
        $this->quoteAddressExtendedRepository->expects($this->once())->method('save')->with($quoteRow);

        $this->createObserver()->execute($this->createEventObserver($this->createQuoteAddress(42, 5, 'shipping')));

        $this->assertSame(42, $quoteRow->getQuoteAddressId());
        $this->assertSame('shipping', $quoteRow->getAddressType());
    }

    public function testSkipsSaveWhenNothingWasCopied(): void
    {
        $quoteRow = $this->createQuoteAddressExtended(['entity_id' => 2, 'quote_address_id' => 42]);

        $this->extendedDataCopier->method('getSharedColumns')->willReturn(['example_address_field']);
        $this->customerAddressExtendedRepository->method('getByCustomerAddressId')
            ->willReturn($this->createCustomerAddressExtended(['entity_id' => 1]));
        $this->quoteAddressExtendedRepository->method('getByQuoteAddressId')->willReturn($quoteRow);
        $this->extendedDataCopier->method('copyCustomerToQuoteAddressValues')->willReturn(false);
        $this->quoteAddressExtendedRepository->expects($this->never())->method('save');

        $this->createObserver()->execute($this->createEventObserver($this->createQuoteAddress(42, 5, 'shipping')));

        $this->assertSame('', $quoteRow->getAddressType());
    }

    public function testSkipsQuoteRowLinkedToOrderAddress(): void
    {
        $this->extendedDataCopier->method('getSharedColumns')->willReturn(['example_address_field']);
        $this->customerAddressExtendedRepository->method('getByCustomerAddressId')
            ->willReturn($this->createCustomerAddressExtended(['entity_id' => 1]));
        $this->quoteAddressExtendedRepository->method('getByQuoteAddressId')
            ->willReturn($this->createQuoteAddressExtended(['entity_id' => 2, 'order_address_id' => 7]));
        $this->extendedDataCopier->expects($this->never())->method('copyCustomerToQuoteAddressValues');
        $this->quoteAddressExtendedRepository->expects($this->never())->method('save');

        $this->createObserver()->execute($this->createEventObserver($this->createQuoteAddress(42, 5, 'shipping')));
    }

    public function testSkipsMissingCustomerRow(): void
    {
        $this->extendedDataCopier->method('getSharedColumns')->willReturn(['example_address_field']);
        $this->customerAddressExtendedRepository->method('getByCustomerAddressId')
            ->willThrowException(new NoSuchEntityException(__('Not found')));
        $this->quoteAddressExtendedRepository->expects($this->never())->method('getByQuoteAddressId');
        $this->quoteAddressExtendedRepository->expects($this->never())->method('save');

        $this->createObserver()->execute($this->createEventObserver($this->createQuoteAddress(42, 5, 'shipping')));
    }

    public function testSkipsWithoutSharedColumns(): void
    {
        $this->extendedDataCopier->method('getSharedColumns')->willReturn([]);
        $this->customerAddressExtendedRepository->expects($this->never())->method('getByCustomerAddressId');

        $this->createObserver()->execute($this->createEventObserver($this->createQuoteAddress(42, 5, 'shipping')));
    }

    public function testSkipsQuoteAddressWithoutCustomerAddressId(): void
    {
        $this->extendedDataCopier->expects($this->never())->method('getSharedColumns');
        $this->customerAddressExtendedRepository->expects($this->never())->method('getByCustomerAddressId');

        $this->createObserver()->execute($this->createEventObserver($this->createQuoteAddress(42, 0, 'shipping')));
    }

    public function testSkipsQuoteAddressWithoutId(): void
    {
        $this->extendedDataCopier->expects($this->never())->method('getSharedColumns');
        $this->customerAddressExtendedRepository->expects($this->never())->method('getByCustomerAddressId');

        $this->createObserver()->execute($this->createEventObserver($this->createQuoteAddress(0, 5, 'shipping')));
    }

    public function testDoesNothingWithoutQuoteAddress(): void
    {
        $this->extendedDataCopier->expects($this->never())->method('getSharedColumns');

        $this->createObserver()->execute($this->createEventObserver(new DataObject()));
    }

    public function testLogsSaveFailure(): void
    {
        $this->extendedDataCopier->method('getSharedColumns')->willReturn(['example_address_field']);
        $this->customerAddressExtendedRepository->method('getByCustomerAddressId')
            ->willReturn($this->createCustomerAddressExtended(['entity_id' => 1]));
        $this->quoteAddressExtendedRepository->method('getByQuoteAddressId')
            ->willReturn($this->createQuoteAddressExtended(['entity_id' => 2]));
        $this->extendedDataCopier->method('copyCustomerToQuoteAddressValues')->willReturn(true);
        $this->quoteAddressExtendedRepository->method('save')
            ->willThrowException(new CouldNotSaveException(__('Failure')));
        $this->logger->expects($this->once())->method('error')->with(
            'Loki_QuoteAddressExtended: Could not copy extended customer address data to the quote address',
            ['customer_address_id' => 5, 'quote_address_id' => 42, 'exception' => 'Failure']
        );

        $this->createObserver()->execute($this->createEventObserver($this->createQuoteAddress(42, 5, 'shipping')));
    }

    private function createQuoteAddress(int $quoteAddressId, int $customerAddressId, string $addressType): QuoteAddress
    {
        $quoteAddress = $this->getMockBuilder(QuoteAddress::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();
        $quoteAddress->setData([
            'id' => $quoteAddressId ?: null,
            'customer_address_id' => $customerAddressId ?: null,
            'address_type' => $addressType,
        ]);

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

    private function createCustomerAddressExtended(array $data): CustomerAddressExtended
    {
        $customerAddressExtended = $this->getMockBuilder(CustomerAddressExtended::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();
        $customerAddressExtended->setData($data);

        return $customerAddressExtended;
    }

    private function createObserver(): CopyCustomerAddressExtendedToQuoteAddress
    {
        return new CopyCustomerAddressExtendedToQuoteAddress(
            $this->customerAddressExtendedRepository,
            $this->quoteAddressExtendedRepository,
            $this->extendedDataCopier,
            $this->customerAddressOwnership,
            $this->logger
        );
    }

    private function createEventObserver(object $quoteAddress): Observer
    {
        return new Observer(['event' => new Event(['quote_address' => $quoteAddress])]);
    }
}
