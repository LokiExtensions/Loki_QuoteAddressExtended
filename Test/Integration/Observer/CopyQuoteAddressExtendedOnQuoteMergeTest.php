<?php

declare(strict_types=1);

namespace Loki\QuoteAddressExtended\Test\Integration\Observer;

use Loki\QuoteAddressExtended\Test\Integration\Trait\QuoteAddressExtendedTestTrait;
use Loki\QuoteAddressExtended\Test\Integration\Trait\TemporaryColumnTestTrait;
use Magento\Catalog\Test\Fixture\Product as ProductFixture;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Checkout\Test\Fixture\SetBillingAddress as SetBillingAddressFixture;
use Magento\Checkout\Test\Fixture\SetShippingAddress as SetShippingAddressFixture;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Customer\Test\Fixture\Customer as CustomerFixture;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Test\Fixture\AddProductToCart as AddProductToCartFixture;
use Magento\Quote\Test\Fixture\CustomerCart as CustomerCartFixture;
use Magento\Quote\Test\Fixture\GuestCart as GuestCartFixture;
use Magento\TestFramework\Fixture\AppArea;
use Magento\TestFramework\Fixture\AppIsolation;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

#[
    DbIsolation(false),
    AppArea('frontend'),
]
class CopyQuoteAddressExtendedOnQuoteMergeTest extends TestCase
{
    use QuoteAddressExtendedTestTrait;
    use TemporaryColumnTestTrait;

    private const COLUMN = 'loki_integration_test_merge_field';

    public static function setUpBeforeClass(): void
    {
        self::addTemporaryColumn(self::COLUMN);
    }

    public static function tearDownAfterClass(): void
    {
        self::dropTemporaryColumn(self::COLUMN);
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
        DataFixture(GuestCartFixture::class, as: 'guestCart'),
        DataFixture(AddProductToCartFixture::class, ['cart_id' => '$guestCart.id$', 'product_id' => '$product.id$']),
        DataFixture(SetBillingAddressFixture::class, ['cart_id' => '$guestCart.id$']),
        DataFixture(SetShippingAddressFixture::class, ['cart_id' => '$guestCart.id$']),
        DataFixture(CustomerFixture::class, as: 'customer'),
        DataFixture(CustomerCartFixture::class, ['customer_id' => '$customer.id$'], as: 'customerCart'),
        DataFixture(AddProductToCartFixture::class, ['cart_id' => '$customerCart.id$', 'product_id' => '$product.id$']),
    ]
    public function testMergeCopiesGuestValuesToCustomerQuoteWithoutAddresses(): void
    {
        $guestCartId = $this->getCartId('guestCart');
        $this->saveQuoteAddressExtended($this->getShippingAddressId($guestCartId), [
            'address_type' => 'shipping',
            self::COLUMN => 'guest shipping',
        ]);
        $this->saveQuoteAddressExtended($this->getBillingAddressId($guestCartId), [
            'address_type' => 'billing',
            self::COLUMN => 'guest billing',
        ]);

        $customerQuote = $this->getQuote($this->getCartId('customerCart'));
        $customerQuote->merge($this->getQuote($guestCartId));

        $shippingAddressId = (int)$customerQuote->getShippingAddress()->getId();
        $billingAddressId = (int)$customerQuote->getBillingAddress()->getId();
        $this->assertGreaterThan(0, $shippingAddressId);
        $this->assertGreaterThan(0, $billingAddressId);

        $shippingRow = $this->getQuoteAddressExtendedRepository()->getByQuoteAddressId($shippingAddressId);
        $this->assertSame('guest shipping', $shippingRow->getData(self::COLUMN));
        $this->assertSame('shipping', $shippingRow->getAddressType());

        $billingRow = $this->getQuoteAddressExtendedRepository()->getByQuoteAddressId($billingAddressId);
        $this->assertSame('guest billing', $billingRow->getData(self::COLUMN));
        $this->assertSame('billing', $billingRow->getAddressType());
    }

    #[
        DataFixture(ProductFixture::class, as: 'product'),
        DataFixture(GuestCartFixture::class, as: 'guestCart'),
        DataFixture(AddProductToCartFixture::class, ['cart_id' => '$guestCart.id$', 'product_id' => '$product.id$']),
        DataFixture(SetBillingAddressFixture::class, ['cart_id' => '$guestCart.id$']),
        DataFixture(SetShippingAddressFixture::class, ['cart_id' => '$guestCart.id$']),
        DataFixture(CustomerFixture::class, as: 'customer'),
        DataFixture(CustomerCartFixture::class, ['customer_id' => '$customer.id$'], as: 'customerCart'),
        DataFixture(AddProductToCartFixture::class, ['cart_id' => '$customerCart.id$', 'product_id' => '$product.id$']),
        DataFixture(SetBillingAddressFixture::class, ['cart_id' => '$customerCart.id$']),
        DataFixture(SetShippingAddressFixture::class, ['cart_id' => '$customerCart.id$']),
    ]
    public function testMergeDoesNotCopyToFilledInCustomerAddresses(): void
    {
        $guestCartId = $this->getCartId('guestCart');
        $customerCartId = $this->getCartId('customerCart');
        $this->saveQuoteAddressExtended($this->getShippingAddressId($guestCartId), [
            'address_type' => 'shipping',
            self::COLUMN => 'guest shipping',
        ]);
        $this->saveQuoteAddressExtended($this->getBillingAddressId($guestCartId), [
            'address_type' => 'billing',
            self::COLUMN => 'guest billing',
        ]);
        $customerShippingAddressId = $this->getShippingAddressId($customerCartId);
        $customerBillingAddressId = $this->getBillingAddressId($customerCartId);
        $this->saveQuoteAddressExtended($customerShippingAddressId, [
            'address_type' => 'shipping',
            self::COLUMN => 'customer shipping',
        ]);
        $this->saveQuoteAddressExtended($customerBillingAddressId, ['address_type' => 'billing']);

        $this->getQuote($customerCartId)->merge($this->getQuote($guestCartId));

        $repository = $this->getQuoteAddressExtendedRepository();
        $this->assertSame(
            'customer shipping',
            $repository->getByQuoteAddressId($customerShippingAddressId)->getData(self::COLUMN)
        );
        $this->assertNull($repository->getByQuoteAddressId($customerBillingAddressId)->getData(self::COLUMN));
        $this->assertSame(
            'guest shipping',
            $repository->getByQuoteAddressId($this->getShippingAddressId($guestCartId))->getData(self::COLUMN)
        );
    }

    #[
        DataFixture(ProductFixture::class, as: 'product'),
        DataFixture(GuestCartFixture::class, as: 'guestCart'),
        DataFixture(AddProductToCartFixture::class, ['cart_id' => '$guestCart.id$', 'product_id' => '$product.id$']),
        DataFixture(SetBillingAddressFixture::class, ['cart_id' => '$guestCart.id$']),
        DataFixture(SetShippingAddressFixture::class, ['cart_id' => '$guestCart.id$']),
        DataFixture(CustomerFixture::class, as: 'customer'),
        DataFixture(CustomerCartFixture::class, ['customer_id' => '$customer.id$'], as: 'customerCart'),
        DataFixture(AddProductToCartFixture::class, ['cart_id' => '$customerCart.id$', 'product_id' => '$product.id$']),
        DataFixture(SetBillingAddressFixture::class, ['cart_id' => '$customerCart.id$']),
        DataFixture(SetShippingAddressFixture::class, ['cart_id' => '$customerCart.id$']),
    ]
    public function testMergeWithoutGuestRowsCreatesNothing(): void
    {
        $customerCartId = $this->getCartId('customerCart');

        $this->getQuote($customerCartId)->merge($this->getQuote($this->getCartId('guestCart')));

        $this->expectException(NoSuchEntityException::class);
        $this->getQuoteAddressExtendedRepository()->getByQuoteAddressId($this->getShippingAddressId($customerCartId));
    }

    #[
        AppIsolation(true),
        DataFixture(ProductFixture::class, as: 'product'),
        DataFixture(GuestCartFixture::class, as: 'guestCart'),
        DataFixture(AddProductToCartFixture::class, ['cart_id' => '$guestCart.id$', 'product_id' => '$product.id$']),
        DataFixture(SetBillingAddressFixture::class, ['cart_id' => '$guestCart.id$']),
        DataFixture(SetShippingAddressFixture::class, ['cart_id' => '$guestCart.id$']),
        DataFixture(CustomerFixture::class, as: 'customer'),
        DataFixture(CustomerCartFixture::class, ['customer_id' => '$customer.id$'], as: 'customerCart'),
        DataFixture(AddProductToCartFixture::class, ['cart_id' => '$customerCart.id$', 'product_id' => '$product.id$']),
    ]
    public function testCustomerLoginMergesGuestValues(): void
    {
        $guestCartId = $this->getCartId('guestCart');
        $customerCartId = $this->getCartId('customerCart');
        $this->saveQuoteAddressExtended($this->getShippingAddressId($guestCartId), [
            'address_type' => 'shipping',
            self::COLUMN => 'guest shipping',
        ]);

        $objectManager = Bootstrap::getObjectManager();
        $checkoutSession = $objectManager->get(CheckoutSession::class);
        $checkoutSession->setQuoteId($guestCartId);
        $customerSession = $objectManager->get(CustomerSession::class);
        $customerSession->loginById((int)DataFixtureStorageManager::getStorage()->get('customer')->getId());

        try {
            $this->assertSame($customerCartId, (int)$checkoutSession->getQuoteId());
            $customerShippingAddressId = $this->getShippingAddressId($customerCartId);
            $row = $this->getQuoteAddressExtendedRepository()->getByQuoteAddressId($customerShippingAddressId);
            $this->assertSame('guest shipping', $row->getData(self::COLUMN));
            $this->assertSame('shipping', $row->getAddressType());
        } finally {
            $customerSession->setCustomerId(null);
            $checkoutSession->clearStorage();
        }
    }

    private function getCartId(string $name): int
    {
        return (int)DataFixtureStorageManager::getStorage()->get($name)->getId();
    }
}
