<?php

declare(strict_types=1);

namespace Loki\QuoteAddressExtended\Observer;

use Loki\QuoteAddressExtended\Model\CustomerAddressExtended;
use Loki\QuoteAddressExtended\Model\QuoteAddressExtended;
use Loki\QuoteAddressExtended\Repository\CustomerAddressExtendedRepository;
use Loki\QuoteAddressExtended\Repository\QuoteAddressExtendedRepository;
use Loki\QuoteAddressExtended\Util\CustomerAddressOwnership;
use Loki\QuoteAddressExtended\Util\ExtendedDataCopier;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address as QuoteAddress;
use Psr\Log\LoggerInterface;

class CopyQuoteAddressExtendedToCustomerAddress implements ObserverInterface
{
    public function __construct(
        private readonly CustomerAddressExtendedRepository $customerAddressExtendedRepository,
        private readonly QuoteAddressExtendedRepository $quoteAddressExtendedRepository,
        private readonly ExtendedDataCopier $extendedDataCopier,
        private readonly CustomerAddressOwnership $customerAddressOwnership,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(Observer $observer): void
    {
        $quote = $observer->getEvent()->getData('quote');
        if (false === $quote instanceof Quote || !$quote->getCustomerId()) {
            return;
        }

        if ([] === $this->extendedDataCopier->getSharedColumns()) {
            return;
        }

        $customerId = (int)$quote->getCustomerId();
        $shippingAddress = $quote->isVirtual() ? null : $quote->getShippingAddress();
        foreach ($quote->getAllAddresses() as $quoteAddress) {
            $fallbackAddress = $this->getFallbackAddress($quoteAddress, $shippingAddress);
            $this->copyAddress($quoteAddress, $fallbackAddress, $customerId);
        }
    }

    private function getFallbackAddress(QuoteAddress $quoteAddress, ?QuoteAddress $shippingAddress): ?QuoteAddress
    {
        if (null === $shippingAddress || $quoteAddress->getAddressType() !== QuoteAddress::ADDRESS_TYPE_BILLING) {
            return null;
        }

        return $shippingAddress->getSameAsBilling() ? $shippingAddress : null;
    }

    private function copyAddress(QuoteAddress $quoteAddress, ?QuoteAddress $fallbackAddress, int $customerId): void
    {
        $customerAddressId = (int)$quoteAddress->getCustomerAddressId();
        if ($customerAddressId < 1) {
            return;
        }

        if (false === $this->customerAddressOwnership->isOwnedBy($customerAddressId, $customerId)) {
            return;
        }

        $quoteRow = $this->getQuoteRow($quoteAddress) ?? $this->getQuoteRow($fallbackAddress);
        if (null === $quoteRow) {
            return;
        }

        try {
            $customerRow = $this->customerAddressExtendedRepository->getOrCreateByCustomerAddressId(
                $customerAddressId
            );
            if ($customerRow->getData(CustomerAddressExtended::ENTITY_ID) && !$quoteAddress->getSaveInAddressBook()) {
                return;
            }

            if (false === $this->extendedDataCopier->copyQuoteToCustomerAddressValues($quoteRow, $customerRow)) {
                return;
            }

            $this->customerAddressExtendedRepository->save($customerRow);
        } catch (CouldNotSaveException $exception) {
            $this->logger->error(
                'Loki_QuoteAddressExtended: Could not copy extended quote address data to the customer address',
                [
                    'customer_address_id' => $customerAddressId,
                    'quote_address_id' => $quoteAddress->getId(),
                    'exception' => $exception,
                ]
            );
        }
    }

    private function getQuoteRow(?QuoteAddress $quoteAddress): ?QuoteAddressExtended
    {
        $quoteAddressId = (int)$quoteAddress?->getId();
        if ($quoteAddressId < 1) {
            return null;
        }

        try {
            return $this->quoteAddressExtendedRepository->getByQuoteAddressId($quoteAddressId);
        } catch (NoSuchEntityException) {
            return null;
        }
    }
}
