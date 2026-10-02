<?php

declare(strict_types=1);

namespace Loki\QuoteAddressExtended\Model\ResourceModel\QuoteAddressExtended;

use Loki\QuoteAddressExtended\Model\QuoteAddressExtended;
use Loki\QuoteAddressExtended\Model\ResourceModel\QuoteAddressExtended as QuoteAddressExtendedResource;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    protected $_idFieldName = 'entity_id';
    protected $_eventPrefix = 'loki_quote_address_extended_collection';

    protected function _construct(): void
    {
        $this->_init(QuoteAddressExtended::class, QuoteAddressExtendedResource::class);
    }
}
