<?php

declare(strict_types=1);

namespace Loki\QuoteAddressExtended\Repository;

use Exception;
use Loki\QuoteAddressExtended\Model\CustomerAddressExtended;
use Loki\QuoteAddressExtended\Model\CustomerAddressExtendedFactory;
use Loki\QuoteAddressExtended\Model\ResourceModel\CustomerAddressExtended as CustomerAddressExtendedResource;
use Loki\QuoteAddressExtended\Model\ResourceModel\CustomerAddressExtended\Collection;
use Loki\QuoteAddressExtended\Model\ResourceModel\CustomerAddressExtended\CollectionFactory;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;

class CustomerAddressExtendedRepository
{
    public const RESERVED_COLUMNS = [
        CustomerAddressExtended::ENTITY_ID,
        CustomerAddressExtended::CUSTOMER_ADDRESS_ID,
        CustomerAddressExtended::CREATED_AT,
        CustomerAddressExtended::UPDATED_AT,
    ];

    public function __construct(
        private readonly CustomerAddressExtendedResource $resource,
        private readonly CustomerAddressExtendedFactory $customerAddressExtendedFactory,
        private readonly CollectionFactory $collectionFactory
    ) {
    }

    public function create(): CustomerAddressExtended
    {
        return $this->customerAddressExtendedFactory->create();
    }

    public function getById(int $id): CustomerAddressExtended
    {
        return $this->getByField(CustomerAddressExtendedResource::PRIMARY_KEY, $id);
    }

    public function getByCustomerAddressId(int $customerAddressId): CustomerAddressExtended
    {
        return $this->getByField(CustomerAddressExtended::CUSTOMER_ADDRESS_ID, $customerAddressId);
    }

    public function getOrCreateByCustomerAddressId(int $customerAddressId): CustomerAddressExtended
    {
        try {
            return $this->getByCustomerAddressId($customerAddressId);
        } catch (NoSuchEntityException) {
            $customerAddressExtended = $this->create();
            $customerAddressExtended->setCustomerAddressId($customerAddressId);
            return $customerAddressExtended;
        }
    }

    public function save(CustomerAddressExtended $customerAddressExtended): CustomerAddressExtended
    {
        try {
            $this->resource->save($customerAddressExtended);
        } catch (Exception $exception) {
            throw new CouldNotSaveException(
                __('Could not save the extended customer address data: %1', $exception->getMessage()),
                $exception
            );
        }

        return $customerAddressExtended;
    }

    public function delete(CustomerAddressExtended $customerAddressExtended): void
    {
        try {
            $this->resource->delete($customerAddressExtended);
        } catch (Exception $exception) {
            throw new CouldNotDeleteException(
                __('Could not delete the extended customer address data: %1', $exception->getMessage()),
                $exception
            );
        }
    }

    public function getCollection(): Collection
    {
        return $this->collectionFactory->create();
    }

    private function getByField(string $field, int $value): CustomerAddressExtended
    {
        $customerAddressExtended = $this->create();
        if ($value > 0) {
            $this->resource->load($customerAddressExtended, $value, $field);
        }

        if (!$customerAddressExtended->getData(CustomerAddressExtendedResource::PRIMARY_KEY)) {
            throw new NoSuchEntityException(
                __('No extended customer address data found for %1 "%2".', $field, $value)
            );
        }

        return $customerAddressExtended;
    }
}
