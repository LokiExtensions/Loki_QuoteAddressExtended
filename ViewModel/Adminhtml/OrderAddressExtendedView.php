<?php

declare(strict_types=1);

namespace Loki\QuoteAddressExtended\ViewModel\Adminhtml;

use Loki\QuoteAddressExtended\Model\QuoteAddressExtended;
use Loki\QuoteAddressExtended\Repository\QuoteAddressExtendedRepository;
use Loki\QuoteAddressExtended\Util\AddressResolver;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Sales\Api\CreditmemoRepositoryInterface;
use Magento\Sales\Api\InvoiceRepositoryInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Api\ShipmentRepositoryInterface;
use Magento\Sales\Model\Order;

class OrderAddressExtendedView implements ArgumentInterface
{
    private const ENTITY_PARAMS = [
        'sales_order_invoice_view' => 'invoice_id',
        'adminhtml_order_shipment_view' => 'shipment_id',
        'sales_order_creditmemo_view' => 'creditmemo_id',
    ];

    private array $quoteAddressExtended = [];

    public function __construct(
        private readonly QuoteAddressExtendedRepository $quoteAddressExtendedRepository,
        private readonly AddressResolver $addressResolver,
        private readonly RequestInterface $request,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly InvoiceRepositoryInterface $invoiceRepository,
        private readonly ShipmentRepositoryInterface $shipmentRepository,
        private readonly CreditmemoRepositoryInterface $creditmemoRepository
    ) {
    }

    public function getValue(string $column, string $addressType = AddressResolver::TYPE_AUTO): string
    {
        $quoteAddressExtended = $this->getQuoteAddressExtended($addressType);
        if (null === $quoteAddressExtended) {
            return '';
        }

        return (string)$quoteAddressExtended->getData($column);
    }

    private function getQuoteAddressExtended(string $addressType): ?QuoteAddressExtended
    {
        if (array_key_exists($addressType, $this->quoteAddressExtended)) {
            return $this->quoteAddressExtended[$addressType];
        }

        $this->quoteAddressExtended[$addressType] = $this->loadQuoteAddressExtended($addressType);

        return $this->quoteAddressExtended[$addressType];
    }

    private function loadQuoteAddressExtended(string $addressType): ?QuoteAddressExtended
    {
        $order = $this->getOrder();
        if (null === $order) {
            return null;
        }

        $orderAddress = $this->addressResolver->getOrderAddress($order, $addressType);
        if (null === $orderAddress) {
            return null;
        }

        try {
            return $this->quoteAddressExtendedRepository->getByOrderAddress($orderAddress);
        } catch (NoSuchEntityException) {
            return null;
        }
    }

    private function getOrder(): ?Order
    {
        try {
            $order = $this->loadOrder();
        } catch (NoSuchEntityException) {
            return null;
        }

        return $order instanceof Order ? $order : null;
    }

    private function loadOrder(): mixed
    {
        $entityParam = $this->getEntityParam();

        $invoiceId = $this->getIdParam('invoice_id', $entityParam);
        if ($invoiceId > 0) {
            return $this->invoiceRepository->get($invoiceId)->getOrder();
        }

        $shipmentId = $this->getIdParam('shipment_id', $entityParam);
        if ($shipmentId > 0) {
            return $this->orderRepository->get((int)$this->shipmentRepository->get($shipmentId)->getOrderId());
        }

        $creditmemoId = $this->getIdParam('creditmemo_id', $entityParam);
        if ($creditmemoId > 0) {
            return $this->orderRepository->get((int)$this->creditmemoRepository->get($creditmemoId)->getOrderId());
        }

        $orderId = (int)$this->request->getParam('order_id');
        if ($orderId > 0) {
            return $this->orderRepository->get($orderId);
        }

        return null;
    }

    private function getIdParam(string $param, ?string $entityParam): int
    {
        if ($entityParam !== null && $entityParam !== $param) {
            return 0;
        }

        return (int)$this->request->getParam($param);
    }

    private function getEntityParam(): ?string
    {
        if (false === $this->request instanceof HttpRequest) {
            return null;
        }

        return self::ENTITY_PARAMS[$this->request->getFullActionName()] ?? '';
    }
}
