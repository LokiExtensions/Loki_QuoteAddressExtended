<?php

declare(strict_types=1);

namespace Loki\QuoteAddressExtended\Repository;

use Exception;
use Loki\QuoteAddressExtended\Model\QuoteAddressExtended;
use Loki\QuoteAddressExtended\Model\QuoteAddressExtendedFactory;
use Loki\QuoteAddressExtended\Model\ResourceModel\QuoteAddressExtended as QuoteAddressExtendedResource;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Model\Order\Address as OrderAddress;

class QuoteAddressExtendedRepository
{
    public const RESERVED_COLUMNS = [
        'entity_id',
        'quote_address_id',
        'order_address_id',
        'address_type',
        'created_at',
        'updated_at',
    ];

    public function __construct(
        private readonly QuoteAddressExtendedResource $resource,
        private readonly QuoteAddressExtendedFactory $quoteAddressExtendedFactory
    ) {
    }

    public function getByQuoteAddressId(int $quoteAddressId): QuoteAddressExtended
    {
        return $this->getByField('quote_address_id', $quoteAddressId);
    }

    public function getByOrderAddressId(int $orderAddressId): QuoteAddressExtended
    {
        return $this->getByField('order_address_id', $orderAddressId);
    }

    public function getByOrderAddress(OrderAddress $orderAddress): QuoteAddressExtended
    {
        $orderAddressId = (int)$orderAddress->getEntityId();

        try {
            return $this->getByOrderAddressId($orderAddressId);
        } catch (NoSuchEntityException $exception) {
            $quoteAddressId = (int)$orderAddress->getData('quote_address_id');
            if ($quoteAddressId < 1) {
                throw $exception;
            }

            $quoteAddressExtended = $this->getByQuoteAddressId($quoteAddressId);
            $linkedOrderAddressId = $quoteAddressExtended->getOrderAddressId();
            if ($linkedOrderAddressId !== null && $linkedOrderAddressId !== $orderAddressId) {
                throw $exception;
            }

            return $quoteAddressExtended;
        }
    }

    public function getOrCreateByQuoteAddressId(int $quoteAddressId): QuoteAddressExtended
    {
        try {
            $quoteAddressExtended = $this->getByQuoteAddressId($quoteAddressId);
        } catch (NoSuchEntityException) {
            $quoteAddressExtended = $this->quoteAddressExtendedFactory->create();
            $quoteAddressExtended->setQuoteAddressId($quoteAddressId);
            return $quoteAddressExtended;
        }

        if ($quoteAddressExtended->getOrderAddressId() !== null) {
            return $this->detachFromQuoteAddress($quoteAddressExtended);
        }

        return $quoteAddressExtended;
    }

    public function detachFromQuoteAddress(QuoteAddressExtended $quoteAddressExtended): QuoteAddressExtended
    {
        $copy = $this->quoteAddressExtendedFactory->create();
        $copy->setData(array_diff_key($quoteAddressExtended->getData(), array_flip(self::RESERVED_COLUMNS)));
        $copy->setQuoteAddressId($quoteAddressExtended->getQuoteAddressId());
        $copy->setAddressType($quoteAddressExtended->getAddressType());

        $quoteAddressExtended->setQuoteAddressId(null);
        $this->save($quoteAddressExtended);

        return $copy;
    }

    public function save(QuoteAddressExtended $quoteAddressExtended): QuoteAddressExtended
    {
        try {
            $this->resource->save($quoteAddressExtended);
        } catch (Exception $exception) {
            throw new CouldNotSaveException(
                __('Could not save the extended quote address data: %1', $exception->getMessage()),
                $exception
            );
        }

        return $quoteAddressExtended;
    }

    private function getByField(string $field, int $value): QuoteAddressExtended
    {
        $quoteAddressExtended = $this->quoteAddressExtendedFactory->create();
        if ($value > 0) {
            $this->resource->load($quoteAddressExtended, $value, $field);
        }

        if (!$quoteAddressExtended->getData(QuoteAddressExtendedResource::PRIMARY_KEY)) {
            throw new NoSuchEntityException(
                __('No extended quote address data found for %1 "%2".', $field, $value)
            );
        }

        return $quoteAddressExtended;
    }
}
