<?php

declare(strict_types=1);

namespace Loki\QuoteAddressExtended\Observer;

use Exception;
use Loki\QuoteAddressExtended\Repository\QuoteAddressExtendedRepository;
use Loki\QuoteAddressExtended\Util\AddressResolver;
use Loki\QuoteAddressExtended\Util\CustomerAddressOwnership;
use Loki\QuoteAddressExtended\Util\ExtendedDataCopier;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address as QuoteAddress;
use Magento\Quote\Model\ResourceModel\Quote\Address as QuoteAddressResource;
use Psr\Log\LoggerInterface;

class CopyQuoteAddressExtendedOnQuoteMerge implements ObserverInterface
{
    public function __construct(
        private readonly QuoteAddressExtendedRepository $quoteAddressExtendedRepository,
        private readonly AddressResolver $addressResolver,
        private readonly ExtendedDataCopier $extendedDataCopier,
        private readonly CustomerAddressOwnership $customerAddressOwnership,
        private readonly QuoteAddressResource $quoteAddressResource,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(Observer $observer): void
    {
        $quote = $observer->getEvent()->getData('quote');
        $source = $observer->getEvent()->getData('source');
        if (false === $quote instanceof Quote || false === $source instanceof Quote) {
            return;
        }

        foreach ([AddressResolver::TYPE_SHIPPING, AddressResolver::TYPE_BILLING] as $addressType) {
            $this->copyAddress($source, $quote, $addressType);
        }
    }

    private function copyAddress(Quote $source, Quote $target, string $addressType): void
    {
        $sourceAddress = $this->addressResolver->getQuoteAddress($source, $addressType);
        $sourceAddressId = (int)$sourceAddress->getId();
        if ($sourceAddressId < 1) {
            return;
        }

        try {
            $sourceRow = $this->quoteAddressExtendedRepository->getByQuoteAddressId($sourceAddressId);
        } catch (NoSuchEntityException) {
            return;
        }

        $targetAddress = $this->addressResolver->getQuoteAddress($target, $addressType);
        if ((int)$targetAddress->getId() === $sourceAddressId || (int)$target->getId() < 1) {
            return;
        }

        if (false === $this->isSameOrEmptyAddress($sourceAddress, $targetAddress, (int)$target->getCustomerId())) {
            return;
        }

        try {
            if (!$targetAddress->getId()) {
                $this->quoteAddressResource->save($targetAddress);
            }

            $targetRow = $this->quoteAddressExtendedRepository->getOrCreateByQuoteAddressId(
                (int)$targetAddress->getId()
            );
            if (false === $this->extendedDataCopier->copyQuoteAddressValues($sourceRow, $targetRow)) {
                return;
            }

            $targetRow->setAddressType($addressType);
            $this->quoteAddressExtendedRepository->save($targetRow);
        } catch (Exception $exception) {
            $this->logger->error(
                'Loki_QuoteAddressExtended: Could not copy extended quote address data on quote merge',
                ['quote_id' => $target->getId(), 'exception' => $exception->getMessage()]
            );
        }
    }

    private function isSameOrEmptyAddress(
        QuoteAddress $sourceAddress,
        QuoteAddress $targetAddress,
        int $customerId
    ): bool {
        $targetCustomerAddressId = (int)$targetAddress->getCustomerAddressId();
        if ($targetCustomerAddressId > 0) {
            return $targetCustomerAddressId === (int)$sourceAddress->getCustomerAddressId()
                && $this->customerAddressOwnership->isOwnedBy($targetCustomerAddressId, $customerId);
        }

        return [] === array_filter((array)$targetAddress->getStreet()) && !$targetAddress->getPostcode();
    }
}
