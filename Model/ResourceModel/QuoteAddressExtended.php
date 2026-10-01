<?php

declare(strict_types=1);

namespace Loki\QuoteAddressExtended\Model\ResourceModel;

<<<<<<< HEAD
use Loki\QuoteAddressExtended\Model\QuoteAddressExtended as QuoteAddressExtendedModel;
=======
>>>>>>> 9526565 (WIP)
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class QuoteAddressExtended extends AbstractDb
{
    public const TABLE_NAME = 'loki_quote_address_extended';
<<<<<<< HEAD

    protected function _construct(): void
    {
        $this->_init(self::TABLE_NAME, QuoteAddressExtendedModel::ID);
=======
    public const PRIMARY_KEY = 'entity_id';

    protected function _construct(): void
    {
        $this->_init(self::TABLE_NAME, self::PRIMARY_KEY);
    }

    public function deleteOrphanedRows(): int
    {
        return $this->getConnection()->delete(
            $this->getMainTable(),
            ['quote_address_id IS NULL', 'order_address_id IS NULL']
        );
>>>>>>> 9526565 (WIP)
    }
}
