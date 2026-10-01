<?php

declare(strict_types=1);

namespace Loki\QuoteAddressExtended\Model;

use Exception;
use Loki\QuoteAddressExtended\Model\ResourceModel\QuoteAddressExtended as QuoteAddressExtendedResource;
use Loki\QuoteAddressExtended\Model\ResourceModel\QuoteAddressExtended\Collection;
use Loki\QuoteAddressExtended\Model\ResourceModel\QuoteAddressExtended\CollectionFactory;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;

class QuoteAddressExtendedRepository
{
    public function __construct(
        private readonly QuoteAddressExtendedFactory $quoteAddressExtendedFactory,
        private readonly QuoteAddressExtendedResource $quoteAddressExtendedResource,
        private readonly CollectionFactory $collectionFactory
    ) {
    }

    public function create(): QuoteAddressExtended
    {
        return $this->quoteAddressExtendedFactory->create();
    }

    public function getById(int $id): QuoteAddressExtended
    {
        $quoteAddressExtended = $this->create();
        $this->quoteAddressExtendedResource->load($quoteAddressExtended, $id);

        if (!$quoteAddressExtended->getId()) {
            throw new NoSuchEntityException(
                __('No quote address extended entity found with ID "%1"', $id)
            );
        }

        return $quoteAddressExtended;
    }

    public function getByQuoteAddressId(int $quoteAddressId): QuoteAddressExtended
    {
        $quoteAddressExtended = $this->create();
        $this->quoteAddressExtendedResource->load(
            $quoteAddressExtended,
            $quoteAddressId,
            QuoteAddressExtended::QUOTE_ADDRESS_ID
        );

        if (!$quoteAddressExtended->getId()) {
            throw new NoSuchEntityException(
                __('No quote address extended entity found with quote address ID "%1"', $quoteAddressId)
            );
        }

        return $quoteAddressExtended;
    }

    public function save(QuoteAddressExtended $quoteAddressExtended): QuoteAddressExtended
    {
        try {
            $this->quoteAddressExtendedResource->save($quoteAddressExtended);
        } catch (Exception $exception) {
            throw new CouldNotSaveException(
                __('Could not save quote address extended entity: %1', $exception->getMessage()),
                $exception
            );
        }

        return $quoteAddressExtended;
    }

    public function delete(QuoteAddressExtended $quoteAddressExtended): void
    {
        try {
            $this->quoteAddressExtendedResource->delete($quoteAddressExtended);
        } catch (Exception $exception) {
            throw new CouldNotDeleteException(
                __('Could not delete quote address extended entity: %1', $exception->getMessage()),
                $exception
            );
        }
    }

    public function getCollection(): Collection
    {
        return $this->collectionFactory->create();
    }
}
