<?php

declare(strict_types=1);

namespace Loki\QuoteAddressExtended\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class CustomerAddressExtended extends AbstractDb
{
    public const TABLE_NAME = 'loki_customer_address_extended';
    public const PRIMARY_KEY = 'entity_id';

    protected function _construct(): void
    {
        $this->_init(self::TABLE_NAME, self::PRIMARY_KEY);
    }

    public function deleteOrphanedRows(): int
    {
        $connection = $this->getConnection();
        $customerAddressIds = $connection->select()->from($this->getTable('customer_address_entity'), 'entity_id');

        return $connection->delete(
            $this->getMainTable(),
            ['customer_address_id NOT IN (?)' => $customerAddressIds]
        );
    }
}
