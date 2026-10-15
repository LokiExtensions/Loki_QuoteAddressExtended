<?php

declare(strict_types=1);

namespace Loki\QuoteAddressExtended\Test\Unit\Component\QuoteAddressExtendedField;

use Loki\Components\Component\ComponentInterface;
use Loki\QuoteAddressExtended\Component\QuoteAddressExtendedField\QuoteAddressExtendedFieldRepository;
use Loki\QuoteAddressExtended\Model\QuoteAddressExtended;
use Loki\QuoteAddressExtended\Repository\QuoteAddressExtendedRepository;
use Loki\QuoteAddressExtended\Util\AddressResolver;
use LokiCheckout\Core\ViewModel\CheckoutState;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Element\AbstractBlock;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address as QuoteAddress;
use Magento\Quote\Model\ResourceModel\Quote\Address as QuoteAddressResource;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class QuoteAddressExtendedFieldRepositoryTest extends TestCase
{
    private QuoteAddressExtendedRepository&MockObject $quoteAddressExtendedRepository;
    private CheckoutState&MockObject $checkoutState;
    private QuoteAddressResource&MockObject $quoteAddressResource;
    private Quote&MockObject $quote;

    protected function setUp(): void
    {
        $this->quoteAddressExtendedRepository = $this->createMock(QuoteAddressExtendedRepository::class);
        $this->checkoutState = $this->createMock(CheckoutState::class);
        $this->quoteAddressResource = $this->createMock(QuoteAddressResource::class);
        $this->quote = $this->createMock(Quote::class);
        $this->checkoutState->method('getQuote')->willReturn($this->quote);
    }

    public function testGetFieldNameUsesLastPartOfComponentName(): void
    {
        $repository = $this->createRepository('loki-checkout.customer.example_address_field');

        $this->assertSame('example_address_field', $repository->getFieldName());
    }

    public function testGetFieldNamePrefersFieldNameArgument(): void
    {
        $repository = $this->createRepository(
            'loki-checkout.customer.example_address_field',
            ['field_name' => 'other']
        );

        $this->assertSame('other', $repository->getFieldName());
    }

    public function testGetAddressTypeDefaultsToAuto(): void
    {
        $repository = $this->createRepository('loki-checkout.customer.example_address_field');

        $this->assertSame('auto', $repository->getAddressType());
    }

    public function testGetAddressTypeUsesBlockArgument(): void
    {
        $repository = $this->createRepository(
            'loki-checkout.customer.example_address_field',
            ['address_type' => 'billing']
        );

        $this->assertSame('billing', $repository->getAddressType());
    }

    public function testGetValueReturnsColumnValueOfShippingAddress(): void
    {
        $this->checkoutState->method('getQuoteId')->willReturn(42);
        $this->quote->method('getShippingAddress')->willReturn($this->createQuoteAddress(11, 'shipping'));
        $quoteAddressExtended = $this->createQuoteAddressExtended(['example_address_field' => 'foobar']);
        $this->quoteAddressExtendedRepository->method('getByQuoteAddressId')->with(11)
            ->willReturn($quoteAddressExtended);

        $repository = $this->createRepository('loki-checkout.customer.example_address_field');

        $this->assertSame('foobar', $repository->getValue());
    }

    public function testGetValueUsesBillingAddressOfVirtualQuote(): void
    {
        $this->checkoutState->method('getQuoteId')->willReturn(42);
        $this->quote->method('isVirtual')->willReturn(true);
        $this->quote->method('getBillingAddress')->willReturn($this->createQuoteAddress(12, 'billing'));
        $quoteAddressExtended = $this->createQuoteAddressExtended(['example_address_field' => 'foobar']);
        $this->quoteAddressExtendedRepository->method('getByQuoteAddressId')->with(12)
            ->willReturn($quoteAddressExtended);

        $repository = $this->createRepository('loki-checkout.customer.example_address_field');

        $this->assertSame('foobar', $repository->getValue());
    }

    public function testGetValueReturnsEmptyStringWithoutRow(): void
    {
        $this->checkoutState->method('getQuoteId')->willReturn(42);
        $this->quote->method('getShippingAddress')->willReturn($this->createQuoteAddress(11, 'shipping'));
        $this->quoteAddressExtendedRepository->method('getByQuoteAddressId')
            ->willThrowException(new NoSuchEntityException(__('Not found')));

        $repository = $this->createRepository('loki-checkout.customer.example_address_field');

        $this->assertSame('', $repository->getValue());
    }

    public function testGetValueReturnsEmptyStringWithoutSavedAddress(): void
    {
        $this->checkoutState->method('getQuoteId')->willReturn(42);
        $this->quote->method('getShippingAddress')->willReturn($this->createQuoteAddress(0, 'shipping'));
        $this->quoteAddressExtendedRepository->expects($this->never())->method('getByQuoteAddressId');

        $repository = $this->createRepository('loki-checkout.customer.example_address_field');

        $this->assertSame('', $repository->getValue());
    }

    public function testSaveValueStoresColumnValueAndAddressType(): void
    {
        $this->checkoutState->method('getQuoteId')->willReturn(42);
        $this->quote->method('getBillingAddress')->willReturn($this->createQuoteAddress(12, 'billing'));
        $quoteAddressExtended = $this->createQuoteAddressExtended(['quote_address_id' => 12]);
        $this->quoteAddressExtendedRepository->method('getOrCreateByQuoteAddressId')->with(12)
            ->willReturn($quoteAddressExtended);
        $this->quoteAddressExtendedRepository->expects($this->once())->method('save')->with($quoteAddressExtended);
        $this->quoteAddressResource->expects($this->never())->method('save');

        $repository = $this->createRepository(
            'loki-checkout.customer.example_address_field',
            ['address_type' => 'billing']
        );
        $repository->saveValue('foobar');

        $this->assertSame('foobar', $quoteAddressExtended->getData('example_address_field'));
        $this->assertSame('billing', $quoteAddressExtended->getAddressType());
    }

    public function testSaveValueSavesQuoteAddressWithoutId(): void
    {
        $this->checkoutState->method('getQuoteId')->willReturn(42);
        $quoteAddress = $this->createQuoteAddress(0, 'shipping');
        $this->quote->method('getShippingAddress')->willReturn($quoteAddress);
        $this->quoteAddressResource->expects($this->once())->method('save')->with($quoteAddress)
            ->willReturnCallback(function (QuoteAddress $quoteAddress) {
                $quoteAddress->setId(13);
                return $this->quoteAddressResource;
            });
        $quoteAddressExtended = $this->createQuoteAddressExtended([]);
        $this->quoteAddressExtendedRepository->method('getOrCreateByQuoteAddressId')->with(13)
            ->willReturn($quoteAddressExtended);
        $this->quoteAddressExtendedRepository->expects($this->once())->method('save');

        $repository = $this->createRepository('loki-checkout.customer.example_address_field');
        $repository->saveValue('foobar');

        $this->assertSame('foobar', $quoteAddressExtended->getData('example_address_field'));
    }

    public function testSaveValueRefusesReservedColumns(): void
    {
        $this->checkoutState->method('getQuoteId')->willReturn(42);
        $this->quoteAddressExtendedRepository->expects($this->never())->method('save');

        $repository = $this->createRepository('loki-checkout.customer.order_address_id');
        $repository->saveValue('1');
    }

    public function testSaveValueStoresNonScalarValueAsNull(): void
    {
        $this->checkoutState->method('getQuoteId')->willReturn(42);
        $this->quote->method('getShippingAddress')->willReturn($this->createQuoteAddress(11, 'shipping'));
        $quoteAddressExtended = $this->createQuoteAddressExtended(['example_address_field' => 'old']);
        $this->quoteAddressExtendedRepository->method('getOrCreateByQuoteAddressId')->with(11)
            ->willReturn($quoteAddressExtended);
        $this->quoteAddressExtendedRepository->expects($this->once())->method('save');

        $repository = $this->createRepository('loki-checkout.customer.example_address_field');
        $repository->saveValue(['foo' => 'bar']);

        $this->assertNull($quoteAddressExtended->getData('example_address_field'));
        $this->assertSame('shipping', $quoteAddressExtended->getAddressType());
    }

    public function testSaveValueUsesFieldNameArgumentAsColumn(): void
    {
        $this->checkoutState->method('getQuoteId')->willReturn(42);
        $this->quote->method('getShippingAddress')->willReturn($this->createQuoteAddress(11, 'shipping'));
        $quoteAddressExtended = $this->createQuoteAddressExtended([]);
        $this->quoteAddressExtendedRepository->method('getOrCreateByQuoteAddressId')
            ->willReturn($quoteAddressExtended);

        $repository = $this->createRepository(
            'loki-checkout.customer.example_address_field',
            ['field_name' => 'other_column']
        );
        $repository->saveValue('foobar');

        $this->assertSame('foobar', $quoteAddressExtended->getData('other_column'));
        $this->assertFalse($quoteAddressExtended->hasData('example_address_field'));
    }

    public function testSaveValueDoesNothingWhenQuoteAddressCannotBeSaved(): void
    {
        $this->checkoutState->method('getQuoteId')->willReturn(42);
        $quoteAddress = $this->createQuoteAddress(0, 'shipping');
        $this->quote->method('getShippingAddress')->willReturn($quoteAddress);
        $this->quoteAddressResource->expects($this->once())->method('save')->with($quoteAddress);
        $this->quoteAddressExtendedRepository->expects($this->never())->method('getOrCreateByQuoteAddressId');
        $this->quoteAddressExtendedRepository->expects($this->never())->method('save');

        $repository = $this->createRepository('loki-checkout.customer.example_address_field');
        $repository->saveValue('foobar');
    }

    public function testGetValueReturnsEmptyStringWithoutQuote(): void
    {
        $this->checkoutState->method('getQuoteId')->willReturn(0);
        $this->checkoutState->expects($this->never())->method('getQuote');
        $this->quoteAddressExtendedRepository->expects($this->never())->method('getByQuoteAddressId');

        $repository = $this->createRepository('loki-checkout.customer.example_address_field');

        $this->assertSame('', $repository->getValue());
    }

    public function testSaveValueDoesNothingWithoutQuote(): void
    {
        $this->checkoutState->method('getQuoteId')->willReturn(0);
        $this->quoteAddressExtendedRepository->expects($this->never())->method('getOrCreateByQuoteAddressId');
        $this->quoteAddressExtendedRepository->expects($this->never())->method('save');

        $repository = $this->createRepository('loki-checkout.customer.example_address_field');
        $repository->saveValue('foobar');
    }

    private function createRepository(string $componentName, array $blockData = []): QuoteAddressExtendedFieldRepository
    {
        $component = $this->createMock(ComponentInterface::class);
        $component->method('getName')->willReturn($componentName);
        $component->method('getBlock')->willReturn($this->createBlock($blockData));

        $repository = new QuoteAddressExtendedFieldRepository(
            $this->quoteAddressExtendedRepository,
            $this->checkoutState,
            new AddressResolver(),
            $this->quoteAddressResource
        );
        $repository->setComponent($component);

        return $repository;
    }

    private function createBlock(array $data): AbstractBlock
    {
        $block = $this->getMockBuilder(AbstractBlock::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();
        $block->setData($data);

        return $block;
    }

    private function createQuoteAddress(int $id, string $addressType): QuoteAddress
    {
        $quoteAddress = $this->getMockBuilder(QuoteAddress::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();
        $quoteAddress->setData('address_type', $addressType);
        if ($id > 0) {
            $quoteAddress->setId($id);
        }

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
}
