<?php

declare(strict_types=1);

namespace Loki\QuoteAddressExtended\Cron;

use Loki\QuoteAddressExtended\Model\ResourceModel\CustomerAddressExtended as CustomerAddressExtendedResource;
use Loki\QuoteAddressExtended\Model\ResourceModel\QuoteAddressExtended as QuoteAddressExtendedResource;

class DeleteOrphanedRows
{
    public function __construct(
        private readonly QuoteAddressExtendedResource $resource,
        private readonly CustomerAddressExtendedResource $customerAddressExtendedResource
    ) {
    }

    public function execute(): void
    {
        $this->resource->deleteOrphanedRows();
        $this->customerAddressExtendedResource->deleteOrphanedRows();
    }
}
