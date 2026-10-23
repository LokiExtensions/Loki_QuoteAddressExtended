<?php

declare(strict_types=1);

namespace Loki\QuoteAddressExtended\Test\Integration\Observer;

use Loki\QuoteAddressExtended\Test\Integration\Trait\QuoteAddressExtendedTestTrait;
use Magento\Catalog\Test\Fixture\Product as ProductFixture;
use Magento\Checkout\Test\Fixture\SetBillingAddress as SetBillingAddressFixture;
use Magento\Checkout\Test\Fixture\SetDeliveryMethod as SetDeliveryMethodFixture;
use Magento\Checkout\Test\Fixture\SetPaymentMethod as SetPaymentMethodFixture;
use Magento\Checkout\Test\Fixture\SetShippingAddress as SetShippingAddressFixture;
use Magento\Customer\Test\Fixture\Customer as CustomerFixture;
use Magento\Quote\Test\Fixture\AddProductToCart as AddProductToCartFixture;
use Magento\Quote\Test\Fixture\CustomerCart as CustomerCartFixture;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Fixture\DbIsolation;
use PHPUnit\Framework\TestCase;

#[
    DbIsolation(true),
    DataFixture(ProductFixture::class, as: 'product'),
    DataFixture(CustomerFixture::class, as: 'customer'),
    DataFixture(CustomerCartFixture::class, ['customer_id' => '$customer.id$'], as: 'cart'),
    DataFixture(AddProductToCartFixture::class, ['cart_id' => '$cart.id$', 'product_id' => '$product.id$', 'qty' => 1]),
    DataFixture(SetBillingAddressFixture::class, ['cart_id' => '$cart.id$']),
    DataFixture(SetShippingAddressFixture::class, ['cart_id' => '$cart.id$']),
    DataFixture(SetDeliveryMethodFixture::class, ['cart_id' => '$cart.id$']),
    DataFixture(SetPaymentMethodFixture::class, ['cart_id' => '$cart.id$']),
]
class LinkCustomerOrderAddressesTest extends TestCase
{
    use QuoteAddressExtendedTestTrait;

    public function testCustomerQuoteLinksRowsToOrderAddresses(): void
    {
        $cartId = $this->getCartId();
        $customerId = (int)DataFixtureStorageManager::getStorage()->get('customer')->getId();
        $this->assertSame($customerId, (int)$this->getQuote($cartId)->getCustomerId());

        $shippingAddressId = $this->getShippingAddressId($cartId);
        $billingAddressId = $this->getBillingAddressId($cartId);
        $shipping = $this->saveQuoteAddressExtended($shippingAddressId, ['address_type' => 'shipping']);
        $billing = $this->saveQuoteAddressExtended($billingAddressId, ['address_type' => 'billing']);

        $order = $this->placeOrder($cartId);
        $this->assertSame($customerId, (int)$order->getCustomerId());

        $shippingRow = $this->loadQuoteAddressExtended((int)$shipping->getId());
        $this->assertSame((int)$order->getShippingAddress()->getId(), $shippingRow->getOrderAddressId());
        $this->assertSame($shippingAddressId, $shippingRow->getQuoteAddressId());

        $billingRow = $this->loadQuoteAddressExtended((int)$billing->getId());
        $this->assertSame((int)$order->getBillingAddress()->getId(), $billingRow->getOrderAddressId());
        $this->assertSame($billingAddressId, $billingRow->getQuoteAddressId());

        $repository = $this->getQuoteAddressExtendedRepository();
        $this->assertSame(
            (int)$shipping->getId(),
            (int)$repository->getByOrderAddress($order->getShippingAddress())->getId()
        );
        $this->assertSame(
            (int)$billing->getId(),
            (int)$repository->getByOrderAddress($order->getBillingAddress())->getId()
        );
    }

    private function getCartId(): int
    {
        return (int)DataFixtureStorageManager::getStorage()->get('cart')->getId();
    }
}
