<?php

declare(strict_types=1);

namespace Loki\QuoteAddressExtended\Test\Unit\ViewModel\Adminhtml;

use Loki\QuoteAddressExtended\Model\QuoteAddressExtended;
use Loki\QuoteAddressExtended\Repository\QuoteAddressExtendedRepository;
use Loki\QuoteAddressExtended\Util\AddressResolver;
use Loki\QuoteAddressExtended\ViewModel\Adminhtml\OrderAddressExtendedView;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\CreditmemoRepositoryInterface;
use Magento\Sales\Api\InvoiceRepositoryInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Api\ShipmentRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Address as OrderAddress;
use Magento\Sales\Model\Order\Creditmemo;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Shipment;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class OrderAddressExtendedViewTest extends TestCase
{
    private QuoteAddressExtendedRepository&MockObject $quoteAddressExtendedRepository;
    private RequestInterface&MockObject $request;
    private OrderRepositoryInterface&MockObject $orderRepository;
    private InvoiceRepositoryInterface&MockObject $invoiceRepository;
    private ShipmentRepositoryInterface&MockObject $shipmentRepository;
    private CreditmemoRepositoryInterface&MockObject $creditmemoRepository;
    private OrderAddress $shippingAddress;
    private OrderAddress $billingAddress;

    protected function setUp(): void
    {
        $this->quoteAddressExtendedRepository = $this->createMock(QuoteAddressExtendedRepository::class);
        $this->request = $this->createMock(RequestInterface::class);
        $this->orderRepository = $this->createMock(OrderRepositoryInterface::class);
        $this->invoiceRepository = $this->createMock(InvoiceRepositoryInterface::class);
        $this->shipmentRepository = $this->createMock(ShipmentRepositoryInterface::class);
        $this->creditmemoRepository = $this->createMock(CreditmemoRepositoryInterface::class);
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

    public function testReturnsValueOfOrderFromShipmentId(): void
    {
        $this->mockRequestParams(['shipment_id' => 7, 'order_id' => 99]);
        $shipment = $this->createMock(Shipment::class);
        $shipment->method('getOrderId')->willReturn('5');
        $this->shipmentRepository->method('get')->with(7)->willReturn($shipment);
        $this->orderRepository->expects($this->once())->method('get')->with(5)->willReturn($this->createOrder(true));
        $this->mockRows();

        $this->assertSame('billing value', $this->createViewModel()->getValue('example_address_field', 'billing'));
    }

    public function testReturnsValueOfOrderFromCreditmemoId(): void
    {
        $this->mockRequestParams(['creditmemo_id' => 8, 'order_id' => 99]);
        $creditmemo = $this->createMock(Creditmemo::class);
        $creditmemo->method('getOrderId')->willReturn('5');
        $this->creditmemoRepository->method('get')->with(8)->willReturn($creditmemo);
        $this->orderRepository->expects($this->once())->method('get')->with(5)->willReturn($this->createOrder(true));
        $this->mockRows();

        $this->assertSame('shipping value', $this->createViewModel()->getValue('example_address_field'));
    }

    public function testReturnsEmptyStringForUnknownShipment(): void
    {
        $this->shipmentRepository->method('get')->willThrowException(new NoSuchEntityException(__('Not found')));
        $this->quoteAddressExtendedRepository->expects($this->never())->method('getByOrderAddress');

        $this->mockRequestParams(['shipment_id' => 7]);
        $this->assertSame('', $this->createViewModel()->getValue('example_address_field'));
    }

    public function testReturnsEmptyStringForUnknownCreditmemo(): void
    {
        $this->creditmemoRepository->method('get')->willThrowException(new NoSuchEntityException(__('Not found')));
        $this->quoteAddressExtendedRepository->expects($this->never())->method('getByOrderAddress');

        $this->mockRequestParams(['creditmemo_id' => 8]);
        $this->assertSame('', $this->createViewModel()->getValue('example_address_field'));
    }

    public function testUsesOnlyEntityParamOfCurrentPage(): void
    {
        $request = $this->createMock(HttpRequest::class);
        $request->method('getFullActionName')->willReturn('sales_order_creditmemo_view');
        $request->method('getParam')->willReturnCallback(
            fn (string $name) => ['invoice_id' => 9, 'creditmemo_id' => 8][$name] ?? null
        );
        $this->request = $request;
        $creditmemo = $this->createMock(Creditmemo::class);
        $creditmemo->method('getOrderId')->willReturn('5');
        $this->creditmemoRepository->method('get')->with(8)->willReturn($creditmemo);
        $this->invoiceRepository->expects($this->never())->method('get');
        $this->orderRepository->method('get')->with(5)->willReturn($this->createOrder(true));
        $this->mockRows();

        $this->assertSame('shipping value', $this->createViewModel()->getValue('example_address_field'));
    }

    public function testIgnoresEntityParamsOnOrderPage(): void
    {
        $request = $this->createMock(HttpRequest::class);
        $request->method('getFullActionName')->willReturn('sales_order_view');
        $request->method('getParam')->willReturnCallback(
            fn (string $name) => ['invoice_id' => 9, 'order_id' => 5][$name] ?? null
        );
        $this->request = $request;
        $this->invoiceRepository->expects($this->never())->method('get');
        $this->orderRepository->method('get')->with(5)->willReturn($this->createOrder(true));
        $this->mockRows();

        $this->assertSame('billing value', $this->createViewModel()->getValue('example_address_field', 'billing'));
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
            $this->invoiceRepository,
            $this->shipmentRepository,
            $this->creditmemoRepository
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
