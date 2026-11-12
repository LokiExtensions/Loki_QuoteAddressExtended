<?php

declare(strict_types=1);

namespace Loki\QuoteAddressExtended\Test\Unit\Util;

use Loki\QuoteAddressExtended\Util\CustomerAddressOwnership;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address as QuoteAddress;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class CustomerAddressOwnershipTest extends TestCase
{
    private ResourceConnection&MockObject $resourceConnection;
    private AdapterInterface&MockObject $connection;
    private array $rows = [];
    private array $queries = [];

    protected function setUp(): void
    {
        $this->rows = [];
        $this->queries = [];
        $this->connection = $this->createMock(AdapterInterface::class);
        $this->connection->method('select')->willReturnCallback(fn () => $this->createSelect());
        $this->connection->method('fetchOne')->willReturnCallback(function (Select $select) {
            $key = $select->getPart('table') . ':' . $select->getPart('id');
            $this->queries[] = $key;
            return $this->rows[$key] ?? false;
        });
        $this->resourceConnection = $this->createMock(ResourceConnection::class);
        $this->resourceConnection->method('getConnection')->willReturn($this->connection);
        $this->resourceConnection->method('getTableName')->willReturnArgument(0);
    }

    public function testIsOwnedByMatchingCustomer(): void
    {
        $this->rows['customer_address_entity:5'] = '3';
        $ownership = $this->createOwnership();

        $this->assertTrue($ownership->isOwnedBy(5, 3));
        $this->assertFalse($ownership->isOwnedBy(5, 4));
        $this->assertSame(['customer_address_entity:5'], $this->queries);
    }

    public function testIsNotOwnedForUnknownAddressGuestOrInvalidIds(): void
    {
        $ownership = $this->createOwnership();

        $this->assertFalse($ownership->isOwnedBy(6, 3));
        $this->assertFalse($ownership->isOwnedBy(6, 0));
        $this->assertFalse($ownership->isOwnedBy(0, 3));
        $this->assertSame(['customer_address_entity:6'], $this->queries);
    }

    public function testUsesCustomerOfQuoteOfQuoteAddress(): void
    {
        $this->rows['customer_address_entity:5'] = '3';
        $quote = $this->getMockBuilder(Quote::class)->disableOriginalConstructor()->onlyMethods([])->getMock();
        $quote->setData('customer_id', 3);
        $quoteAddress = $this->createQuoteAddress(['quote_id' => 9]);
        $quoteAddress->setQuote($quote);

        $this->assertTrue($this->createOwnership()->isOwnedByQuoteCustomer(5, $quoteAddress));
        $this->assertSame(['customer_address_entity:5'], $this->queries);
    }

    public function testLoadsCustomerOfQuoteWhenQuoteIsNotSet(): void
    {
        $this->rows['customer_address_entity:5'] = '3';
        $this->rows['quote:9'] = '4';

        $quoteAddress = $this->createQuoteAddress(['quote_id' => 9]);

        $this->assertFalse($this->createOwnership()->isOwnedByQuoteCustomer(5, $quoteAddress));
        $this->assertSame(['quote:9', 'customer_address_entity:5'], $this->queries);
    }

    public function testQuoteAddressWithoutQuoteIsNotOwned(): void
    {
        $this->assertFalse($this->createOwnership()->isOwnedByQuoteCustomer(5, $this->createQuoteAddress([])));
        $this->assertSame([], $this->queries);
    }

    private function createSelect(): Select
    {
        $select = $this->getMockBuilder(Select::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['from', 'where', 'getPart'])
            ->getMock();
        $parts = [];
        $select->method('from')->willReturnCallback(function (string $table) use ($select, &$parts) {
            $parts['table'] = $table;
            return $select;
        });
        $select->method('where')->willReturnCallback(function (string $condition, mixed $value) use ($select, &$parts) {
            $parts['id'] = $value;
            return $select;
        });
        $select->method('getPart')->willReturnCallback(function (string $part) use (&$parts) {
            return $parts[$part] ?? null;
        });

        return $select;
    }

    private function createQuoteAddress(array $data): QuoteAddress
    {
        $quoteAddress = $this->getMockBuilder(QuoteAddress::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();
        $quoteAddress->setData($data);

        return $quoteAddress;
    }

    private function createOwnership(): CustomerAddressOwnership
    {
        return new CustomerAddressOwnership($this->resourceConnection);
    }
}
