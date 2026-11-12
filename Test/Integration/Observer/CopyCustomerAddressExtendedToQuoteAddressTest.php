<?php

declare(strict_types=1);

namespace Loki\QuoteAddressExtended\Test\Integration\Observer;

use Loki\QuoteAddressExtended\Test\Integration\Trait\QuoteAddressExtendedTestTrait;
use Loki\QuoteAddressExtended\Test\Integration\Trait\TemporaryColumnTestTrait;
use Magento\Catalog\Test\Fixture\Product as ProductFixture;
use Magento\Checkout\Test\Fixture\SetBillingAddress as SetBillingAddressFixture;
use Magento\Checkout\Test\Fixture\SetShippingAddress as SetShippingAddressFixture;
use Magento\Customer\Test\Fixture\Customer as CustomerFixture;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Model\Quote\Address as QuoteAddress;
use Magento\Quote\Model\ResourceModel\Quote\Address as QuoteAddressResource;
use Magento\Quote\Test\Fixture\AddProductToCart as AddProductToCartFixture;
use Magento\Quote\Test\Fixture\CustomerCart as CustomerCartFixture;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

#[
    DbIsolation(false),
    DataFixture(ProductFixture::class, as: 'product'),
    DataFixture(CustomerFixture::class, ['addresses' => [[]]], as: 'customer'),
    DataFixture(CustomerCartFixture::class, ['customer_id' => '$customer.id$'], as: 'cart'),
    DataFixture(AddProductToCartFixture::class, ['cart_id' => '$cart.id$', 'product_id' => '$product.id$', 'qty' => 1]),
    DataFixture(SetBillingAddressFixture::class, ['cart_id' => '$cart.id$']),
    DataFixture(SetShippingAddressFixture::class, ['cart_id' => '$cart.id$']),
]
class CopyCustomerAddressExtendedToQuoteAddressTest extends TestCase
{
    use QuoteAddressExtendedTestTrait;
    use TemporaryColumnTestTrait;

    private const SHARED_COLUMN = 'loki_integration_test_shared_field';
    private const CUSTOMER_COLUMN = 'loki_integration_test_customer_field';
    private const QUOTE_COLUMN = 'loki_integration_test_quote_field';

    public static function setUpBeforeClass(): void
    {
        self::addTemporaryColumn(self::SHARED_COLUMN);
        self::addTemporaryColumn(self::CUSTOMER_COLUMN, false, true);
        self::addTemporaryColumn(self::QUOTE_COLUMN, true, false);
    }

    public static function tearDownAfterClass(): void
    {
        self::dropTemporaryColumn(self::SHARED_COLUMN);
        self::dropTemporaryColumn(self::CUSTOMER_COLUMN, false, true);
        self::dropTemporaryColumn(self::QUOTE_COLUMN, true, false);
    }

    protected function setUp(): void
    {
        $this->rememberExtendedRows();
    }

    protected function tearDown(): void
    {
        $this->deleteNewExtendedRows();
    }

    public function testSharedValueIsCopiedToQuoteAddress(): void
    {
        $customerAddressId = $this->getCustomerAddressId();
        $this->saveCustomerAddressExtended($customerAddressId, [
            self::SHARED_COLUMN => 'customer value',
            self::CUSTOMER_COLUMN => 'customer only',
        ]);

        $quoteAddress = $this->linkShippingAddress($customerAddressId);

        $row = $this->getQuoteAddressExtendedRepository()->getByQuoteAddressId((int)$quoteAddress->getId());
        $this->assertSame('customer value', $row->getData(self::SHARED_COLUMN));
        $this->assertSame('shipping', $row->getAddressType());
        $this->assertNull($row->getOrderAddressId());
        $this->assertArrayNotHasKey(self::CUSTOMER_COLUMN, $row->getData());
    }

    public function testExistingQuoteValueIsNotOverwritten(): void
    {
        $customerAddressId = $this->getCustomerAddressId();
        $this->saveCustomerAddressExtended($customerAddressId, [self::SHARED_COLUMN => 'customer value']);
        $quoteAddressId = $this->getShippingAddressId($this->getCartId());
        $this->saveQuoteAddressExtended($quoteAddressId, [
            'address_type' => 'shipping',
            self::SHARED_COLUMN => 'quote value',
            self::QUOTE_COLUMN => 'quote only',
        ]);

        $this->linkShippingAddress($customerAddressId);

        $row = $this->getQuoteAddressExtendedRepository()->getByQuoteAddressId($quoteAddressId);
        $this->assertSame('quote value', $row->getData(self::SHARED_COLUMN));
        $this->assertSame('quote only', $row->getData(self::QUOTE_COLUMN));
    }

    public function testEmptyQuoteValueIsFilled(): void
    {
        $customerAddressId = $this->getCustomerAddressId();
        $this->saveCustomerAddressExtended($customerAddressId, [self::SHARED_COLUMN => 'customer value']);
        $quoteAddressId = $this->getShippingAddressId($this->getCartId());
        $this->saveQuoteAddressExtended($quoteAddressId, [
            'address_type' => 'shipping',
            self::SHARED_COLUMN => '',
            self::QUOTE_COLUMN => 'quote only',
        ]);

        $this->linkShippingAddress($customerAddressId);

        $row = $this->getQuoteAddressExtendedRepository()->getByQuoteAddressId($quoteAddressId);
        $this->assertSame('customer value', $row->getData(self::SHARED_COLUMN));
        $this->assertSame('quote only', $row->getData(self::QUOTE_COLUMN));
    }

    #[
        DataFixture(ProductFixture::class, as: 'product'),
        DataFixture(CustomerFixture::class, ['addresses' => [[], []]], as: 'customer'),
        DataFixture(CustomerCartFixture::class, ['customer_id' => '$customer.id$'], as: 'cart'),
        DataFixture(AddProductToCartFixture::class, ['cart_id' => '$cart.id$', 'product_id' => '$product.id$']),
        DataFixture(SetBillingAddressFixture::class, ['cart_id' => '$cart.id$']),
        DataFixture(SetShippingAddressFixture::class, ['cart_id' => '$cart.id$']),
    ]
    public function testSwitchingCustomerAddressReplacesValues(): void
    {
        $customerId = (int)DataFixtureStorageManager::getStorage()->get('customer')->getId();
        [$addressA, $addressB] = $this->getCustomerAddressIds($customerId);
        $this->saveCustomerAddressExtended($addressA, [self::SHARED_COLUMN => 'value A']);
        $rowB = $this->saveCustomerAddressExtended($addressB, [self::SHARED_COLUMN => 'value B']);

        $quoteAddressId = (int)$this->linkShippingAddress($addressA)->getId();
        $repository = $this->getQuoteAddressExtendedRepository();
        $this->assertSame('value A', $repository->getByQuoteAddressId($quoteAddressId)->getData(self::SHARED_COLUMN));

        $this->linkShippingAddress($addressB);
        $this->assertSame('value B', $repository->getByQuoteAddressId($quoteAddressId)->getData(self::SHARED_COLUMN));

        $this->getCustomerAddressExtendedRepository()->delete($rowB);
        $this->linkShippingAddress($addressA);
        $this->linkShippingAddress($addressB);
        $this->assertNull($repository->getByQuoteAddressId($quoteAddressId)->getData(self::SHARED_COLUMN));
    }

    #[
        DataFixture(ProductFixture::class, as: 'product'),
        DataFixture(CustomerFixture::class, ['addresses' => [[]]], as: 'customer'),
        DataFixture(CustomerFixture::class, ['addresses' => [[]]], as: 'otherCustomer'),
        DataFixture(CustomerCartFixture::class, ['customer_id' => '$customer.id$'], as: 'cart'),
        DataFixture(AddProductToCartFixture::class, ['cart_id' => '$cart.id$', 'product_id' => '$product.id$']),
        DataFixture(SetBillingAddressFixture::class, ['cart_id' => '$cart.id$']),
        DataFixture(SetShippingAddressFixture::class, ['cart_id' => '$cart.id$']),
    ]
    public function testAddressOfAnotherCustomerIsNotCopied(): void
    {
        $otherCustomerId = (int)DataFixtureStorageManager::getStorage()->get('otherCustomer')->getId();
        $otherCustomerAddressId = $this->getCustomerAddressIds($otherCustomerId)[0];
        $this->saveCustomerAddressExtended($otherCustomerAddressId, [self::SHARED_COLUMN => 'other customer value']);

        $quoteAddress = $this->linkShippingAddress($otherCustomerAddressId);

        $this->expectException(NoSuchEntityException::class);
        $this->getQuoteAddressExtendedRepository()->getByQuoteAddressId((int)$quoteAddress->getId());
    }

    public function testCustomerOnlyColumnIsNotCopied(): void
    {
        $customerAddressId = $this->getCustomerAddressId();
        $this->saveCustomerAddressExtended($customerAddressId, [self::CUSTOMER_COLUMN => 'customer only']);

        $quoteAddress = $this->linkShippingAddress($customerAddressId);

        $this->expectException(NoSuchEntityException::class);
        $this->getQuoteAddressExtendedRepository()->getByQuoteAddressId((int)$quoteAddress->getId());
    }

    public function testQuoteAddressWithoutCustomerAddressIsNotChanged(): void
    {
        $customerAddressId = $this->getCustomerAddressId();
        $this->saveCustomerAddressExtended($customerAddressId, [self::SHARED_COLUMN => 'customer value']);

        $quoteAddress = $this->getQuote($this->getCartId())->getShippingAddress();
        $quoteAddress->setCustomerAddressId(null);
        $quoteAddress->setCity('Other city');
        Bootstrap::getObjectManager()->get(QuoteAddressResource::class)->save($quoteAddress);

        $this->expectException(NoSuchEntityException::class);
        $this->getQuoteAddressExtendedRepository()->getByQuoteAddressId((int)$quoteAddress->getId());
    }

    private function linkShippingAddress(int $customerAddressId): QuoteAddress
    {
        $quoteAddress = $this->getQuote($this->getCartId())->getShippingAddress();
        $this->assertGreaterThan(0, (int)$quoteAddress->getId());
        $quoteAddress->setCustomerAddressId($customerAddressId);
        Bootstrap::getObjectManager()->get(QuoteAddressResource::class)->save($quoteAddress);

        return $quoteAddress;
    }

    private function getCustomerAddressId(): int
    {
        $customerId = (int)DataFixtureStorageManager::getStorage()->get('customer')->getId();
        $customerAddressIds = $this->getCustomerAddressIds($customerId);
        $this->assertCount(1, $customerAddressIds);

        return $customerAddressIds[0];
    }

    private function getCartId(): int
    {
        return (int)DataFixtureStorageManager::getStorage()->get('cart')->getId();
    }
}
