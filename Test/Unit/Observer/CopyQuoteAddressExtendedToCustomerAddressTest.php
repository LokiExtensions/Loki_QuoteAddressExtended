<?php

declare(strict_types=1);

namespace Loki\QuoteAddressExtended\Test\Unit\Observer;

use Loki\QuoteAddressExtended\Model\CustomerAddressExtended;
use Loki\QuoteAddressExtended\Model\QuoteAddressExtended;
use Loki\QuoteAddressExtended\Observer\CopyQuoteAddressExtendedToCustomerAddress;
use Loki\QuoteAddressExtended\Repository\CustomerAddressExtendedRepository;
use Loki\QuoteAddressExtended\Repository\QuoteAddressExtendedRepository;
use Loki\QuoteAddressExtended\Util\ExtendedDataCopier;
use Magento\Framework\DataObject;
use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address as QuoteAddress;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class CopyQuoteAddressExtendedToCustomerAddressTest extends TestCase
{
    private CustomerAddressExtendedRepository&MockObject $customerAddressExtendedRepository;
    private QuoteAddressExtendedRepository&MockObject $quoteAddressExtendedRepository;
    private ExtendedDataCopier&MockObject $extendedDataCopier;
    private LoggerInterface&MockObject $logger;

    protected function setUp(): void
    {
        $this->customerAddressExtendedRepository = $this->createMock(CustomerAddressExtendedRepository::class);
        $this->quoteAddressExtendedRepository = $this->createMock(QuoteAddressExtendedRepository::class);
        $this->extendedDataCopier = $this->createMock(ExtendedDataCopier::class);
        $this->logger = $this->createMock(LoggerInterface::class);
    }

    public function testCopiesEveryQuoteAddressToItsCustomerAddress(): void
    {
        $shippingRow = $this->createQuoteAddressExtended(['entity_id' => 1]);
        $billingRow = $this->createQuoteAddressExtended(['entity_id' => 2]);
        $shippingCustomerRow = $this->createCustomerAddressExtended(['customer_address_id' => 5]);
        $billingCustomerRow = $this->createCustomerAddressExtended(['customer_address_id' => 6]);

        $this->extendedDataCopier->method('getSharedColumns')->willReturn(['example_address_field']);
        $this->quoteAddressExtendedRepository->method('getByQuoteAddressId')
            ->willReturnMap([[41, $shippingRow], [42, $billingRow]]);
        $this->customerAddressExtendedRepository->method('getOrCreateByCustomerAddressId')
            ->willReturnMap([[5, $shippingCustomerRow], [6, $billingCustomerRow]]);
        $this->extendedDataCopier->expects($this->exactly(2))->method('copyQuoteToCustomerAddressValues')
            ->willReturnMap([[$shippingRow, $shippingCustomerRow, true], [$billingRow, $billingCustomerRow, true]]);

        $savedRows = [];
        $this->customerAddressExtendedRepository->expects($this->exactly(2))->method('save')
            ->willReturnCallback(function (CustomerAddressExtended $row) use (&$savedRows) {
                $savedRows[] = $row;
                return $row;
            });
        $this->logger->expects($this->never())->method('error');

        $this->createObserver()->execute($this->createEventObserver($this->createQuote([
            $this->createQuoteAddress(41, 5, true),
            $this->createQuoteAddress(42, 6, true),
        ])));

        $this->assertSame([$shippingCustomerRow, $billingCustomerRow], $savedRows);
    }

    public function testSkipsSaveWhenNothingWasCopied(): void
    {
        $this->extendedDataCopier->method('getSharedColumns')->willReturn(['example_address_field']);
        $this->quoteAddressExtendedRepository->method('getByQuoteAddressId')
            ->willReturn($this->createQuoteAddressExtended(['entity_id' => 1]));
        $this->customerAddressExtendedRepository->method('getOrCreateByCustomerAddressId')
            ->willReturn($this->createCustomerAddressExtended([]));
        $this->extendedDataCopier->method('copyQuoteToCustomerAddressValues')->willReturn(false);
        $this->customerAddressExtendedRepository->expects($this->never())->method('save');

        $this->createObserver()->execute($this->createEventObserver($this->createQuote([
            $this->createQuoteAddress(41, 5, true),
        ])));
    }

    public function testSkipsMissingQuoteRow(): void
    {
        $this->extendedDataCopier->method('getSharedColumns')->willReturn(['example_address_field']);
        $this->quoteAddressExtendedRepository->method('getByQuoteAddressId')
            ->willThrowException(new NoSuchEntityException(__('Not found')));
        $this->customerAddressExtendedRepository->expects($this->never())->method('getOrCreateByCustomerAddressId');
        $this->customerAddressExtendedRepository->expects($this->never())->method('save');

        $this->createObserver()->execute($this->createEventObserver($this->createQuote([
            $this->createQuoteAddress(41, 5, true),
        ])));
    }

    public function testSkipsAddressesNotEligibleForCopying(): void
    {
        $this->extendedDataCopier->method('getSharedColumns')->willReturn(['example_address_field']);
        $this->quoteAddressExtendedRepository->method('getByQuoteAddressId')
            ->willReturn($this->createQuoteAddressExtended(['entity_id' => 1]));
        $this->customerAddressExtendedRepository->method('getOrCreateByCustomerAddressId')
            ->willReturn($this->createCustomerAddressExtended(['entity_id' => 9, 'customer_address_id' => 5]));
        $this->extendedDataCopier->expects($this->never())->method('copyQuoteToCustomerAddressValues');
        $this->customerAddressExtendedRepository->expects($this->never())->method('save');

        $this->createObserver()->execute($this->createEventObserver($this->createQuote([
            $this->createQuoteAddress(41, 0, true),
            $this->createQuoteAddress(0, 5, true),
            $this->createQuoteAddress(42, 5, false),
        ])));
    }

    public function testCopiesToNewCustomerRowWithoutSaveInAddressBook(): void
    {
        $customerRow = $this->createCustomerAddressExtended(['customer_address_id' => 5]);
        $this->extendedDataCopier->method('getSharedColumns')->willReturn(['example_address_field']);
        $this->quoteAddressExtendedRepository->method('getByQuoteAddressId')
            ->willReturn($this->createQuoteAddressExtended(['entity_id' => 1]));
        $this->customerAddressExtendedRepository->method('getOrCreateByCustomerAddressId')->willReturn($customerRow);
        $this->extendedDataCopier->method('copyQuoteToCustomerAddressValues')->willReturn(true);
        $this->customerAddressExtendedRepository->expects($this->once())->method('save')->with($customerRow);

        $this->createObserver()->execute($this->createEventObserver($this->createQuote([
            $this->createQuoteAddress(42, 5, false),
        ])));
    }

    public function testUsesShippingRowForBillingAddressWhenSameAsBilling(): void
    {
        $shippingRow = $this->createQuoteAddressExtended(['entity_id' => 1]);
        $customerRow = $this->createCustomerAddressExtended(['customer_address_id' => 6]);
        $shippingAddress = $this->createQuoteAddress(41, 0, false, 'shipping');
        $shippingAddress->setData('same_as_billing', 1);
        $billingAddress = $this->createQuoteAddress(42, 6, true, 'billing');

        $this->extendedDataCopier->method('getSharedColumns')->willReturn(['example_address_field']);
        $this->quoteAddressExtendedRepository->method('getByQuoteAddressId')->willReturnCallback(
            fn (int $id) => $id === 41 ? $shippingRow : throw new NoSuchEntityException(__('Not found'))
        );
        $this->customerAddressExtendedRepository->method('getOrCreateByCustomerAddressId')->with(6)
            ->willReturn($customerRow);
        $this->extendedDataCopier->expects($this->once())->method('copyQuoteToCustomerAddressValues')
            ->with($shippingRow, $customerRow)->willReturn(true);
        $this->customerAddressExtendedRepository->expects($this->once())->method('save')->with($customerRow);

        $this->createObserver()->execute($this->createEventObserver(
            $this->createQuote([$shippingAddress, $billingAddress], $shippingAddress)
        ));
    }

    public function testSkipsGuestQuote(): void
    {
        $this->extendedDataCopier->expects($this->never())->method('getSharedColumns');

        $this->createObserver()->execute($this->createEventObserver(
            $this->createQuote([$this->createQuoteAddress(41, 5, true)], null, 0)
        ));
    }

    public function testSkipsWithoutSharedColumns(): void
    {
        $this->extendedDataCopier->method('getSharedColumns')->willReturn([]);
        $this->quoteAddressExtendedRepository->expects($this->never())->method('getByQuoteAddressId');

        $quote = $this->createQuoteMock(3);
        $quote->expects($this->never())->method('getAllAddresses');

        $this->createObserver()->execute($this->createEventObserver($quote));
    }

    public function testDoesNothingWithoutQuote(): void
    {
        $this->extendedDataCopier->expects($this->never())->method('getSharedColumns');

        $this->createObserver()->execute($this->createEventObserver(new DataObject()));
    }

    public function testLogsSaveFailure(): void
    {
        $this->extendedDataCopier->method('getSharedColumns')->willReturn(['example_address_field']);
        $this->quoteAddressExtendedRepository->method('getByQuoteAddressId')
            ->willReturn($this->createQuoteAddressExtended(['entity_id' => 1]));
        $this->customerAddressExtendedRepository->method('getOrCreateByCustomerAddressId')
            ->willReturn($this->createCustomerAddressExtended([]));
        $this->extendedDataCopier->method('copyQuoteToCustomerAddressValues')->willReturn(true);
        $this->customerAddressExtendedRepository->method('save')
            ->willThrowException(new CouldNotSaveException(__('Failure')));
        $this->logger->expects($this->once())->method('error')->with(
            'Loki_QuoteAddressExtended: Could not copy extended quote address data to the customer address',
            ['customer_address_id' => 5, 'quote_address_id' => 41, 'exception' => 'Failure']
        );

        $this->createObserver()->execute($this->createEventObserver($this->createQuote([
            $this->createQuoteAddress(41, 5, true),
        ])));
    }

    private function createQuoteMock(int $customerId): Quote&MockObject
    {
        $quote = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getAllAddresses', 'isVirtual', 'getShippingAddress'])
            ->getMock();
        $quote->setData('customer_id', $customerId ?: null);

        return $quote;
    }

    private function createQuote(
        array $quoteAddresses,
        ?QuoteAddress $shippingAddress = null,
        int $customerId = 3
    ): Quote
    {
        $quote = $this->createQuoteMock($customerId);
        $quote->method('getAllAddresses')->willReturn($quoteAddresses);
        $quote->method('isVirtual')->willReturn(null === $shippingAddress);
        $quote->method('getShippingAddress')->willReturn($shippingAddress);

        return $quote;
    }

    private function createQuoteAddress(
        int $quoteAddressId,
        int $customerAddressId,
        bool $saveInAddressBook,
        string $addressType = 'shipping'
    ): QuoteAddress {
        $quoteAddress = $this->getMockBuilder(QuoteAddress::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();
        $quoteAddress->setData([
            'id' => $quoteAddressId ?: null,
            'customer_address_id' => $customerAddressId ?: null,
            'save_in_address_book' => (int)$saveInAddressBook,
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

    private function createObserver(): CopyQuoteAddressExtendedToCustomerAddress
    {
        return new CopyQuoteAddressExtendedToCustomerAddress(
            $this->customerAddressExtendedRepository,
            $this->quoteAddressExtendedRepository,
            $this->extendedDataCopier,
            $this->logger
        );
    }

    private function createEventObserver(object $quote): Observer
    {
        return new Observer(['event' => new Event(['quote' => $quote])]);
    }
}
