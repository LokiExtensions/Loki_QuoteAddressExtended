<?php

declare(strict_types=1);

namespace Loki\QuoteAddressExtended\Test\Unit\Cron;

use Loki\QuoteAddressExtended\Cron\DeleteOrphanedRows;
use Loki\QuoteAddressExtended\Model\ResourceModel\QuoteAddressExtended as QuoteAddressExtendedResource;
use PHPUnit\Framework\TestCase;

class DeleteOrphanedRowsTest extends TestCase
{
    public function testExecuteDeletesOrphanedRows(): void
    {
        $resource = $this->createMock(QuoteAddressExtendedResource::class);
        $resource->expects($this->once())->method('deleteOrphanedRows')->willReturn(3);

        (new DeleteOrphanedRows($resource))->execute();
    }
}
