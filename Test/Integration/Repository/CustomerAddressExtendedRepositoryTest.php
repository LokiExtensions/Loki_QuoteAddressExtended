<?php

declare(strict_types=1);

namespace Loki\QuoteAddressExtended\Test\Integration\Repository;

use Loki\QuoteAddressExtended\Model\CustomerAddressExtended;
use Loki\QuoteAddressExtended\Model\ResourceModel\CustomerAddressExtended as CustomerAddressExtendedResource;
use Loki\QuoteAddressExtended\Test\Integration\Trait\QuoteAddressExtendedTestTrait;
use Magento\Customer\Api\AddressRepositoryInterface;
use Magento\Customer\Test\Fixture\Customer as CustomerFixture;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

#[
    DbIsolation(true),
    DataFixture(CustomerFixture::class, ['addresses' => [[], []]], as: 'customer'),
]
class CustomerAddressExtendedRepositoryTest extends TestCase
{
    use QuoteAddressExtendedTestTrait;

    public function testSaveAndLoadRow(): void
    {
        $customerAddressId = $this->getCustomerAddressIds($this->getCustomerId())[0];
        $repository = $this->getCustomerAddressExtendedRepository();

        $row = $repository->create();
        $this->assertInstanceOf(CustomerAddressExtended::class, $row);
        $row->setCustomerAddressId($customerAddressId);
        $repository->save($row);
        $this->assertGreaterThan(0, (int)$row->getId());

        $loadedById = $repository->getById((int)$row->getId());
        $this->assertSame($customerAddressId, $loadedById->getCustomerAddressId());
        $this->assertNotEmpty($loadedById->getData('created_at'));
        $this->assertNotEmpty($loadedById->getData('updated_at'));

        $loadedByAddress = $repository->getByCustomerAddressId($customerAddressId);
        $this->assertSame((int)$row->getId(), (int)$loadedByAddress->getId());
    }

    public function testGetOrCreateByCustomerAddressId(): void
    {
        $customerAddressId = $this->getCustomerAddressIds($this->getCustomerId())[0];
        $repository = $this->getCustomerAddressExtendedRepository();

        $newRow = $repository->getOrCreateByCustomerAddressId($customerAddressId);
        $this->assertNull($newRow->getId());
        $this->assertSame($customerAddressId, $newRow->getCustomerAddressId());

        $repository->save($newRow);
        $existingRow = $repository->getOrCreateByCustomerAddressId($customerAddressId);
        $this->assertSame((int)$newRow->getId(), (int)$existingRow->getId());
    }

    public function testCollectionContainsSavedRows(): void
    {
        $customerAddressIds = $this->getCustomerAddressIds($this->getCustomerId());
        $this->assertCount(2, $customerAddressIds);
        foreach ($customerAddressIds as $customerAddressId) {
            $this->saveCustomerAddressExtended($customerAddressId);
        }

        $collection = $this->getCustomerAddressExtendedRepository()->getCollection();
        $collection->addFieldToFilter('customer_address_id', ['in' => $customerAddressIds]);

        $this->assertCount(2, $collection);
        $this->assertContainsOnlyInstancesOf(CustomerAddressExtended::class, $collection->getItems());
    }

    public function testDeleteRow(): void
    {
        $customerAddressId = $this->getCustomerAddressIds($this->getCustomerId())[0];
        $row = $this->saveCustomerAddressExtended($customerAddressId);

        $this->getCustomerAddressExtendedRepository()->delete($row);

        $this->expectException(NoSuchEntityException::class);
        $this->getCustomerAddressExtendedRepository()->getById((int)$row->getId());
    }

    public function testUnknownRowsThrowException(): void
    {
        $repository = $this->getCustomerAddressExtendedRepository();

        foreach ([0, 999999999] as $id) {
            try {
                $repository->getByCustomerAddressId($id);
                $this->fail('Expected NoSuchEntityException for customer address ID ' . $id);
            } catch (NoSuchEntityException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->expectException(NoSuchEntityException::class);
        $repository->getById(999999999);
    }

    public function testCustomerAddressIdIsUnique(): void
    {
        $customerAddressId = $this->getCustomerAddressIds($this->getCustomerId())[0];
        $this->saveCustomerAddressExtended($customerAddressId);

        $duplicate = $this->getCustomerAddressExtendedRepository()->create();
        $duplicate->setCustomerAddressId($customerAddressId);

        $this->expectException(CouldNotSaveException::class);
        $this->getCustomerAddressExtendedRepository()->save($duplicate);
    }

    public function testRowIsDeletedWithCustomerAddress(): void
    {
        [$deletedAddressId, $keptAddressId] = $this->getCustomerAddressIds($this->getCustomerId());
        $deletedRow = $this->saveCustomerAddressExtended($deletedAddressId);
        $keptRow = $this->saveCustomerAddressExtended($keptAddressId);

        Bootstrap::getObjectManager()->get(AddressRepositoryInterface::class)->deleteById($deletedAddressId);

        $this->assertSame(
            (int)$keptRow->getId(),
            (int)$this->getCustomerAddressExtendedRepository()->getById((int)$keptRow->getId())->getId()
        );
        $this->expectException(NoSuchEntityException::class);
        $this->getCustomerAddressExtendedRepository()->getById((int)$deletedRow->getId());
    }

    public function testDeleteOrphanedRows(): void
    {
        $customerAddressId = $this->getCustomerAddressIds($this->getCustomerId())[0];
        $linkedRow = $this->saveCustomerAddressExtended($customerAddressId);
        $orphanId = $this->insertOrphanedRow();

        $deleted = Bootstrap::getObjectManager()->get(CustomerAddressExtendedResource::class)->deleteOrphanedRows();

        $this->assertGreaterThanOrEqual(1, $deleted);
        $repository = $this->getCustomerAddressExtendedRepository();
        $this->assertSame((int)$linkedRow->getId(), (int)$repository->getById((int)$linkedRow->getId())->getId());
        $this->expectException(NoSuchEntityException::class);
        $repository->getById($orphanId);
    }

    private function insertOrphanedRow(): int
    {
        $resource = Bootstrap::getObjectManager()->get(ResourceConnection::class);
        $connection = $resource->getConnection();
        $table = $resource->getTableName(CustomerAddressExtendedResource::TABLE_NAME);
        $nonExistingAddressId = (int)$connection->fetchOne(
            'SELECT IFNULL(MAX(entity_id), 0) + 1000 FROM ' . $resource->getTableName('customer_address_entity')
        );

        $connection->query('SET FOREIGN_KEY_CHECKS = 0');
        try {
            $connection->insert($table, ['customer_address_id' => $nonExistingAddressId]);
            $orphanId = (int)$connection->lastInsertId($table);
        } finally {
            $connection->query('SET FOREIGN_KEY_CHECKS = 1');
        }

        return $orphanId;
    }

    private function getCustomerId(): int
    {
        return (int)DataFixtureStorageManager::getStorage()->get('customer')->getId();
    }
}
