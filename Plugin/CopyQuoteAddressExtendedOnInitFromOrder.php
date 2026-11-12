<?php

declare(strict_types=1);

namespace Loki\QuoteAddressExtended\Plugin;

use Loki\QuoteAddressExtended\Repository\QuoteAddressExtendedRepository;
use Loki\QuoteAddressExtended\Util\AddressResolver;
use Loki\QuoteAddressExtended\Util\ExtendedDataCopier;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Model\AdminOrder\Create;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Address as OrderAddress;
use Psr\Log\LoggerInterface;

class CopyQuoteAddressExtendedOnInitFromOrder
{
    public function __construct(
        private readonly QuoteAddressExtendedRepository $quoteAddressExtendedRepository,
        private readonly AddressResolver $addressResolver,
        private readonly ExtendedDataCopier $extendedDataCopier,
        private readonly LoggerInterface $logger
    ) {
    }

    public function afterInitFromOrder(Create $subject, mixed $result, Order $order): mixed
    {
        $quote = $subject->getQuote();
        foreach ([AddressResolver::TYPE_SHIPPING, AddressResolver::TYPE_BILLING] as $addressType) {
            $orderAddress = $this->addressResolver->getOrderAddress($order, $addressType);
            if (null === $orderAddress) {
                continue;
            }

            $quoteAddressId = (int)$this->addressResolver->getQuoteAddress($quote, $addressType)->getId();
            if ($quoteAddressId < 1) {
                continue;
            }

            $this->copyAddress($orderAddress, $quoteAddressId, $addressType);
        }

        return $result;
    }

    private function copyAddress(OrderAddress $orderAddress, int $quoteAddressId, string $addressType): void
    {
        try {
            $sourceRow = $this->quoteAddressExtendedRepository->getByOrderAddress($orderAddress);
        } catch (NoSuchEntityException) {
            return;
        }

        if ($sourceRow->getQuoteAddressId() === $quoteAddressId) {
            return;
        }

        try {
            $targetRow = $this->quoteAddressExtendedRepository->getOrCreateByQuoteAddressId($quoteAddressId);
            if (false === $this->extendedDataCopier->copyOrderToQuoteAddressValues($sourceRow, $targetRow)) {
                return;
            }

            $targetRow->setAddressType($addressType);
            $this->quoteAddressExtendedRepository->save($targetRow);
        } catch (CouldNotSaveException $exception) {
            $this->logger->error(
                'Loki_QuoteAddressExtended: Could not copy extended order address data to the admin quote',
                [
                    'order_address_id' => $orderAddress->getEntityId(),
                    'quote_address_id' => $quoteAddressId,
                    'exception' => $exception,
                ]
            );
        }
    }
}
