<?php

declare(strict_types=1);

namespace Loki\QuoteAddressExtended\Test\Integration\Observer;

use Loki\QuoteAddressExtended\Test\Integration\Trait\QuoteAddressExtendedTestTrait;
use Magento\Catalog\Test\Fixture\Product as ProductFixture;
use Magento\Checkout\Test\Fixture\SetBillingAddress as SetBillingAddressFixture;
use Magento\Checkout\Test\Fixture\SetDeliveryMethod as SetDeliveryMethodFixture;
use Magento\Checkout\Test\Fixture\SetGuestEmail as SetGuestEmailFixture;
use Magento\Checkout\Test\Fixture\SetPaymentMethod as SetPaymentMethodFixture;
use Magento\Checkout\Test\Fixture\SetShippingAddress as SetShippingAddressFixture;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Registry;
use Magento\Quote\Test\Fixture\AddProductToCart as AddProductToCartFixture;
use Magento\Quote\Test\Fixture\GuestCart as GuestCartFixture;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

#[
    DbIsolation(true),
    DataFixture(ProductFixture::class, as: 'product'),
    DataFixture(GuestCartFixture::class, as: 'cart'),
    DataFixture(AddProductToCartFixture::class, ['cart_id' => '$cart.id$', 'product_id' => '$product.id$', 'qty' => 1]),
    DataFixture(SetBillingAddressFixture::class, ['cart_id' => '$cart.id$']),
    DataFixture(SetShippingAddressFixture::class, ['cart_id' => '$cart.id$']),
    DataFixture(SetGuestEmailFixture::class, ['cart_id' => '$cart.id$']),
    DataFixture(SetDeliveryMethodFixture::class, ['cart_id' => '$cart.id$']),
    DataFixture(SetPaymentMethodFixture::class, ['cart_id' => '$cart.id$']),
]
class LinkOrderAddressesToQuoteAddressExtendedTest extends TestCase
{
    use QuoteAddressExtendedTestTrait;

    public function testPlacingOrderLinksRowsToOrderAddresses(): void
    {
        $cartId = $this->getCartId();
        $shippingAddressId = $this->getShippingAddressId($cartId);
        $billingAddressId = $this->getBillingAddressId($cartId);
        $shipping = $this->saveQuoteAddressExtended($shippingAddressId, ['address_type' => 'shipping']);
        $billing = $this->saveQuoteAddressExtended($billingAddressId, ['address_type' => 'billing']);

        $order = $this->placeOrder($cartId);

        $shippingRow = $this->loadQuoteAddressExtended((int)$shipping->getId());
        $this->assertSame((int)$order->getShippingAddress()->getId(), $shippingRow->getOrderAddressId());
        $this->assertSame($shippingAddressId, $shippingRow->getQuoteAddressId());

        $billingRow = $this->loadQuoteAddressExtended((int)$billing->getId());
        $this->assertSame((int)$order->getBillingAddress()->getId(), $billingRow->getOrderAddressId());
        $this->assertSame($billingAddressId, $billingRow->getQuoteAddressId());
    }

    public function testPlacingOrderWithoutRowsCreatesNothing(): void
    {
        $order = $this->placeOrder($this->getCartId());

        $this->expectException(NoSuchEntityException::class);
        $this->getQuoteAddressExtendedRepository()->getByOrderAddressId((int)$order->getShippingAddress()->getId());
    }

    public function testChangingValueAfterOrderKeepsValueOfOrder(): void
    {
        $cartId = $this->getCartId();
        $shippingAddressId = $this->getShippingAddressId($cartId);
        $first = $this->saveQuoteAddressExtended($shippingAddressId, ['address_type' => 'shipping']);
        $order = $this->placeOrder($cartId);

        $second = $this->saveQuoteAddressExtended($shippingAddressId, ['address_type' => 'changed']);

        $this->assertNotSame((int)$first->getId(), (int)$second->getId());
        $this->assertNull($second->getOrderAddressId());

        $orderRow = $this->getQuoteAddressExtendedRepository()->getByOrderAddress($order->getShippingAddress());
        $this->assertSame((int)$first->getId(), (int)$orderRow->getId());
        $this->assertSame('shipping', $orderRow->getAddressType());
        $this->assertNull($orderRow->getQuoteAddressId());

        $quoteRow = $this->getQuoteAddressExtendedRepository()->getByQuoteAddressId($shippingAddressId);
        $this->assertSame((int)$second->getId(), (int)$quoteRow->getId());
        $this->assertSame('changed', $quoteRow->getAddressType());
    }

    public function testRestoredQuoteKeepsDataOfFirstOrder(): void
    {
        $cartId = $this->getCartId();
        $shippingAddressId = $this->getShippingAddressId($cartId);
        $first = $this->saveQuoteAddressExtended($shippingAddressId, ['address_type' => 'shipping']);
        $firstOrder = $this->placeOrder($cartId);

        $this->restoreQuote($cartId);
        $secondOrder = $this->placeOrder($cartId);

        $firstOrderAddressId = (int)$firstOrder->getShippingAddress()->getId();
        $secondOrderAddressId = (int)$secondOrder->getShippingAddress()->getId();
        $this->assertNotSame($firstOrderAddressId, $secondOrderAddressId);

        $firstRow = $this->getQuoteAddressExtendedRepository()->getByOrderAddressId($firstOrderAddressId);
        $this->assertSame((int)$first->getId(), (int)$firstRow->getId());
        $this->assertNull($firstRow->getQuoteAddressId());

        $secondRow = $this->getQuoteAddressExtendedRepository()->getByOrderAddressId($secondOrderAddressId);
        $this->assertNotSame((int)$first->getId(), (int)$secondRow->getId());
        $this->assertSame($shippingAddressId, $secondRow->getQuoteAddressId());
        $this->assertSame('shipping', $secondRow->getAddressType());
    }

    public function testDeletingOrderDeletesLinkedRows(): void
    {
        $cartId = $this->getCartId();
        $shipping = $this->saveQuoteAddressExtended($this->getShippingAddressId($cartId), ['address_type' => 'shipping']);
        $order = $this->placeOrder($cartId);
        $this->assertNotNull($this->loadQuoteAddressExtended((int)$shipping->getId())->getOrderAddressId());

        $registry = Bootstrap::getObjectManager()->get(Registry::class);
        $registry->unregister('isSecureArea');
        $registry->register('isSecureArea', true);
        Bootstrap::getObjectManager()->get(OrderRepositoryInterface::class)->delete($order);
        $registry->unregister('isSecureArea');

        $this->assertEmpty($this->loadQuoteAddressExtended((int)$shipping->getId())->getId());
    }

    private function getCartId(): int
    {
        return (int)DataFixtureStorageManager::getStorage()->get('cart')->getId();
    }
}
