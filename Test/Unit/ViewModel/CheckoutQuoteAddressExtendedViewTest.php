<?php

declare(strict_types=1);

namespace Loki\QuoteAddressExtended\Test\Unit\ViewModel;

use Loki\QuoteAddressExtended\Model\QuoteAddressExtended;
use Loki\QuoteAddressExtended\Repository\QuoteAddressExtendedRepository;
use Loki\QuoteAddressExtended\Util\AddressResolver;
use Loki\QuoteAddressExtended\ViewModel\CheckoutQuoteAddressExtendedView;
use LokiCheckout\Core\ViewModel\CheckoutState;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address as QuoteAddress;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class CheckoutQuoteAddressExtendedViewTest extends TestCase
{
    private QuoteAddressExtendedRepository&MockObject $quoteAddressExtendedRepository;
    private CheckoutState&MockObject $checkoutState;
    private Quote&MockObject $quote;

    protected function setUp(): void
    {
        $this->quoteAddressExtendedRepository = $this->createMock(QuoteAddressExtendedRepository::class);
        $this->checkoutState = $this->createMock(CheckoutState::class);
        $this->quote = $this->createMock(Quote::class);
        $this->checkoutState->method('getQuote')->willReturn($this->quote);
        $this->quote->method('getShippingAddress')->willReturn($this->createQuoteAddress(11));
        $this->quote->method('getBillingAddress')->willReturn($this->createQuoteAddress(12));
    }

    public function testReturnsEmptyStringWithoutQuote(): void
    {
        $this->checkoutState->method('getQuoteId')->willReturn(0);
        $this->checkoutState->expects($this->never())->method('getQuote');
        $this->quoteAddressExtendedRepository->expects($this->never())->method('getByQuoteAddressId');

        $this->assertSame('', $this->createViewModel()->getValue('example_address_field'));
    }

    public function testReturnsEmptyStringWithoutSavedAddress(): void
    {
        $quote = $this->createMock(Quote::class);
        $quote->method('getShippingAddress')->willReturn($this->createQuoteAddress(0));
        $checkoutState = $this->createMock(CheckoutState::class);
        $checkoutState->method('getQuoteId')->willReturn(42);
        $checkoutState->method('getQuote')->willReturn($quote);
        $this->quoteAddressExtendedRepository->expects($this->never())->method('getByQuoteAddressId');

        $viewModel = new CheckoutQuoteAddressExtendedView(
            $this->quoteAddressExtendedRepository,
            $checkoutState,
            new AddressResolver()
        );

        $this->assertSame('', $viewModel->getValue('example_address_field'));
    }

    public function testReturnsEmptyStringWithoutRow(): void
    {
        $this->checkoutState->method('getQuoteId')->willReturn(42);
        $this->quoteAddressExtendedRepository->method('getByQuoteAddressId')
            ->willThrowException(new NoSuchEntityException(__('Not found')));

        $this->assertSame('', $this->createViewModel()->getValue('example_address_field'));
    }

    public function testReturnsValueOfShippingAddressByDefault(): void
    {
        $this->checkoutState->method('getQuoteId')->willReturn(42);
        $this->mockRows();

        $this->assertSame('shipping value', $this->createViewModel()->getValue('example_address_field'));
    }

    public function testReturnsValueOfRequestedAddressType(): void
    {
        $this->checkoutState->method('getQuoteId')->willReturn(42);
        $this->mockRows();

        $viewModel = $this->createViewModel();
        $this->assertSame('shipping value', $viewModel->getValue('example_address_field', 'shipping'));
        $this->assertSame('billing value', $viewModel->getValue('example_address_field', 'billing'));
    }

    public function testReturnsValueOfBillingAddressForVirtualQuote(): void
    {
        $this->checkoutState->method('getQuoteId')->willReturn(42);
        $this->quote->method('isVirtual')->willReturn(true);
        $this->mockRows();

        $this->assertSame('billing value', $this->createViewModel()->getValue('example_address_field', 'auto'));
    }

    public function testReturnsEmptyStringForUnknownColumn(): void
    {
        $this->checkoutState->method('getQuoteId')->willReturn(42);
        $this->mockRows();

        $this->assertSame('', $this->createViewModel()->getValue('unknown_column'));
    }

    private function mockRows(): void
    {
        $this->quoteAddressExtendedRepository->method('getByQuoteAddressId')->willReturnMap([
            [11, $this->createQuoteAddressExtended(['example_address_field' => 'shipping value'])],
            [12, $this->createQuoteAddressExtended(['example_address_field' => 'billing value'])],
        ]);
    }

    private function createViewModel(): CheckoutQuoteAddressExtendedView
    {
        return new CheckoutQuoteAddressExtendedView(
            $this->quoteAddressExtendedRepository,
            $this->checkoutState,
            new AddressResolver()
        );
    }

    private function createQuoteAddress(int $id): QuoteAddress
    {
        $quoteAddress = $this->getMockBuilder(QuoteAddress::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();
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
