<?php

declare(strict_types=1);

namespace Loki\QuoteAddressExtended\Observer;

use Loki\QuoteAddressExtended\Repository\QuoteAddressExtendedRepository;
use Magento\Framework\DataObject;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Address as OrderAddress;

class AddQuoteAddressExtendedToEmailVars implements ObserverInterface
{
    public const SHIPPING_TEMPLATE_VARIABLE = 'loki_shipping_address_extended';
    public const BILLING_TEMPLATE_VARIABLE = 'loki_billing_address_extended';

    public function __construct(
        private readonly QuoteAddressExtendedRepository $quoteAddressExtendedRepository
    ) {
    }

    public function execute(Observer $observer): void
    {
        $transport = $observer->getEvent()->getData('transportObject');
        if (false === $transport instanceof DataObject) {
            return;
        }

        $order = $transport->getData('order');
        if (false === $order instanceof Order) {
            return;
        }

        $this->addAddress($transport, self::SHIPPING_TEMPLATE_VARIABLE, $order->getShippingAddress());
        $this->addAddress($transport, self::BILLING_TEMPLATE_VARIABLE, $order->getBillingAddress());
    }

    private function addAddress(DataObject $transport, string $variable, ?OrderAddress $orderAddress): void
    {
        if (null === $orderAddress) {
            return;
        }

        try {
            $quoteAddressExtended = $this->quoteAddressExtendedRepository->getByOrderAddress($orderAddress);
        } catch (NoSuchEntityException) {
            return;
        }

        $transport->setData($variable, $quoteAddressExtended);
    }
}
