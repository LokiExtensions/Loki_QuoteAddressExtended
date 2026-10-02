<?php

declare(strict_types=1);

namespace Loki\QuoteAddressExtended\Repository;

use Exception;
use Loki\QuoteAddressExtended\Model\QuoteAddressExtended;
use Loki\QuoteAddressExtended\Model\QuoteAddressExtendedFactory;
use Loki\QuoteAddressExtended\Model\ResourceModel\QuoteAddressExtended as QuoteAddressExtendedResource;
use Loki\QuoteAddressExtended\Model\ResourceModel\QuoteAddressExtended\Collection;
use Loki\QuoteAddressExtended\Model\ResourceModel\QuoteAddressExtended\CollectionFactory;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Model\Order\Address as OrderAddress;

class QuoteAddressExtendedRepository
{
    public const RESERVED_COLUMNS = [
        QuoteAddressExtended::ENTITY_ID,
        QuoteAddressExtended::QUOTE_ADDRESS_ID,
        QuoteAddressExtended::ORDER_ADDRESS_ID,
        QuoteAddressExtended::ADDRESS_TYPE,
        QuoteAddressExtended::CREATED_AT,
        QuoteAddressExtended::UPDATED_AT,
    ];

    public function __construct(
        private readonly QuoteAddressExtendedResource $resource,
        private readonly QuoteAddressExtendedFactory $quoteAddressExtendedFactory,
        private readonly CollectionFactory $collectionFactory
    ) {
    }

    public function create(): QuoteAddressExtended
    {
        return $this->quoteAddressExtendedFactory->create();
    }

    public function getById(int $id): QuoteAddressExtended
    {
        return $this->getByField(QuoteAddressExtendedResource::PRIMARY_KEY, $id);
    }

    public function getByQuoteAddressId(int $quoteAddressId): QuoteAddressExtended
    {
        return $this->getByField(QuoteAddressExtended::QUOTE_ADDRESS_ID, $quoteAddressId);
    }

    public function getByOrderAddressId(int $orderAddressId): QuoteAddressExtended
    {
        return $this->getByField(QuoteAddressExtended::ORDER_ADDRESS_ID, $orderAddressId);
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
            $quoteAddressExtended = $this->create();
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
        $copy = $this->create();
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

    public function delete(QuoteAddressExtended $quoteAddressExtended): void
    {
        try {
            $this->resource->delete($quoteAddressExtended);
        } catch (Exception $exception) {
            throw new CouldNotDeleteException(
                __('Could not delete the extended quote address data: %1', $exception->getMessage()),
                $exception
            );
        }
    }

    public function getCollection(): Collection
    {
        return $this->collectionFactory->create();
    }

    private function getByField(string $field, int $value): QuoteAddressExtended
    {
        $quoteAddressExtended = $this->create();
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
