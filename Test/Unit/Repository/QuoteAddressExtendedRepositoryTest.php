<?php

declare(strict_types=1);

namespace Loki\QuoteAddressExtended\Test\Unit\Repository;

use Loki\QuoteAddressExtended\Model\QuoteAddressExtended;
use Loki\QuoteAddressExtended\Model\QuoteAddressExtendedFactory;
use Loki\QuoteAddressExtended\Model\ResourceModel\QuoteAddressExtended as QuoteAddressExtendedResource;
use Loki\QuoteAddressExtended\Repository\QuoteAddressExtendedRepository;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Model\Order\Address as OrderAddress;
use PHPUnit\Framework\TestCase;

class QuoteAddressExtendedRepositoryTest extends TestCase
{
    private array $rows = [];
    private array $savedRows = [];

    public function testGetByOrderAddressPrefersOrderAddressId(): void
    {
        $this->rows = [
            ['entity_id' => 1, 'quote_address_id' => 42, 'order_address_id' => 7],
        ];

        $quoteAddressExtended = $this->createRepository()->getByOrderAddress($this->createOrderAddress(7, 42));

        $this->assertSame(1, (int)$quoteAddressExtended->getData('entity_id'));
    }

    public function testGetByOrderAddressFallsBackToQuoteAddressId(): void
    {
        $this->rows = [
            ['entity_id' => 2, 'quote_address_id' => 42, 'order_address_id' => null],
        ];

        $quoteAddressExtended = $this->createRepository()->getByOrderAddress($this->createOrderAddress(7, 42));

        $this->assertSame(2, (int)$quoteAddressExtended->getData('entity_id'));
    }

    public function testGetByOrderAddressIgnoresQuoteAddressRowOfOtherOrderAddress(): void
    {
        $this->rows = [
            ['entity_id' => 2, 'quote_address_id' => 42, 'order_address_id' => 8],
        ];

        $this->expectException(NoSuchEntityException::class);

        $this->createRepository()->getByOrderAddress($this->createOrderAddress(7, 42));
    }

    public function testGetByOrderAddressThrowsWithoutRow(): void
    {
        $this->expectException(NoSuchEntityException::class);

        $this->createRepository()->getByOrderAddress($this->createOrderAddress(7, 42));
    }

    public function testGetByOrderAddressThrowsWithoutQuoteAddressId(): void
    {
        $this->rows = [
            ['entity_id' => 2, 'quote_address_id' => 42, 'order_address_id' => null],
        ];

        $this->expectException(NoSuchEntityException::class);

        $this->createRepository()->getByOrderAddress($this->createOrderAddress(7, 0));
    }

    public function testGetOrCreateByQuoteAddressIdDetachesRowLinkedToOrderAddress(): void
    {
        $this->rows = [
            ['entity_id' => 2, 'quote_address_id' => 42, 'order_address_id' => 8, 'address_type' => 'shipping',
                'example_address_field' => 'foobar', 'created_at' => '2026-01-01 00:00:00',
                'updated_at' => '2026-01-01 00:00:00'],
        ];

        $quoteAddressExtended = $this->createRepository()->getOrCreateByQuoteAddressId(42);

        $this->assertNull($quoteAddressExtended->getData('entity_id'));
        $this->assertNull($quoteAddressExtended->getOrderAddressId());
        $this->assertNull($quoteAddressExtended->getData('created_at'));
        $this->assertSame(42, $quoteAddressExtended->getQuoteAddressId());
        $this->assertSame('shipping', $quoteAddressExtended->getAddressType());
        $this->assertSame('foobar', $quoteAddressExtended->getData('example_address_field'));
        $this->assertCount(1, $this->savedRows);
        $this->assertNull($this->savedRows[0]['quote_address_id']);
        $this->assertSame(8, (int)$this->savedRows[0]['order_address_id']);
    }

    public function testGetOrCreateByQuoteAddressIdReturnsUnlinkedRow(): void
    {
        $this->rows = [
            ['entity_id' => 2, 'quote_address_id' => 42, 'order_address_id' => null],
        ];

        $quoteAddressExtended = $this->createRepository()->getOrCreateByQuoteAddressId(42);

        $this->assertSame(2, (int)$quoteAddressExtended->getData('entity_id'));
        $this->assertCount(0, $this->savedRows);
    }

    public function testGetOrCreateByQuoteAddressIdCreatesNewModel(): void
    {
        $quoteAddressExtended = $this->createRepository()->getOrCreateByQuoteAddressId(42);

        $this->assertNull($quoteAddressExtended->getData('entity_id'));
        $this->assertSame(42, $quoteAddressExtended->getQuoteAddressId());
    }

    private function createRepository(): QuoteAddressExtendedRepository
    {
        $resource = $this->createMock(QuoteAddressExtendedResource::class);
        $resource->method('load')->willReturnCallback(
            function (QuoteAddressExtended $model, $value, $field) use ($resource) {
                foreach ($this->rows as $row) {
                    if ((int)($row[$field] ?? 0) === (int)$value) {
                        $model->setData($row);
                    }
                }

                return $resource;
            }
        );

        $resource->method('save')->willReturnCallback(
            function (QuoteAddressExtended $model) use ($resource) {
                $this->savedRows[] = $model->getData();
                return $resource;
            }
        );

        $factory = $this->createMock(QuoteAddressExtendedFactory::class);
        $factory->method('create')->willReturnCallback(
            fn () => $this->getMockBuilder(QuoteAddressExtended::class)
                ->disableOriginalConstructor()
                ->onlyMethods([])
                ->getMock()
        );

        return new QuoteAddressExtendedRepository($resource, $factory);
    }

    private function createOrderAddress(int $orderAddressId, int $quoteAddressId): OrderAddress
    {
        $orderAddress = $this->getMockBuilder(OrderAddress::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();
        $orderAddress->setData(['entity_id' => $orderAddressId, 'quote_address_id' => $quoteAddressId]);

        return $orderAddress;
    }
}
