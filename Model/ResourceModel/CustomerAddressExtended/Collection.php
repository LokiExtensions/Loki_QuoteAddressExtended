<?php

declare(strict_types=1);

namespace Loki\QuoteAddressExtended\Model\ResourceModel\CustomerAddressExtended;

use Loki\QuoteAddressExtended\Model\CustomerAddressExtended;
use Loki\QuoteAddressExtended\Model\ResourceModel\CustomerAddressExtended as CustomerAddressExtendedResource;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    protected $_idFieldName = 'entity_id';
    protected $_eventPrefix = 'loki_customer_address_extended_collection';

    protected function _construct(): void
    {
        $this->_init(CustomerAddressExtended::class, CustomerAddressExtendedResource::class);
    }
}
