<?php

declare(strict_types=1);

namespace Loki\QuoteAddressExtended\Test\Unit\Model\ResourceModel;

use Loki\QuoteAddressExtended\Model\ResourceModel\CustomerAddressExtended as CustomerAddressExtendedResource;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use PHPUnit\Framework\TestCase;

class CustomerAddressExtendedTest extends TestCase
{
    public function testDeleteOrphanedRowsDeletesRowsWithoutCustomerAddress(): void
    {
        $select = $this->createMock(Select::class);
        $select->expects($this->once())->method('from')
            ->with('customer_address_entity', 'entity_id')
            ->willReturnSelf();

        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('select')->willReturn($select);
        $connection->expects($this->once())->method('delete')
            ->with('loki_customer_address_extended', ['customer_address_id NOT IN (?)' => $select])
            ->willReturn(2);

        $resource = $this->getMockBuilder(CustomerAddressExtendedResource::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getConnection', 'getMainTable', 'getTable'])
            ->getMock();
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getMainTable')->willReturn('loki_customer_address_extended');
        $resource->method('getTable')->willReturnArgument(0);

        $this->assertSame(2, $resource->deleteOrphanedRows());
    }
}
