<?php

declare(strict_types=1);

namespace Loki\QuoteAddressExtended\Observer;

use Loki\QuoteAddressExtended\Model\CustomerAddressExtended;
use Loki\QuoteAddressExtended\Repository\CustomerAddressExtendedRepository;
use Loki\QuoteAddressExtended\Repository\QuoteAddressExtendedRepository;
use Loki\QuoteAddressExtended\Util\CustomerAddressOwnership;
use Loki\QuoteAddressExtended\Util\ExtendedDataCopier;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Model\Quote\Address as QuoteAddress;
use Psr\Log\LoggerInterface;

class CopyCustomerAddressExtendedToQuoteAddress implements ObserverInterface
{
    private array $copiedCustomerAddressIds = [];

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
        $quoteAddress = $observer->getEvent()->getData('quote_address');
        if (false === $quoteAddress instanceof QuoteAddress) {
            return;
        }

        $customerAddressId = (int)$quoteAddress->getCustomerAddressId();
        $quoteAddressId = (int)$quoteAddress->getId();
        if ($customerAddressId < 1 || $quoteAddressId < 1) {
            return;
        }

        if (false === $quoteAddress->dataHasChangedFor('customer_address_id')) {
            return;
        }

        if (($this->copiedCustomerAddressIds[$quoteAddressId] ?? null) === $customerAddressId) {
            return;
        }

        if ([] === $this->extendedDataCopier->getSharedColumns()) {
            return;
        }

        if (false === $this->customerAddressOwnership->isOwnedByQuoteCustomer($customerAddressId, $quoteAddress)) {
            return;
        }

        $replace = (int)$quoteAddress->getOrigData('customer_address_id') > 0;
        $customerRow = $this->getCustomerRow($customerAddressId, $replace);
        if (null === $customerRow) {
            return;
        }

        try {
            $quoteRow = $this->quoteAddressExtendedRepository->getByQuoteAddressId($quoteAddressId);
        } catch (NoSuchEntityException) {
            $quoteRow = $this->quoteAddressExtendedRepository->create();
            $quoteRow->setQuoteAddressId($quoteAddressId);
        }

        if ($quoteRow->getOrderAddressId() !== null) {
            return;
        }

        try {
            if (false === $this->extendedDataCopier->copyCustomerToQuoteAddressValues(
                $customerRow,
                $quoteRow,
                $replace
            )) {
                return;
            }

            $quoteRow->setAddressType((string)$quoteAddress->getAddressType());
            $this->quoteAddressExtendedRepository->save($quoteRow);
            $this->copiedCustomerAddressIds[$quoteAddressId] = $customerAddressId;
        } catch (CouldNotSaveException $exception) {
            $this->logger->error(
                'Loki_QuoteAddressExtended: Could not copy extended customer address data to the quote address',
                [
                    'customer_address_id' => $customerAddressId,
                    'quote_address_id' => $quoteAddressId,
                    'exception' => $exception->getMessage(),
                ]
            );
        }
    }

    private function getCustomerRow(int $customerAddressId, bool $replace): ?CustomerAddressExtended
    {
        try {
            return $this->customerAddressExtendedRepository->getByCustomerAddressId($customerAddressId);
        } catch (NoSuchEntityException) {
            return $replace ? $this->customerAddressExtendedRepository->create() : null;
        }
    }
}
