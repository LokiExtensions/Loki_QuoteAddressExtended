<?php

declare(strict_types=1);

namespace Loki\QuoteAddressExtended\Test\Unit\Util;

use Loki\QuoteAddressExtended\Model\CustomerAddressExtended;
use Loki\QuoteAddressExtended\Model\QuoteAddressExtended;
use Loki\QuoteAddressExtended\Util\ExtendedDataCopier;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ExtendedDataCopierTest extends TestCase
{
    private ResourceConnection&MockObject $resourceConnection;
    private AdapterInterface&MockObject $connection;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(AdapterInterface::class);
        $this->resourceConnection = $this->createMock(ResourceConnection::class);
        $this->resourceConnection->method('getConnection')->willReturn($this->connection);
        $this->resourceConnection->method('getTableName')->willReturnCallback(fn (string $table) => 'prefix_' . $table);
    }

    public function testGetQuoteAddressColumnsExcludesReservedColumns(): void
    {
        $this->setTables(['entity_id', 'quote_address_id', 'order_address_id', 'address_type', 'created_at',
            'updated_at', 'example_address_field', 'house_number'], []);

        $this->assertSame(['example_address_field', 'house_number'], $this->createCopier()->getQuoteAddressColumns());
    }

    public function testGetQuoteAddressColumnsIsCached(): void
    {
        $this->connection->expects($this->once())->method('describeTable')
            ->with('prefix_loki_quote_address_extended')
            ->willReturn(['example_address_field' => []]);

        $copier = $this->createCopier();
        $copier->getQuoteAddressColumns();

        $this->assertSame(['example_address_field'], $copier->getQuoteAddressColumns());
    }

    public function testGetSharedColumnsReturnsIntersection(): void
    {
        $this->setTables(
            ['entity_id', 'quote_address_id', 'example_address_field', 'house_number', 'quote_only'],
            ['entity_id', 'customer_address_id', 'created_at', 'house_number', 'customer_only', 'example_address_field']
        );

        $this->assertSame(['example_address_field', 'house_number'], $this->createCopier()->getSharedColumns());
    }

    public function testGetSharedColumnsExcludesCustomerReservedColumns(): void
    {
        $this->setTables(
            ['customer_address_id', 'example_address_field'],
            ['customer_address_id', 'example_address_field']
        );

        $this->assertSame(['example_address_field'], $this->createCopier()->getSharedColumns());
    }

    public function testGetSharedColumnsIsCached(): void
    {
        $this->connection->expects($this->exactly(2))->method('describeTable')
            ->willReturnMap([
                ['prefix_loki_quote_address_extended', null, ['example_address_field' => []]],
                ['prefix_loki_customer_address_extended', null, ['example_address_field' => []]],
            ]);

        $copier = $this->createCopier();
        $copier->getSharedColumns();
        $copier->getQuoteAddressColumns();

        $this->assertSame(['example_address_field'], $copier->getSharedColumns());
    }

    public function testCopyQuoteAddressValuesOnlyFillsEmptyValues(): void
    {
        $this->setTables(['field_a', 'field_b', 'field_c', 'field_d', 'field_e'], []);
        $source = $this->createQuoteAddressExtended([
            'field_a' => 'source_a',
            'field_b' => 'source_b',
            'field_c' => '',
            'field_d' => null,
            'field_e' => 'source_e',
            'quote_address_id' => 1,
        ]);
        $target = $this->createQuoteAddressExtended([
            'field_a' => 'target_a',
            'field_b' => '',
            'field_c' => 'target_c',
            'quote_address_id' => 2,
        ]);

        $this->assertTrue($this->createCopier()->copyQuoteAddressValues($source, $target));

        $this->assertSame('target_a', $target->getData('field_a'));
        $this->assertSame('source_b', $target->getData('field_b'));
        $this->assertSame('target_c', $target->getData('field_c'));
        $this->assertNull($target->getData('field_d'));
        $this->assertSame('source_e', $target->getData('field_e'));
        $this->assertSame(2, $target->getQuoteAddressId());
    }

    public function testCopyQuoteAddressValuesReturnsFalseWithoutChanges(): void
    {
        $this->setTables(['field_a', 'field_b'], []);
        $source = $this->createQuoteAddressExtended(['field_a' => 'source_a', 'field_b' => '']);
        $target = $this->createQuoteAddressExtended(['field_a' => 'target_a']);

        $this->assertFalse($this->createCopier()->copyQuoteAddressValues($source, $target));
        $this->assertSame('target_a', $target->getData('field_a'));
    }

    public function testCopyQuoteAddressValuesReturnsFalseWithoutColumns(): void
    {
        $this->setTables(['entity_id', 'quote_address_id'], []);
        $source = $this->createQuoteAddressExtended(['quote_address_id' => 1]);
        $target = $this->createQuoteAddressExtended([]);

        $this->assertFalse($this->createCopier()->copyQuoteAddressValues($source, $target));
        $this->assertNull($target->getQuoteAddressId());
    }

    public function testCopyCustomerToQuoteAddressValuesOnlyFillsEmptySharedValues(): void
    {
        $this->setTables(['field_a', 'field_b', 'quote_only'], ['field_a', 'field_b', 'customer_only']);
        $source = $this->createCustomerAddressExtended([
            'field_a' => 'customer_a',
            'field_b' => 'customer_b',
            'customer_only' => 'customer',
        ]);
        $target = $this->createQuoteAddressExtended(['field_a' => 'quote_a']);

        $this->assertTrue($this->createCopier()->copyCustomerToQuoteAddressValues($source, $target));

        $this->assertSame('quote_a', $target->getData('field_a'));
        $this->assertSame('customer_b', $target->getData('field_b'));
        $this->assertNull($target->getData('customer_only'));
    }

    public function testCopyCustomerToQuoteAddressValuesReturnsFalseWithoutChanges(): void
    {
        $this->setTables(['field_a'], ['field_a']);
        $source = $this->createCustomerAddressExtended(['field_a' => 'customer_a']);
        $target = $this->createQuoteAddressExtended(['field_a' => 'quote_a']);

        $this->assertFalse($this->createCopier()->copyCustomerToQuoteAddressValues($source, $target));
    }

    public function testCopyCustomerToQuoteAddressValuesReplacesSharedValues(): void
    {
        $this->setTables(['field_a', 'field_b', 'quote_only'], ['field_a', 'field_b']);
        $source = $this->createCustomerAddressExtended(['field_a' => 'customer_a', 'field_b' => '']);
        $target = $this->createQuoteAddressExtended([
            'field_a' => 'quote_a',
            'field_b' => 'quote_b',
            'quote_only' => 'quote',
        ]);

        $this->assertTrue($this->createCopier()->copyCustomerToQuoteAddressValues($source, $target, true));

        $this->assertSame('customer_a', $target->getData('field_a'));
        $this->assertNull($target->getData('field_b'));
        $this->assertSame('quote', $target->getData('quote_only'));
        $this->assertFalse($this->createCopier()->copyCustomerToQuoteAddressValues($source, $target, true));
    }

    public function testCopyOrderToQuoteAddressValuesReplacesAllQuoteColumns(): void
    {
        $this->setTables(['field_a', 'field_b', 'field_c'], ['field_a']);
        $source = $this->createQuoteAddressExtended(['field_a' => 'order_a', 'field_b' => '']);
        $target = $this->createQuoteAddressExtended(['field_a' => 'book_a', 'field_b' => 'book_b', 'field_c' => 'c']);

        $this->assertTrue($this->createCopier()->copyOrderToQuoteAddressValues($source, $target));

        $this->assertSame('order_a', $target->getData('field_a'));
        $this->assertNull($target->getData('field_b'));
        $this->assertNull($target->getData('field_c'));
        $this->assertFalse($this->createCopier()->copyOrderToQuoteAddressValues($source, $target));
    }

    public function testCopyQuoteToCustomerAddressValuesOverwritesSharedValues(): void
    {
        $this->setTables(['field_a', 'field_b', 'field_c', 'quote_only'], ['field_a', 'field_b', 'field_c']);
        $source = $this->createQuoteAddressExtended([
            'field_a' => 'quote_a',
            'field_b' => '',
            'field_c' => 'quote_c',
            'quote_only' => 'quote',
        ]);
        $target = $this->createCustomerAddressExtended([
            'field_a' => 'customer_a',
            'field_b' => 'customer_b',
        ]);

        $this->assertTrue($this->createCopier()->copyQuoteToCustomerAddressValues($source, $target));

        $this->assertSame('quote_a', $target->getData('field_a'));
        $this->assertSame('customer_b', $target->getData('field_b'));
        $this->assertSame('quote_c', $target->getData('field_c'));
        $this->assertNull($target->getData('quote_only'));
    }

    public function testCopyQuoteToCustomerAddressValuesReturnsFalseForIdenticalValues(): void
    {
        $this->setTables(['field_a'], ['field_a']);
        $source = $this->createQuoteAddressExtended(['field_a' => 'same']);
        $target = $this->createCustomerAddressExtended(['field_a' => 'same']);

        $this->assertFalse($this->createCopier()->copyQuoteToCustomerAddressValues($source, $target));
    }

    private function setTables(array $quoteColumns, array $customerColumns): void
    {
        $this->connection->method('describeTable')->willReturnMap([
            ['prefix_loki_quote_address_extended', null, array_fill_keys($quoteColumns, [])],
            ['prefix_loki_customer_address_extended', null, array_fill_keys($customerColumns, [])],
        ]);
    }

    private function createCopier(): ExtendedDataCopier
    {
        return new ExtendedDataCopier($this->resourceConnection);
    }

    private function createQuoteAddressExtended(array $data): QuoteAddressExtended
    {
        $quoteAddressExtended = $this->getMockBuilder(QuoteAddressExtended::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();
        $quoteAddressExtended->setData($data);

        return $quoteAddressExtended;
    }

    private function createCustomerAddressExtended(array $data): CustomerAddressExtended
    {
        $customerAddressExtended = $this->getMockBuilder(CustomerAddressExtended::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();
        $customerAddressExtended->setData($data);

        return $customerAddressExtended;
    }
}
