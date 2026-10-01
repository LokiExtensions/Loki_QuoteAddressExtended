<?php

declare(strict_types=1);

namespace Loki\QuoteAddressExtended\Model\ResourceModel;

use Loki\QuoteAddressExtended\Model\QuoteAddressExtended as QuoteAddressExtendedModel;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class QuoteAddressExtended extends AbstractDb
{
    public const TABLE_NAME = 'loki_quote_address_extended';

    protected function _construct(): void
    {
        $this->_init(self::TABLE_NAME, QuoteAddressExtendedModel::ID);
    }
}
