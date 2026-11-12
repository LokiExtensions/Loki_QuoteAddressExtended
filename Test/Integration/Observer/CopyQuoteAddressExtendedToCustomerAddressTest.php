<?php

declare(strict_types=1);

namespace Loki\QuoteAddressExtended\Test\Integration\Observer;

use Loki\QuoteAddressExtended\Test\Integration\Trait\QuoteAddressExtendedTestTrait;
use Loki\QuoteAddressExtended\Test\Integration\Trait\TemporaryColumnTestTrait;
use Magento\Catalog\Test\Fixture\Product as ProductFixture;
use Magento\Checkout\Test\Fixture\SetBillingAddress as SetBillingAddressFixture;
use Magento\Checkout\Test\Fixture\SetDeliveryMethod as SetDeliveryMethodFixture;
use Magento\Checkout\Test\Fixture\SetPaymentMethod as SetPaymentMethodFixture;
use Magento\Checkout\Test\Fixture\SetShippingAddress as SetShippingAddressFixture;
use Magento\Customer\Test\Fixture\Customer as CustomerFixture;
use Loki\QuoteAddressExtended\Observer\CopyQuoteAddressExtendedToCustomerAddress;
use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Test\Fixture\AddProductToCart as AddProductToCartFixture;
use Magento\Quote\Test\Fixture\CustomerCart as CustomerCartFixture;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

#[
    DbIsolation(false),
]
class CopyQuoteAddressExtendedToCustomerAddressTest extends TestCase
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

    #[
        DataFixture(ProductFixture::class, as: 'product'),
        DataFixture(CustomerFixture::class, as: 'customer'),
        DataFixture(CustomerCartFixture::class, ['customer_id' => '$customer.id$'], as: 'cart'),
        DataFixture(AddProductToCartFixture::class, ['cart_id' => '$cart.id$', 'product_id' => '$product.id$', 'qty' => 1]),
        DataFixture(
            SetBillingAddressFixture::class,
            ['cart_id' => '$cart.id$', 'address' => ['save_in_address_book' => 1]]
        ),
        DataFixture(
            SetShippingAddressFixture::class,
            ['cart_id' => '$cart.id$', 'address' => ['save_in_address_book' => 1]]
        ),
        DataFixture(SetDeliveryMethodFixture::class, ['cart_id' => '$cart.id$']),
        DataFixture(SetPaymentMethodFixture::class, ['cart_id' => '$cart.id$']),
    ]
    public function testPlacingOrderCopiesValuesToSavedCustomerAddresses(): void
    {
        $cartId = $this->getCartId();
        $this->saveQuoteAddressExtended($this->getShippingAddressId($cartId), [
            'address_type' => 'shipping',
            self::SHARED_COLUMN => 'shipping value',
            self::QUOTE_COLUMN => 'quote only',
        ]);
        $this->saveQuoteAddressExtended($this->getBillingAddressId($cartId), [
            'address_type' => 'billing',
            self::SHARED_COLUMN => 'billing value',
        ]);

        $order = $this->placeOrder($cartId);

        $shippingCustomerAddressId = (int)$order->getShippingAddress()->getCustomerAddressId();
        $billingCustomerAddressId = (int)$order->getBillingAddress()->getCustomerAddressId();
        $this->assertGreaterThan(0, $shippingCustomerAddressId);
        $this->assertGreaterThan(0, $billingCustomerAddressId);
        $this->assertNotSame($shippingCustomerAddressId, $billingCustomerAddressId);
        $this->assertContains($shippingCustomerAddressId, $this->getCustomerAddressIds($this->getCustomerId()));

        $repository = $this->getCustomerAddressExtendedRepository();
        $shippingRow = $repository->getByCustomerAddressId($shippingCustomerAddressId);
        $this->assertSame('shipping value', $shippingRow->getData(self::SHARED_COLUMN));
        $this->assertNull($shippingRow->getData(self::CUSTOMER_COLUMN));
        $this->assertArrayNotHasKey(self::QUOTE_COLUMN, $shippingRow->getData());

        $billingRow = $repository->getByCustomerAddressId($billingCustomerAddressId);
        $this->assertSame('billing value', $billingRow->getData(self::SHARED_COLUMN));
    }

    #[
        DataFixture(ProductFixture::class, as: 'product'),
        DataFixture(CustomerFixture::class, as: 'customer'),
        DataFixture(CustomerCartFixture::class, ['customer_id' => '$customer.id$'], as: 'cart'),
        DataFixture(AddProductToCartFixture::class, ['cart_id' => '$cart.id$', 'product_id' => '$product.id$', 'qty' => 1]),
        DataFixture(
            SetBillingAddressFixture::class,
            ['cart_id' => '$cart.id$', 'address' => ['save_in_address_book' => 1]]
        ),
        DataFixture(
            SetShippingAddressFixture::class,
            ['cart_id' => '$cart.id$', 'address' => ['same_as_billing' => 1]]
        ),
        DataFixture(SetDeliveryMethodFixture::class, ['cart_id' => '$cart.id$']),
        DataFixture(SetPaymentMethodFixture::class, ['cart_id' => '$cart.id$']),
    ]
    public function testSameAsBillingUsesShippingValuesForBillingCustomerAddress(): void
    {
        $cartId = $this->getCartId();
        $this->saveQuoteAddressExtended($this->getShippingAddressId($cartId), [
            'address_type' => 'shipping',
            self::SHARED_COLUMN => 'shipping value',
        ]);

        $order = $this->placeOrder($cartId);

        $billingCustomerAddressId = (int)$order->getBillingAddress()->getCustomerAddressId();
        $this->assertGreaterThan(0, $billingCustomerAddressId);
        $billingRow = $this->getCustomerAddressExtendedRepository()->getByCustomerAddressId($billingCustomerAddressId);
        $this->assertSame('shipping value', $billingRow->getData(self::SHARED_COLUMN));
    }

    #[
        DataFixture(ProductFixture::class, as: 'product'),
        DataFixture(CustomerFixture::class, as: 'customer'),
        DataFixture(CustomerCartFixture::class, ['customer_id' => '$customer.id$'], as: 'cart'),
        DataFixture(AddProductToCartFixture::class, ['cart_id' => '$cart.id$', 'product_id' => '$product.id$', 'qty' => 1]),
        DataFixture(SetBillingAddressFixture::class, ['cart_id' => '$cart.id$']),
        DataFixture(
            SetShippingAddressFixture::class,
            ['cart_id' => '$cart.id$', 'address' => ['save_in_address_book' => 1]]
        ),
        DataFixture(SetDeliveryMethodFixture::class, ['cart_id' => '$cart.id$']),
        DataFixture(SetPaymentMethodFixture::class, ['cart_id' => '$cart.id$']),
    ]
    public function testQuoteOnlyValuesAreNotCopied(): void
    {
        $cartId = $this->getCartId();
        $this->saveQuoteAddressExtended($this->getShippingAddressId($cartId), [
            'address_type' => 'shipping',
            self::QUOTE_COLUMN => 'quote only',
        ]);

        $order = $this->placeOrder($cartId);

        $customerAddressId = (int)$order->getShippingAddress()->getCustomerAddressId();
        $this->assertGreaterThan(0, $customerAddressId);
        $this->expectException(NoSuchEntityException::class);
        $this->getCustomerAddressExtendedRepository()->getByCustomerAddressId($customerAddressId);
    }

    #[
        DataFixture(ProductFixture::class, as: 'product'),
        DataFixture(CustomerFixture::class, as: 'customer'),
        DataFixture(CustomerCartFixture::class, ['customer_id' => '$customer.id$'], as: 'cart'),
        DataFixture(AddProductToCartFixture::class, ['cart_id' => '$cart.id$', 'product_id' => '$product.id$', 'qty' => 1]),
        DataFixture(SetBillingAddressFixture::class, ['cart_id' => '$cart.id$']),
        DataFixture(SetShippingAddressFixture::class, ['cart_id' => '$cart.id$']),
        DataFixture(SetDeliveryMethodFixture::class, ['cart_id' => '$cart.id$']),
        DataFixture(SetPaymentMethodFixture::class, ['cart_id' => '$cart.id$']),
    ]
    public function testAddressesNotSavedInAddressBookAreNotCopied(): void
    {
        $cartId = $this->getCartId();
        $this->saveQuoteAddressExtended($this->getShippingAddressId($cartId), [
            'address_type' => 'shipping',
            self::SHARED_COLUMN => 'shipping value',
        ]);
        $this->saveQuoteAddressExtended($this->getBillingAddressId($cartId), [
            'address_type' => 'billing',
            self::SHARED_COLUMN => 'billing value',
        ]);

        $this->placeOrder($cartId);

        $collection = $this->getCustomerAddressExtendedRepository()->getCollection();
        $customerAddressIds = $this->getCustomerAddressIds($this->getCustomerId());
        if ([] !== $customerAddressIds) {
            $collection->addFieldToFilter('customer_address_id', ['in' => $customerAddressIds]);
            $collection->addFieldToFilter(self::SHARED_COLUMN, ['notnull' => true]);
            $this->assertCount(0, $collection);
            return;
        }

        $this->assertSame([], $customerAddressIds);
    }

    #[
        DataFixture(ProductFixture::class, as: 'product'),
        DataFixture(CustomerFixture::class, as: 'customer'),
        DataFixture(CustomerFixture::class, ['addresses' => [[]]], as: 'otherCustomer'),
        DataFixture(CustomerCartFixture::class, ['customer_id' => '$customer.id$'], as: 'cart'),
        DataFixture(AddProductToCartFixture::class, ['cart_id' => '$cart.id$', 'product_id' => '$product.id$', 'qty' => 1]),
        DataFixture(SetBillingAddressFixture::class, ['cart_id' => '$cart.id$']),
        DataFixture(SetShippingAddressFixture::class, ['cart_id' => '$cart.id$']),
    ]
    public function testAddressOfAnotherCustomerIsNotChanged(): void
    {
        $cartId = $this->getCartId();
        $this->saveQuoteAddressExtended($this->getShippingAddressId($cartId), [
            'address_type' => 'shipping',
            self::SHARED_COLUMN => 'shipping value',
        ]);
        $otherCustomerId = (int)DataFixtureStorageManager::getStorage()->get('otherCustomer')->getId();
        $otherCustomerAddressId = $this->getCustomerAddressIds($otherCustomerId)[0];

        $quote = $this->getQuote($cartId);
        $quote->getShippingAddress()->setCustomerAddressId($otherCustomerAddressId)->setSaveInAddressBook(1);
        Bootstrap::getObjectManager()->create(CopyQuoteAddressExtendedToCustomerAddress::class)
            ->execute(new Observer(['event' => new Event(['quote' => $quote])]));

        $this->expectException(NoSuchEntityException::class);
        $this->getCustomerAddressExtendedRepository()->getByCustomerAddressId($otherCustomerAddressId);
    }

    private function getCustomerId(): int
    {
        return (int)DataFixtureStorageManager::getStorage()->get('customer')->getId();
    }

    private function getCartId(): int
    {
        return (int)DataFixtureStorageManager::getStorage()->get('cart')->getId();
    }
}
