<?php

declare(strict_types=1);

namespace Loki\QuoteAddressExtended\Test\Integration\Plugin;

use Loki\QuoteAddressExtended\Test\Integration\Trait\QuoteAddressExtendedTestTrait;
use Loki\QuoteAddressExtended\Test\Integration\Trait\TemporaryColumnTestTrait;
use Magento\Backend\Model\Session\Quote as AdminQuoteSession;
use Magento\Catalog\Test\Fixture\Product as ProductFixture;
use Magento\Checkout\Test\Fixture\SetBillingAddress as SetBillingAddressFixture;
use Magento\Checkout\Test\Fixture\SetDeliveryMethod as SetDeliveryMethodFixture;
use Magento\Checkout\Test\Fixture\SetPaymentMethod as SetPaymentMethodFixture;
use Magento\Checkout\Test\Fixture\SetShippingAddress as SetShippingAddressFixture;
use Magento\Customer\Test\Fixture\Customer as CustomerFixture;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Registry;
use Magento\Quote\Test\Fixture\AddProductToCart as AddProductToCartFixture;
use Magento\Quote\Model\Quote\Address\Rate;
use Magento\Quote\Test\Fixture\CustomerCart as CustomerCartFixture;
use Magento\Sales\Model\AdminOrder\Create;
use Magento\Sales\Model\Order;
use Magento\TestFramework\Fixture\AppArea;
use Magento\TestFramework\Fixture\AppIsolation;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

#[
    DbIsolation(false),
    AppIsolation(true),
    AppArea('adminhtml'),
    DataFixture(ProductFixture::class, as: 'product'),
    DataFixture(CustomerFixture::class, as: 'customer'),
    DataFixture(CustomerCartFixture::class, ['customer_id' => '$customer.id$'], as: 'cart'),
    DataFixture(AddProductToCartFixture::class, ['cart_id' => '$cart.id$', 'product_id' => '$product.id$', 'qty' => 1]),
    DataFixture(SetBillingAddressFixture::class, ['cart_id' => '$cart.id$']),
    DataFixture(SetShippingAddressFixture::class, ['cart_id' => '$cart.id$']),
    DataFixture(SetDeliveryMethodFixture::class, ['cart_id' => '$cart.id$']),
    DataFixture(SetPaymentMethodFixture::class, ['cart_id' => '$cart.id$']),
]
class CopyQuoteAddressExtendedOnInitFromOrderTest extends TestCase
{
    use QuoteAddressExtendedTestTrait;
    use TemporaryColumnTestTrait;

    private const COLUMN = 'loki_integration_test_admin_order_field';

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
        Bootstrap::getObjectManager()->get(AdminQuoteSession::class)->clearStorage();
        $this->deleteNewExtendedRows();
    }

    public function testReorderCopiesRowsToNewQuoteAddresses(): void
    {
        $order = $this->placeOrderWithRows();
        $originalShippingRow = $this->getQuoteAddressExtendedRepository()->getByOrderAddress(
            $order->getShippingAddress()
        );

        $create = $this->initFromOrder($order);
        $quote = $create->getQuote();
        $this->assertNotSame($this->getCartId(), (int)$quote->getId());

        $repository = $this->getQuoteAddressExtendedRepository();
        $shippingRow = $repository->getByQuoteAddressId((int)$quote->getShippingAddress()->getId());
        $this->assertSame('order shipping', $shippingRow->getData(self::COLUMN));
        $this->assertSame('shipping', $shippingRow->getAddressType());
        $this->assertNull($shippingRow->getOrderAddressId());

        $billingRow = $repository->getByQuoteAddressId((int)$quote->getBillingAddress()->getId());
        $this->assertSame('order billing', $billingRow->getData(self::COLUMN));
        $this->assertSame('billing', $billingRow->getAddressType());
        $this->assertNull($billingRow->getOrderAddressId());

        $orderRow = $repository->getByOrderAddress($order->getShippingAddress());
        $this->assertSame((int)$originalShippingRow->getId(), (int)$orderRow->getId());
        $this->assertSame('order shipping', $orderRow->getData(self::COLUMN));
    }

    public function testReorderWithoutRowsCreatesNothing(): void
    {
        $order = $this->placeOrder($this->getCartId());

        $quote = $this->initFromOrder($order)->getQuote();

        $this->expectException(NoSuchEntityException::class);
        $this->getQuoteAddressExtendedRepository()->getByQuoteAddressId((int)$quote->getShippingAddress()->getId());
    }

    public function testAdminCreatedOrderIsLinkedToRows(): void
    {
        $order = $this->placeOrderWithRows();
        $create = $this->initFromOrder($order);
        $quote = $create->getQuote();
        $quoteShippingAddressId = (int)$quote->getShippingAddress()->getId();
        $quoteBillingAddressId = (int)$quote->getBillingAddress()->getId();

        $rate = Bootstrap::getObjectManager()->create(Rate::class);
        $rate->setCode('flatrate_flatrate');
        $rate->setPrice(5);
        $quote->getShippingAddress()->addShippingRate($rate);
        $create->setShippingMethod('flatrate_flatrate');
        $create->setPaymentMethod('checkmo');
        $create->setPaymentData(['method' => 'checkmo']);
        $create->setSendConfirmation(false);
        $newOrder = $create->createOrder();

        $this->assertInstanceOf(Order::class, $newOrder);
        $this->assertNotSame((int)$order->getId(), (int)$newOrder->getId());

        $repository = $this->getQuoteAddressExtendedRepository();
        $shippingRow = $repository->getByOrderAddressId((int)$newOrder->getShippingAddress()->getId());
        $this->assertSame($quoteShippingAddressId, $shippingRow->getQuoteAddressId());
        $this->assertSame('order shipping', $shippingRow->getData(self::COLUMN));

        $billingRow = $repository->getByOrderAddressId((int)$newOrder->getBillingAddress()->getId());
        $this->assertSame($quoteBillingAddressId, $billingRow->getQuoteAddressId());
        $this->assertSame('order billing', $billingRow->getData(self::COLUMN));

        $this->assertSame(
            'order shipping',
            $repository->getByOrderAddress($order->getShippingAddress())->getData(self::COLUMN)
        );
    }

    private function placeOrderWithRows(): Order
    {
        $cartId = $this->getCartId();
        $this->saveQuoteAddressExtended($this->getShippingAddressId($cartId), [
            'address_type' => 'shipping',
            self::COLUMN => 'order shipping',
        ]);
        $this->saveQuoteAddressExtended($this->getBillingAddressId($cartId), [
            'address_type' => 'billing',
            self::COLUMN => 'order billing',
        ]);

        $order = $this->placeOrder($cartId);
        $this->assertSame(
            'order shipping',
            $this->getQuoteAddressExtendedRepository()->getByOrderAddress($order->getShippingAddress())
                ->getData(self::COLUMN)
        );

        return $order;
    }

    private function initFromOrder(Order $order): Create
    {
        $objectManager = Bootstrap::getObjectManager();
        $objectManager->get(Registry::class)->unregister('rule_data');
        $order->setReordered(true);

        $create = $objectManager->create(Create::class);
        $create->initFromOrder($order);

        return $create;
    }

    private function getCartId(): int
    {
        return (int)DataFixtureStorageManager::getStorage()->get('cart')->getId();
    }
}
