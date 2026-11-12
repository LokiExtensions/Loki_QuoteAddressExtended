<?php

declare(strict_types=1);

namespace Loki\QuoteAddressExtended\Util;

use Loki\QuoteAddressExtended\Model\CustomerAddressExtended;
use Loki\QuoteAddressExtended\Model\QuoteAddressExtended;
use Loki\QuoteAddressExtended\Model\ResourceModel\CustomerAddressExtended as CustomerAddressExtendedResource;
use Loki\QuoteAddressExtended\Model\ResourceModel\QuoteAddressExtended as QuoteAddressExtendedResource;
use Loki\QuoteAddressExtended\Repository\CustomerAddressExtendedRepository;
use Loki\QuoteAddressExtended\Repository\QuoteAddressExtendedRepository;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DataObject;

class ExtendedDataCopier
{
    private ?array $quoteAddressColumns = null;
    private ?array $customerAddressColumns = null;

    public function __construct(
        private readonly ResourceConnection $resourceConnection
    ) {
    }

    public function getQuoteAddressColumns(): array
    {
        if ($this->quoteAddressColumns === null) {
            $this->quoteAddressColumns = $this->getColumns(
                QuoteAddressExtendedResource::TABLE_NAME,
                QuoteAddressExtendedRepository::RESERVED_COLUMNS
            );
        }

        return $this->quoteAddressColumns;
    }

    public function getSharedColumns(): array
    {
        if ($this->customerAddressColumns === null) {
            $this->customerAddressColumns = $this->getColumns(
                CustomerAddressExtendedResource::TABLE_NAME,
                CustomerAddressExtendedRepository::RESERVED_COLUMNS
            );
        }

        return array_values(array_intersect($this->getQuoteAddressColumns(), $this->customerAddressColumns));
    }

    public function copyQuoteAddressValues(QuoteAddressExtended $source, QuoteAddressExtended $target): bool
    {
        return $this->copyValues($source, $target, $this->getQuoteAddressColumns(), false);
    }

    public function copyOrderToQuoteAddressValues(QuoteAddressExtended $source, QuoteAddressExtended $target): bool
    {
        return $this->replaceValues($source, $target, $this->getQuoteAddressColumns());
    }

    public function copyCustomerToQuoteAddressValues(
        CustomerAddressExtended $source,
        QuoteAddressExtended $target,
        bool $replace = false
    ): bool {
        if ($replace) {
            return $this->replaceValues($source, $target, $this->getSharedColumns());
        }

        return $this->copyValues($source, $target, $this->getSharedColumns(), false);
    }

    public function copyQuoteToCustomerAddressValues(
        QuoteAddressExtended $source,
        CustomerAddressExtended $target
    ): bool {
        return $this->copyValues($source, $target, $this->getSharedColumns(), true);
    }

    private function copyValues(DataObject $source, DataObject $target, array $columns, bool $overwrite): bool
    {
        $changed = false;
        foreach ($columns as $column) {
            $value = $source->getData($column);
            if ($this->isEmpty($value)) {
                continue;
            }

            $currentValue = $target->getData($column);
            if (false === $overwrite && false === $this->isEmpty($currentValue)) {
                continue;
            }

            if ($currentValue === $value) {
                continue;
            }

            $target->setData($column, $value);
            $changed = true;
        }

        return $changed;
    }

    private function replaceValues(DataObject $source, DataObject $target, array $columns): bool
    {
        $changed = false;
        foreach ($columns as $column) {
            $value = $source->getData($column);
            $value = $this->isEmpty($value) ? null : $value;
            if ($target->getData($column) === $value) {
                continue;
            }

            $target->setData($column, $value);
            $changed = true;
        }

        return $changed;
    }

    private function isEmpty(mixed $value): bool
    {
        return $value === null || $value === '';
    }

    private function getColumns(string $tableName, array $reservedColumns): array
    {
        $table = $this->resourceConnection->getTableName($tableName);
        $columns = array_keys($this->resourceConnection->getConnection()->describeTable($table));

        return array_values(array_diff($columns, $reservedColumns));
    }
}
