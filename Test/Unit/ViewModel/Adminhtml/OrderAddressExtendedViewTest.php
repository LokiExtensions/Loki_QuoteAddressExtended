<?php

declare(strict_types=1);

namespace Loki\QuoteAddressExtended\Test\Unit\ViewModel\Adminhtml;

use Loki\QuoteAddressExtended\Model\QuoteAddressExtended;
use Loki\QuoteAddressExtended\Repository\QuoteAddressExtendedRepository;
use Loki\QuoteAddressExtended\Util\AddressResolver;
use Loki\QuoteAddressExtended\ViewModel\Adminhtml\OrderAddressExtendedView;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\InvoiceRepositoryInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Address as OrderAddress;
use Magento\Sales\Model\Order\Invoice;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class OrderAddressExtendedViewTest extends TestCase
{
    private QuoteAddressExtendedRepository&MockObject $quoteAddressExtendedRepository;
    private RequestInterface&MockObject $request;
    private OrderRepositoryInterface&MockObject $orderRepository;
    private InvoiceRepositoryInterface&MockObject $invoiceRepository;
    private OrderAddress $shippingAddress;
    private OrderAddress $billingAddress;

    protected function setUp(): void
    {
        $this->quoteAddressExtendedRepository = $this->createMock(QuoteAddressExtendedRepository::class);
        $this->request = $this->createMock(RequestInterface::class);
        $this->orderRepository = $this->createMock(OrderRepositoryInterface::class);
        $this->invoiceRepository = $this->createMock(InvoiceRepositoryInterface::class);
        $this->shippingAddress = $this->createMock(OrderAddress::class);
        $this->billingAddress = $this->createMock(OrderAddress::class);
    }

    public function testReturnsValueOfOrderFromOrderId(): void
    {
        $this->mockRequestParams(['order_id' => 5]);
        $this->orderRepository->method('get')->with(5)->willReturn($this->createOrder(true));
        $this->mockRows();

        $viewModel = $this->createViewModel();
        $this->assertSame('shipping value', $viewModel->getValue('example_address_field'));
        $this->assertSame('shipping value', $viewModel->getValue('example_address_field', 'shipping'));
        $this->assertSame('billing value', $viewModel->getValue('example_address_field', 'billing'));
    }

    public function testInvoiceIdTakesPrecedenceOverOrderId(): void
    {
        $this->mockRequestParams(['invoice_id' => 9, 'order_id' => 5]);
        $invoice = $this->createMock(Invoice::class);
        $invoice->method('getOrder')->willReturn($this->createOrder(true));
        $this->invoiceRepository->method('get')->with(9)->willReturn($invoice);
        $this->orderRepository->expects($this->never())->method('get');
        $this->mockRows();

        $this->assertSame('shipping value', $this->createViewModel()->getValue('example_address_field'));
    }

    public function testAutoUsesBillingAddressOfVirtualOrder(): void
    {
        $this->mockRequestParams(['order_id' => 5]);
        $this->orderRepository->method('get')->willReturn($this->createOrder(false));
        $this->mockRows();

        $viewModel = $this->createViewModel();
        $this->assertSame('billing value', $viewModel->getValue('example_address_field'));
        $this->assertSame('', $viewModel->getValue('example_address_field', 'shipping'));
    }

    public function testReturnsEmptyStringWithoutRequestParams(): void
    {
        $this->mockRequestParams([]);
        $this->orderRepository->expects($this->never())->method('get');
        $this->invoiceRepository->expects($this->never())->method('get');
        $this->quoteAddressExtendedRepository->expects($this->never())->method('getByOrderAddress');

        $this->assertSame('', $this->createViewModel()->getValue('example_address_field'));
    }

    public function testReturnsEmptyStringForUnknownOrder(): void
    {
        $this->mockRequestParams(['order_id' => 5]);
        $this->orderRepository->method('get')->willThrowException(new NoSuchEntityException(__('Not found')));
        $this->quoteAddressExtendedRepository->expects($this->never())->method('getByOrderAddress');

        $this->assertSame('', $this->createViewModel()->getValue('example_address_field'));
    }

    public function testReturnsEmptyStringForUnknownInvoice(): void
    {
        $this->mockRequestParams(['invoice_id' => 9]);
        $this->invoiceRepository->method('get')->willThrowException(new NoSuchEntityException(__('Not found')));
        $this->quoteAddressExtendedRepository->expects($this->never())->method('getByOrderAddress');

        $this->assertSame('', $this->createViewModel()->getValue('example_address_field'));
    }

    public function testReturnsEmptyStringWithoutRow(): void
    {
        $this->mockRequestParams(['order_id' => 5]);
        $this->orderRepository->method('get')->willReturn($this->createOrder(true));
        $this->quoteAddressExtendedRepository->method('getByOrderAddress')
            ->willThrowException(new NoSuchEntityException(__('Not found')));

        $this->assertSame('', $this->createViewModel()->getValue('example_address_field'));
    }

    public function testCachesRowPerAddressType(): void
    {
        $this->mockRequestParams(['order_id' => 5]);
        $this->orderRepository->expects($this->exactly(2))->method('get')->willReturn($this->createOrder(true));
        $this->quoteAddressExtendedRepository->expects($this->exactly(2))->method('getByOrderAddress')
            ->willReturnMap([
                [$this->shippingAddress, $this->createQuoteAddressExtended(['example_address_field' => 'shipping'])],
                [$this->billingAddress, $this->createQuoteAddressExtended(['example_address_field' => 'billing'])],
            ]);

        $viewModel = $this->createViewModel();
        $viewModel->getValue('example_address_field', 'shipping');
        $viewModel->getValue('address_type', 'shipping');
        $viewModel->getValue('example_address_field', 'billing');
        $viewModel->getValue('address_type', 'billing');
    }

    private function mockRequestParams(array $params): void
    {
        $this->request->method('getParam')->willReturnCallback(
            fn (string $name) => $params[$name] ?? null
        );
    }

    private function mockRows(): void
    {
        $this->quoteAddressExtendedRepository->method('getByOrderAddress')->willReturnMap([
            [$this->shippingAddress, $this->createQuoteAddressExtended(['example_address_field' => 'shipping value'])],
            [$this->billingAddress, $this->createQuoteAddressExtended(['example_address_field' => 'billing value'])],
        ]);
    }

    private function createOrder(bool $hasShippingAddress): Order
    {
        $order = $this->createMock(Order::class);
        $order->method('getShippingAddress')->willReturn($hasShippingAddress ? $this->shippingAddress : null);
        $order->method('getBillingAddress')->willReturn($this->billingAddress);

        return $order;
    }

    private function createViewModel(): OrderAddressExtendedView
    {
        return new OrderAddressExtendedView(
            $this->quoteAddressExtendedRepository,
            new AddressResolver(),
            $this->request,
            $this->orderRepository,
            $this->invoiceRepository
        );
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
