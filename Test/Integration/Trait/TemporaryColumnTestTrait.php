<?php

declare(strict_types=1);

namespace Loki\QuoteAddressExtended\Test\Integration\Trait;

use Loki\QuoteAddressExtended\Model\ResourceModel\CustomerAddressExtended as CustomerAddressExtendedResource;
use Loki\QuoteAddressExtended\Model\ResourceModel\QuoteAddressExtended as QuoteAddressExtendedResource;
use Loki\QuoteAddressExtended\Observer\AddQuoteAddressExtendedToAddressFormat;
use Loki\QuoteAddressExtended\Observer\CopyCustomerAddressExtendedToQuoteAddress;
use Loki\QuoteAddressExtended\Observer\CopyQuoteAddressExtendedOnQuoteMerge;
use Loki\QuoteAddressExtended\Observer\CopyQuoteAddressExtendedToCustomerAddress;
use Loki\QuoteAddressExtended\Plugin\CopyQuoteAddressExtendedOnInitFromOrder;
use Loki\QuoteAddressExtended\Util\ExtendedDataCopier;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Ddl\Table;
use Magento\TestFramework\Helper\Bootstrap;

trait TemporaryColumnTestTrait
{
    private array $extendedRowMaxIds = [];

    protected static function addTemporaryColumn(string $column, bool $quoteTable = true, bool $customerTable = true): void
    {
        $connection = self::getExtendedConnection();
        foreach (self::getExtendedTables($quoteTable, $customerTable) as $table) {
            if (false === $connection->tableColumnExists($table, $column)) {
                $connection->addColumn($table, $column, [
                    'type' => Table::TYPE_TEXT,
                    'length' => 255,
                    'nullable' => true,
                    'comment' => 'Temporary integration test column',
                ]);
            }

            $connection->resetDdlCache($table);
        }

        self::resetExtendedDataCopier();
    }

    protected static function dropTemporaryColumn(string $column, bool $quoteTable = true, bool $customerTable = true): void
    {
        $connection = self::getExtendedConnection();
        foreach (self::getExtendedTables($quoteTable, $customerTable) as $table) {
            $connection->dropColumn($table, $column);
            $connection->resetDdlCache($table);
        }

        self::resetExtendedDataCopier();
    }

    protected static function resetExtendedDataCopier(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        foreach ([
            ExtendedDataCopier::class,
            AddQuoteAddressExtendedToAddressFormat::class,
            CopyCustomerAddressExtendedToQuoteAddress::class,
            CopyQuoteAddressExtendedOnQuoteMerge::class,
            CopyQuoteAddressExtendedToCustomerAddress::class,
            CopyQuoteAddressExtendedOnInitFromOrder::class,
        ] as $className) {
            $objectManager->removeSharedInstance($className);
        }
    }

    protected function rememberExtendedRows(): void
    {
        $connection = self::getExtendedConnection();
        foreach (self::getExtendedTables(true, true) as $table) {
            $this->extendedRowMaxIds[$table] = (int)$connection->fetchOne('SELECT MAX(entity_id) FROM ' . $table);
        }
    }

    protected function deleteNewExtendedRows(): void
    {
        $connection = self::getExtendedConnection();
        foreach ($this->extendedRowMaxIds as $table => $maxId) {
            $connection->delete($table, ['entity_id > ?' => $maxId]);
        }

        $this->extendedRowMaxIds = [];
    }

    protected static function getExtendedConnection(): AdapterInterface
    {
        return Bootstrap::getObjectManager()->get(ResourceConnection::class)->getConnection();
    }

    protected static function getExtendedTable(string $tableName): string
    {
        return Bootstrap::getObjectManager()->get(ResourceConnection::class)->getTableName($tableName);
    }

    private static function getExtendedTables(bool $quoteTable, bool $customerTable): array
    {
        $tables = [];
        if ($quoteTable) {
            $tables[] = self::getExtendedTable(QuoteAddressExtendedResource::TABLE_NAME);
        }

        if ($customerTable) {
            $tables[] = self::getExtendedTable(CustomerAddressExtendedResource::TABLE_NAME);
        }

        return $tables;
    }
}
