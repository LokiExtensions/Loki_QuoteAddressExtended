<?php

declare(strict_types=1);

namespace Loki\QuoteAddressExtended\Test\Integration\Repository;

use Loki\QuoteAddressExtended\Repository\QuoteAddressExtendedRepository;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Model\Quote\Address;
use Magento\Quote\Model\QuoteFactory;
use Magento\Quote\Model\ResourceModel\Quote as QuoteResource;
use Magento\Quote\Model\ResourceModel\Quote\Address as QuoteAddressResource;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

class QuoteAddressExtendedRepositoryTest extends TestCase
{
    #[DbIsolation(true)]
    public function testSaveAndLoad(): void
    {
        $repository = $this->getRepository();
        $quoteAddressId = (int)$this->createQuoteAddress()->getId();

        $entity = $repository->create();
        $entity->setQuoteAddressId($quoteAddressId);
        $repository->save($entity);
        $id = (int)$entity->getId();
        $this->assertGreaterThan(0, $id);

        $loadedById = $repository->getById($id);
        $this->assertSame($quoteAddressId, $loadedById->getQuoteAddressId());
        $this->assertNotEmpty($loadedById->getCreatedAt());
        $this->assertNotEmpty($loadedById->getUpdatedAt());

        $loadedByQuoteAddressId = $repository->getByQuoteAddressId($quoteAddressId);
        $this->assertSame($id, (int)$loadedByQuoteAddressId->getId());

        $this->assertSame(1, $repository->getCollection()->addFieldToFilter('entity_id', $id)->getSize());
    }

    #[DbIsolation(true)]
    public function testDelete(): void
    {
        $repository = $this->getRepository();
        $entity = $repository->create();
        $entity->setQuoteAddressId((int)$this->createQuoteAddress()->getId());
        $repository->save($entity);
        $id = (int)$entity->getId();

        $repository->delete($entity);

        $this->expectException(NoSuchEntityException::class);
        $repository->getById($id);
    }

    #[DbIsolation(true)]
    public function testGetByUnknownQuoteAddressIdThrowsException(): void
    {
        $this->expectException(NoSuchEntityException::class);
        $this->getRepository()->getByQuoteAddressId(999999999);
    }

    #[DbIsolation(true)]
    public function testDeletingQuoteAddressUnsetsQuoteAddressId(): void
    {
        $repository = $this->getRepository();
        $quoteAddress = $this->createQuoteAddress();
        $quoteAddressId = (int)$quoteAddress->getId();

        $entity = $repository->create();
        $entity->setQuoteAddressId($quoteAddressId);
        $repository->save($entity);
        $id = (int)$entity->getId();

        Bootstrap::getObjectManager()->get(QuoteAddressResource::class)->delete($quoteAddress);

        $this->assertNull($repository->getById($id)->getQuoteAddressId());

        $this->expectException(NoSuchEntityException::class);
        $repository->getByQuoteAddressId($quoteAddressId);
    }

    #[DbIsolation(true)]
    public function testDuplicateQuoteAddressIdCannotBeSaved(): void
    {
        $repository = $this->getRepository();
        $quoteAddressId = (int)$this->createQuoteAddress()->getId();

        $entity = $repository->create();
        $entity->setQuoteAddressId($quoteAddressId);
        $repository->save($entity);

        $duplicate = $repository->create();
        $duplicate->setQuoteAddressId($quoteAddressId);

        $this->expectException(CouldNotSaveException::class);
        $repository->save($duplicate);
    }

    private function getRepository(): QuoteAddressExtendedRepository
    {
        return Bootstrap::getObjectManager()->get(QuoteAddressExtendedRepository::class);
    }

    private function createQuoteAddress(): Address
    {
        $objectManager = Bootstrap::getObjectManager();
        $quote = $objectManager->get(QuoteFactory::class)->create();
        $quote->setStoreId(1);
        $quote->setIsActive(true);
        $quote->getShippingAddress()->addData([
            'firstname' => 'John',
            'lastname' => 'Doe',
            'street' => 'Main Street 1',
            'city' => 'Amsterdam',
            'postcode' => '1000AA',
            'country_id' => 'NL',
            'telephone' => '0123456789',
        ]);
        $objectManager->get(QuoteResource::class)->save($quote);

        $address = $quote->getShippingAddress();
        $this->assertGreaterThan(0, (int)$address->getId());

        return $address;
    }
}
