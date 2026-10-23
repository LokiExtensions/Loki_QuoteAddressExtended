<?php

declare(strict_types=1);

namespace Loki\QuoteAddressExtended\Test\Unit\Model\ResourceModel;

use Loki\QuoteAddressExtended\Model\ResourceModel\QuoteAddressExtended as QuoteAddressExtendedResource;
use Magento\Framework\DB\Adapter\AdapterInterface;
use PHPUnit\Framework\TestCase;

class QuoteAddressExtendedTest extends TestCase
{
    public function testDeleteOrphanedRowsDeletesRowsWithoutAnyLink(): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects($this->once())->method('delete')
            ->with('loki_quote_address_extended', ['quote_address_id IS NULL', 'order_address_id IS NULL'])
            ->willReturn(3);

        $resource = $this->getMockBuilder(QuoteAddressExtendedResource::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getConnection', 'getMainTable'])
            ->getMock();
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getMainTable')->willReturn('loki_quote_address_extended');

        $this->assertSame(3, $resource->deleteOrphanedRows());
    }
}
