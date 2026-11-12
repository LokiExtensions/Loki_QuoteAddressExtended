<?php

declare(strict_types=1);

namespace Loki\QuoteAddressExtended\Test\Unit\Cron;

use Loki\QuoteAddressExtended\Cron\DeleteOrphanedRows;
use Loki\QuoteAddressExtended\Model\ResourceModel\CustomerAddressExtended as CustomerAddressExtendedResource;
use Loki\QuoteAddressExtended\Model\ResourceModel\QuoteAddressExtended as QuoteAddressExtendedResource;
use PHPUnit\Framework\TestCase;

class DeleteOrphanedRowsTest extends TestCase
{
    public function testExecuteDeletesOrphanedRowsOfBothTables(): void
    {
        $resource = $this->createMock(QuoteAddressExtendedResource::class);
        $resource->expects($this->once())->method('deleteOrphanedRows')->willReturn(3);
        $customerResource = $this->createMock(CustomerAddressExtendedResource::class);
        $customerResource->expects($this->once())->method('deleteOrphanedRows')->willReturn(2);

        (new DeleteOrphanedRows($resource, $customerResource))->execute();
    }
}
