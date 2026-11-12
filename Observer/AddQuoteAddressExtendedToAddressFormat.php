<?php

declare(strict_types=1);

namespace Loki\QuoteAddressExtended\Observer;

use Loki\QuoteAddressExtended\Repository\QuoteAddressExtendedRepository;
use Loki\QuoteAddressExtended\Util\ExtendedDataCopier;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Model\Order\Address as OrderAddress;

class AddQuoteAddressExtendedToAddressFormat implements ObserverInterface
{
    public const DATA_KEY = 'loki_quote_address_extended_values';

    public function __construct(
        private readonly QuoteAddressExtendedRepository $quoteAddressExtendedRepository,
        private readonly ExtendedDataCopier $extendedDataCopier
    ) {
    }

    public function execute(Observer $observer): void
    {
        $address = $observer->getEvent()->getData('address');
        if (false === $address instanceof OrderAddress || !$address->getEntityId()) {
            return;
        }

        if (is_array($address->getData(self::DATA_KEY))) {
            return;
        }

        $hadDataChanges = $address->hasDataChanges();
        $address->setData(self::DATA_KEY, $this->getValues($address));
        $address->setDataChanges($hadDataChanges);
    }

    private function getValues(OrderAddress $address): array
    {
        $columns = $this->extendedDataCopier->getQuoteAddressColumns();
        if ([] === $columns) {
            return [];
        }

        try {
            $quoteAddressExtended = $this->quoteAddressExtendedRepository->getByOrderAddress($address);
        } catch (NoSuchEntityException) {
            $quoteAddressExtended = null;
        }

        $values = [];
        foreach ($columns as $column) {
            if ($address->getData($column) !== null) {
                continue;
            }

            $value = $quoteAddressExtended?->getData($column);
            $values[$column] = is_scalar($value) ? (string)$value : '';
        }

        return $values;
    }
}
