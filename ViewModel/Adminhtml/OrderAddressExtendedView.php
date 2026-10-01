<?php

declare(strict_types=1);

namespace Loki\QuoteAddressExtended\ViewModel\Adminhtml;

use Loki\QuoteAddressExtended\Model\QuoteAddressExtended;
use Loki\QuoteAddressExtended\Repository\QuoteAddressExtendedRepository;
use Loki\QuoteAddressExtended\Util\AddressResolver;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Sales\Api\InvoiceRepositoryInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;

class OrderAddressExtendedView implements ArgumentInterface
{
    private array $quoteAddressExtended = [];

    public function __construct(
        private readonly QuoteAddressExtendedRepository $quoteAddressExtendedRepository,
        private readonly AddressResolver $addressResolver,
        private readonly RequestInterface $request,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly InvoiceRepositoryInterface $invoiceRepository
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
            $invoiceId = (int)$this->request->getParam('invoice_id');
            if ($invoiceId > 0) {
                $order = $this->invoiceRepository->get($invoiceId)->getOrder();
                return $order instanceof Order ? $order : null;
            }

            $orderId = (int)$this->request->getParam('order_id');
            if ($orderId > 0) {
                $order = $this->orderRepository->get($orderId);
                return $order instanceof Order ? $order : null;
            }
        } catch (NoSuchEntityException) {
            return null;
        }

        return null;
    }
}
