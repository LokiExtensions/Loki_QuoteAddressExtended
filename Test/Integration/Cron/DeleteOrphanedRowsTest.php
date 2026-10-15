<?php

declare(strict_types=1);

namespace Loki\QuoteAddressExtended\Test\Integration\Cron;

use Loki\QuoteAddressExtended\Cron\DeleteOrphanedRows;
use Loki\QuoteAddressExtended\Model\QuoteAddressExtended;
use Loki\QuoteAddressExtended\Test\Integration\Trait\QuoteAddressExtendedTestTrait;
use Magento\Catalog\Test\Fixture\Product as ProductFixture;
use Magento\Checkout\Test\Fixture\SetBillingAddress as SetBillingAddressFixture;
use Magento\Checkout\Test\Fixture\SetDeliveryMethod as SetDeliveryMethodFixture;
use Magento\Checkout\Test\Fixture\SetGuestEmail as SetGuestEmailFixture;
use Magento\Checkout\Test\Fixture\SetPaymentMethod as SetPaymentMethodFixture;
use Magento\Checkout\Test\Fixture\SetShippingAddress as SetShippingAddressFixture;
use Magento\Quote\Test\Fixture\AddProductToCart as AddProductToCartFixture;
use Magento\Quote\Test\Fixture\GuestCart as GuestCartFixture;
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
class DeleteOrphanedRowsTest extends TestCase
{
    use QuoteAddressExtendedTestTrait;

    public function testDeletesOnlyRowsWithoutQuoteAddressAndOrderAddress(): void
    {
        $cartId = (int)DataFixtureStorageManager::getStorage()->get('cart')->getId();
        $shippingAddressId = $this->getShippingAddressId($cartId);

        $linkedToOrder = $this->saveQuoteAddressExtended($shippingAddressId, ['address_type' => 'shipping']);
        $this->placeOrder($cartId);
        $linkedToQuote = $this->saveQuoteAddressExtended($shippingAddressId, ['address_type' => 'shipping']);

        $linkedToOrder = $this->loadQuoteAddressExtended((int)$linkedToOrder->getId());
        $this->assertNull($linkedToOrder->getQuoteAddressId());
        $this->assertNotNull($linkedToOrder->getOrderAddressId());
        $this->assertSame($shippingAddressId, $linkedToQuote->getQuoteAddressId());
        $this->assertNotSame((int)$linkedToOrder->getId(), (int)$linkedToQuote->getId());

        $orphan = Bootstrap::getObjectManager()->create(QuoteAddressExtended::class);
        $orphan->setAddressType('shipping');
        $this->getQuoteAddressExtendedRepository()->save($orphan);
        $this->assertNotEmpty($orphan->getId());

        Bootstrap::getObjectManager()->get(DeleteOrphanedRows::class)->execute();

        $this->assertEmpty($this->loadQuoteAddressExtended((int)$orphan->getId())->getId());
        $this->assertNotEmpty($this->loadQuoteAddressExtended((int)$linkedToOrder->getId())->getId());
        $this->assertNotEmpty($this->loadQuoteAddressExtended((int)$linkedToQuote->getId())->getId());
    }
}
