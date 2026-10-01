<?php

declare(strict_types=1);

namespace Loki\QuoteAddressExtended\Model\ResourceModel\QuoteAddressExtended;

use Loki\QuoteAddressExtended\Model\QuoteAddressExtended as QuoteAddressExtendedModel;
use Loki\QuoteAddressExtended\Model\ResourceModel\QuoteAddressExtended as QuoteAddressExtendedResource;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    protected function _construct(): void
    {
        $this->_init(QuoteAddressExtendedModel::class, QuoteAddressExtendedResource::class);
    }
}
