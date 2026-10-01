<?php

declare(strict_types=1);

namespace Loki\QuoteAddressExtended\Cron;

use Loki\QuoteAddressExtended\Model\ResourceModel\QuoteAddressExtended as QuoteAddressExtendedResource;

class DeleteOrphanedRows
{
    public function __construct(
        private readonly QuoteAddressExtendedResource $resource
    ) {
    }

    public function execute(): void
    {
        $this->resource->deleteOrphanedRows();
    }
}
