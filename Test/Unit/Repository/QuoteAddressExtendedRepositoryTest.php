<?php

declare(strict_types=1);

namespace Loki\QuoteAddressExtended\Test\Unit\Repository;

use Loki\QuoteAddressExtended\Model\QuoteAddressExtended;
use Loki\QuoteAddressExtended\Model\QuoteAddressExtendedFactory;
use Loki\QuoteAddressExtended\Model\ResourceModel\QuoteAddressExtended as QuoteAddressExtendedResource;
use Loki\QuoteAddressExtended\Model\ResourceModel\QuoteAddressExtended\Collection;
use Loki\QuoteAddressExtended\Model\ResourceModel\QuoteAddressExtended\CollectionFactory;
use Loki\QuoteAddressExtended\Repository\QuoteAddressExtendedRepository;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
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

    public function testGetByIdThrowsForInvalidIdWithoutLoading(): void
    {
        $resource = $this->createMock(QuoteAddressExtendedResource::class);
        $resource->expects($this->never())->method('load');

        $this->expectException(NoSuchEntityException::class);

        $this->createRepository($resource)->getById(0);
    }

    public function testGetByQuoteAddressIdThrowsForInvalidIdWithoutLoading(): void
    {
        $resource = $this->createMock(QuoteAddressExtendedResource::class);
        $resource->expects($this->never())->method('load');

        $this->expectException(NoSuchEntityException::class);

        $this->createRepository($resource)->getByQuoteAddressId(-1);
    }

    public function testGetByOrderAddressIdThrowsForInvalidIdWithoutLoading(): void
    {
        $resource = $this->createMock(QuoteAddressExtendedResource::class);
        $resource->expects($this->never())->method('load');

        $this->expectException(NoSuchEntityException::class);

        $this->createRepository($resource)->getByOrderAddressId(0);
    }

    public function testGetByOrderAddressIdReturnsRow(): void
    {
        $this->rows = [
            ['entity_id' => 4, 'quote_address_id' => null, 'order_address_id' => 7],
        ];

        $quoteAddressExtended = $this->createRepository()->getByOrderAddressId(7);

        $this->assertSame(4, (int)$quoteAddressExtended->getData('entity_id'));
        $this->assertSame(7, $quoteAddressExtended->getOrderAddressId());
    }

    public function testGetCollectionUsesFactory(): void
    {
        $collection = $this->createMock(Collection::class);
        $collectionFactory = $this->createMock(CollectionFactory::class);
        $collectionFactory->expects($this->once())->method('create')->willReturn($collection);

        $repository = new QuoteAddressExtendedRepository(
            $this->createMock(QuoteAddressExtendedResource::class),
            $this->createFactory(),
            $collectionFactory
        );

        $this->assertSame($collection, $repository->getCollection());
    }

    public function testGetByIdReturnsRow(): void
    {
        $this->rows = [
            ['entity_id' => 3, 'quote_address_id' => 42, 'order_address_id' => null],
        ];

        $quoteAddressExtended = $this->createRepository()->getById(3);

        $this->assertSame(42, $quoteAddressExtended->getQuoteAddressId());
    }

    public function testSaveWrapsExceptions(): void
    {
        $resource = $this->createMock(QuoteAddressExtendedResource::class);
        $resource->method('save')->willThrowException(new \Exception('Database error'));
        $repository = $this->createRepository($resource);

        $this->expectException(CouldNotSaveException::class);
        $this->expectExceptionMessage('Database error');

        $repository->save($repository->create());
    }

    public function testDeleteWrapsExceptions(): void
    {
        $resource = $this->createMock(QuoteAddressExtendedResource::class);
        $resource->method('delete')->willThrowException(new \Exception('Database error'));
        $repository = $this->createRepository($resource);

        $this->expectException(CouldNotDeleteException::class);
        $this->expectExceptionMessage('Database error');

        $repository->delete($repository->create());
    }

    public function testDetachFromQuoteAddressCopiesCustomColumnsOnly(): void
    {
        $repository = $this->createRepository();
        $original = $repository->create();
        $original->setData([
            'entity_id' => 2,
            'quote_address_id' => 42,
            'order_address_id' => 8,
            'address_type' => 'billing',
            'example_address_field' => 'foobar',
            'created_at' => '2026-01-01 00:00:00',
            'updated_at' => '2026-01-01 00:00:00',
        ]);

        $copy = $repository->detachFromQuoteAddress($original);

        $this->assertNotSame($original, $copy);
        $this->assertSame([
            'example_address_field' => 'foobar',
            'quote_address_id' => 42,
            'address_type' => 'billing',
        ], $copy->getData());

        $this->assertNull($original->getQuoteAddressId());
        $this->assertSame(8, $original->getOrderAddressId());
        $this->assertCount(1, $this->savedRows);
        $this->assertSame(2, (int)$this->savedRows[0]['entity_id']);
    }

    private function createRepository(?QuoteAddressExtendedResource $resource = null): QuoteAddressExtendedRepository
    {
        if ($resource instanceof QuoteAddressExtendedResource) {
            return new QuoteAddressExtendedRepository(
                $resource,
                $this->createFactory(),
                $this->createMock(CollectionFactory::class)
            );
        }

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

        return new QuoteAddressExtendedRepository(
            $resource,
            $this->createFactory(),
            $this->createMock(CollectionFactory::class)
        );
    }

    private function createFactory(): QuoteAddressExtendedFactory
    {
        $factory = $this->createMock(QuoteAddressExtendedFactory::class);
        $factory->method('create')->willReturnCallback(
            fn () => $this->getMockBuilder(QuoteAddressExtended::class)
                ->disableOriginalConstructor()
                ->onlyMethods([])
                ->getMock()
        );

        return $factory;
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
