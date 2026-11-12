<?php

declare(strict_types=1);

namespace Loki\QuoteAddressExtended\Test\Unit\Repository;

use Loki\QuoteAddressExtended\Model\CustomerAddressExtended;
use Loki\QuoteAddressExtended\Model\CustomerAddressExtendedFactory;
use Loki\QuoteAddressExtended\Model\ResourceModel\CustomerAddressExtended as CustomerAddressExtendedResource;
use Loki\QuoteAddressExtended\Model\ResourceModel\CustomerAddressExtended\Collection;
use Loki\QuoteAddressExtended\Model\ResourceModel\CustomerAddressExtended\CollectionFactory;
use Loki\QuoteAddressExtended\Repository\CustomerAddressExtendedRepository;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use PHPUnit\Framework\TestCase;

class CustomerAddressExtendedRepositoryTest extends TestCase
{
    private array $rows = [];

    public function testGetByIdReturnsRow(): void
    {
        $this->rows = [['entity_id' => 3, 'customer_address_id' => 42]];

        $customerAddressExtended = $this->createRepository()->getById(3);

        $this->assertSame(42, $customerAddressExtended->getCustomerAddressId());
    }

    public function testGetByIdThrowsForInvalidIdWithoutLoading(): void
    {
        $resource = $this->createMock(CustomerAddressExtendedResource::class);
        $resource->expects($this->never())->method('load');

        $this->expectException(NoSuchEntityException::class);

        $this->createRepository($resource)->getById(0);
    }

    public function testGetByCustomerAddressIdReturnsRow(): void
    {
        $this->rows = [['entity_id' => 3, 'customer_address_id' => 42]];

        $customerAddressExtended = $this->createRepository()->getByCustomerAddressId(42);

        $this->assertSame(3, (int)$customerAddressExtended->getData('entity_id'));
    }

    public function testGetByCustomerAddressIdThrowsWithoutRow(): void
    {
        $this->expectException(NoSuchEntityException::class);
        $this->expectExceptionMessage('No extended customer address data found for customer_address_id "42".');

        $this->createRepository()->getByCustomerAddressId(42);
    }

    public function testGetByCustomerAddressIdThrowsForInvalidIdWithoutLoading(): void
    {
        $resource = $this->createMock(CustomerAddressExtendedResource::class);
        $resource->expects($this->never())->method('load');

        $this->expectException(NoSuchEntityException::class);

        $this->createRepository($resource)->getByCustomerAddressId(-1);
    }

    public function testGetOrCreateByCustomerAddressIdReturnsExistingRow(): void
    {
        $this->rows = [['entity_id' => 3, 'customer_address_id' => 42, 'example_address_field' => 'foobar']];

        $customerAddressExtended = $this->createRepository()->getOrCreateByCustomerAddressId(42);

        $this->assertSame(3, (int)$customerAddressExtended->getData('entity_id'));
        $this->assertSame('foobar', $customerAddressExtended->getData('example_address_field'));
    }

    public function testGetOrCreateByCustomerAddressIdCreatesNewModel(): void
    {
        $customerAddressExtended = $this->createRepository()->getOrCreateByCustomerAddressId(42);

        $this->assertNull($customerAddressExtended->getData('entity_id'));
        $this->assertSame(42, $customerAddressExtended->getCustomerAddressId());
    }

    public function testSaveReturnsModel(): void
    {
        $resource = $this->createMock(CustomerAddressExtendedResource::class);
        $repository = $this->createRepository($resource);
        $customerAddressExtended = $repository->create();
        $resource->expects($this->once())->method('save')->with($customerAddressExtended);

        $this->assertSame($customerAddressExtended, $repository->save($customerAddressExtended));
    }

    public function testSaveWrapsExceptions(): void
    {
        $resource = $this->createMock(CustomerAddressExtendedResource::class);
        $resource->method('save')->willThrowException(new \Exception('Database error'));
        $repository = $this->createRepository($resource);

        $this->expectException(CouldNotSaveException::class);
        $this->expectExceptionMessage('Could not save the extended customer address data: Database error');

        $repository->save($repository->create());
    }

    public function testDeleteCallsResource(): void
    {
        $resource = $this->createMock(CustomerAddressExtendedResource::class);
        $repository = $this->createRepository($resource);
        $customerAddressExtended = $repository->create();
        $resource->expects($this->once())->method('delete')->with($customerAddressExtended);

        $repository->delete($customerAddressExtended);
    }

    public function testDeleteWrapsExceptions(): void
    {
        $resource = $this->createMock(CustomerAddressExtendedResource::class);
        $resource->method('delete')->willThrowException(new \Exception('Database error'));
        $repository = $this->createRepository($resource);

        $this->expectException(CouldNotDeleteException::class);
        $this->expectExceptionMessage('Could not delete the extended customer address data: Database error');

        $repository->delete($repository->create());
    }

    public function testGetCollectionUsesFactory(): void
    {
        $collection = $this->createMock(Collection::class);
        $collectionFactory = $this->createMock(CollectionFactory::class);
        $collectionFactory->expects($this->once())->method('create')->willReturn($collection);

        $repository = new CustomerAddressExtendedRepository(
            $this->createMock(CustomerAddressExtendedResource::class),
            $this->createFactory(),
            $collectionFactory
        );

        $this->assertSame($collection, $repository->getCollection());
    }

    private function createRepository(
        ?CustomerAddressExtendedResource $resource = null
    ): CustomerAddressExtendedRepository {
        if (false === $resource instanceof CustomerAddressExtendedResource) {
            $resource = $this->createMock(CustomerAddressExtendedResource::class);
            $resource->method('load')->willReturnCallback(
                function (CustomerAddressExtended $model, $value, $field) use ($resource) {
                    foreach ($this->rows as $row) {
                        if ((int)($row[$field] ?? 0) === (int)$value) {
                            $model->setData($row);
                        }
                    }

                    return $resource;
                }
            );
        }

        return new CustomerAddressExtendedRepository(
            $resource,
            $this->createFactory(),
            $this->createMock(CollectionFactory::class)
        );
    }

    private function createFactory(): CustomerAddressExtendedFactory
    {
        $factory = $this->createMock(CustomerAddressExtendedFactory::class);
        $factory->method('create')->willReturnCallback(
            fn () => $this->getMockBuilder(CustomerAddressExtended::class)
                ->disableOriginalConstructor()
                ->onlyMethods([])
                ->getMock()
        );

        return $factory;
    }
}
