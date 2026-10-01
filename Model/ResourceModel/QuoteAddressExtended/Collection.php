<?php

declare(strict_types=1);

namespace Loki\QuoteAddressExtended\Model\ResourceModel\QuoteAddressExtended;

<<<<<<< HEAD
use Loki\QuoteAddressExtended\Model\QuoteAddressExtended as QuoteAddressExtendedModel;
=======
use Loki\QuoteAddressExtended\Model\QuoteAddressExtended;
>>>>>>> 9526565 (WIP)
use Loki\QuoteAddressExtended\Model\ResourceModel\QuoteAddressExtended as QuoteAddressExtendedResource;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
<<<<<<< HEAD
    protected function _construct(): void
    {
        $this->_init(QuoteAddressExtendedModel::class, QuoteAddressExtendedResource::class);
=======
    protected $_idFieldName = 'entity_id';
    protected $_eventPrefix = 'loki_quote_address_extended_collection';

    protected function _construct(): void
    {
        $this->_init(QuoteAddressExtended::class, QuoteAddressExtendedResource::class);
>>>>>>> 9526565 (WIP)
    }
}
