<?php

declare(strict_types=1);

namespace Loki\QuoteAddressExtended\Observer;

use Loki\QuoteAddressExtended\Repository\QuoteAddressExtendedRepository;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Address as OrderAddress;
use Psr\Log\LoggerInterface;

class LinkOrderAddressesToQuoteAddressExtended implements ObserverInterface
{
    public function __construct(
        private readonly QuoteAddressExtendedRepository $quoteAddressExtendedRepository,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(Observer $observer): void
    {
        $order = $observer->getEvent()->getOrder();
        if (false === $order instanceof Order) {
            return;
        }

        foreach ($order->getAddresses() as $orderAddress) {
            $this->linkOrderAddress($orderAddress);
        }
    }

    private function linkOrderAddress(OrderAddress $orderAddress): void
    {
        $quoteAddressId = (int)$orderAddress->getData('quote_address_id');
        $orderAddressId = (int)$orderAddress->getEntityId();
        if ($quoteAddressId < 1 || $orderAddressId < 1) {
            return;
        }

        try {
            $quoteAddressExtended = $this->quoteAddressExtendedRepository->getByQuoteAddressId($quoteAddressId);
        } catch (NoSuchEntityException) {
            return;
        }

        try {
            $linkedOrderAddressId = $quoteAddressExtended->getOrderAddressId();
            if ($linkedOrderAddressId === $orderAddressId) {
                return;
            }

            if ($linkedOrderAddressId !== null) {
                $quoteAddressExtended = $this->quoteAddressExtendedRepository->detachFromQuoteAddress(
                    $quoteAddressExtended
                );
            }

            $quoteAddressExtended->setOrderAddressId($orderAddressId);
            $this->quoteAddressExtendedRepository->save($quoteAddressExtended);
        } catch (CouldNotSaveException $exception) {
            $this->logger->error(
                'Loki_QuoteAddressExtended: Could not link order address to extended quote address data',
                [
                    'quote_address_id' => $quoteAddressId,
                    'order_address_id' => $orderAddressId,
                    'exception' => $exception->getMessage(),
                ]
            );
        }
    }
}
