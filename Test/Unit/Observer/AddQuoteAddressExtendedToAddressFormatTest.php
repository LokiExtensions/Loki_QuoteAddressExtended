<?php

declare(strict_types=1);

namespace Loki\QuoteAddressExtended\Test\Unit\Observer;

use Loki\QuoteAddressExtended\Model\QuoteAddressExtended;
use Loki\QuoteAddressExtended\Observer\AddQuoteAddressExtendedToAddressFormat;
use Loki\QuoteAddressExtended\Repository\QuoteAddressExtendedRepository;
use Loki\QuoteAddressExtended\Util\ExtendedDataCopier;
use Magento\Framework\DataObject;
use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Model\Order\Address as OrderAddress;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class AddQuoteAddressExtendedToAddressFormatTest extends TestCase
{
    private QuoteAddressExtendedRepository&MockObject $quoteAddressExtendedRepository;
    private ExtendedDataCopier&MockObject $extendedDataCopier;

    protected function setUp(): void
    {
        $this->quoteAddressExtendedRepository = $this->createMock(QuoteAddressExtendedRepository::class);
        $this->extendedDataCopier = $this->createMock(ExtendedDataCopier::class);
    }

    public function testAddsNonEmptyValuesWithoutOverwritingAddressData(): void
    {
        $address = $this->createOrderAddress(['entity_id' => 7, 'field_c' => 'address_c']);

        $this->extendedDataCopier->method('getQuoteAddressColumns')
            ->willReturn(['field_a', 'field_b', 'field_c', 'field_d']);
        $this->quoteAddressExtendedRepository->method('getByOrderAddress')->with($address)
            ->willReturn($this->createQuoteAddressExtended([
                'field_a' => 'extended_a',
                'field_b' => '',
                'field_c' => 'extended_c',
                'field_e' => 'extended_e',
            ]));

        $this->createObserver()->execute($this->createEventObserver($address));

        $this->assertFalse($address->hasData('field_a'));
        $this->assertSame('address_c', $address->getData('field_c'));
        $this->assertSame(
            ['field_a' => 'extended_a', 'field_b' => '', 'field_d' => ''],
            $address->getData('loki_quote_address_extended_values')
        );
        $this->assertFalse($address->hasDataChanges());
    }

    public function testKeepsValuesWhenAddressIsFormattedTwice(): void
    {
        $address = $this->createOrderAddress(['entity_id' => 7]);
        $this->extendedDataCopier->method('getQuoteAddressColumns')->willReturn(['field_a']);
        $this->quoteAddressExtendedRepository->expects($this->once())->method('getByOrderAddress')
            ->willReturn($this->createQuoteAddressExtended(['field_a' => 'extended_a']));

        $observer = $this->createObserver();
        $observer->execute($this->createEventObserver($address));
        $observer->execute($this->createEventObserver($address));

        $this->assertSame(['field_a' => 'extended_a'], $address->getData('loki_quote_address_extended_values'));
    }

    public function testKeepsDataChangesOfDirtyAddress(): void
    {
        $address = $this->createOrderAddress(['entity_id' => 7]);
        $address->setHasDataChanges(true);
        $this->extendedDataCopier->method('getQuoteAddressColumns')->willReturn([]);

        $this->createObserver()->execute($this->createEventObserver($address));

        $this->assertTrue($address->hasDataChanges());
    }

    public function testSkipsAddressWithoutQuoteAddressExtended(): void
    {
        $address = $this->createOrderAddress(['entity_id' => 7]);

        $this->extendedDataCopier->method('getQuoteAddressColumns')->willReturn(['field_a']);
        $this->quoteAddressExtendedRepository->method('getByOrderAddress')
            ->willThrowException(new NoSuchEntityException(__('Not found')));

        $this->createObserver()->execute($this->createEventObserver($address));

        $this->assertSame(
            ['entity_id' => 7, 'loki_quote_address_extended_values' => ['field_a' => '']],
            $address->getData()
        );
    }

    public function testSkipsWithoutColumns(): void
    {
        $this->extendedDataCopier->method('getQuoteAddressColumns')->willReturn([]);
        $this->quoteAddressExtendedRepository->expects($this->never())->method('getByOrderAddress');

        $this->createObserver()->execute($this->createEventObserver($this->createOrderAddress(['entity_id' => 7])));
    }

    public function testSkipsOrderAddressWithoutEntityId(): void
    {
        $this->extendedDataCopier->expects($this->never())->method('getQuoteAddressColumns');
        $this->quoteAddressExtendedRepository->expects($this->never())->method('getByOrderAddress');

        $this->createObserver()->execute($this->createEventObserver($this->createOrderAddress([])));
    }

    public function testSkipsAddressThatIsNoOrderAddress(): void
    {
        $this->extendedDataCopier->expects($this->never())->method('getQuoteAddressColumns');
        $this->quoteAddressExtendedRepository->expects($this->never())->method('getByOrderAddress');

        $this->createObserver()->execute($this->createEventObserver(new DataObject(['entity_id' => 7])));
    }

    private function createOrderAddress(array $data): OrderAddress
    {
        $orderAddress = $this->getMockBuilder(OrderAddress::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();
        $orderAddress->setData($data);
        $orderAddress->setHasDataChanges(false);

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

    private function createObserver(): AddQuoteAddressExtendedToAddressFormat
    {
        return new AddQuoteAddressExtendedToAddressFormat(
            $this->quoteAddressExtendedRepository,
            $this->extendedDataCopier
        );
    }

    private function createEventObserver(object $address): Observer
    {
        return new Observer(['event' => new Event(['address' => $address])]);
    }
}
