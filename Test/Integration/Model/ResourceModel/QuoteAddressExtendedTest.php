<?php

declare(strict_types=1);

namespace Loki\QuoteAddressExtended\Test\Integration\Model\ResourceModel;

use Loki\QuoteAddressExtended\Model\QuoteAddressExtended;
use Loki\QuoteAddressExtended\Model\ResourceModel\QuoteAddressExtended as QuoteAddressExtendedResource;
use Loki\QuoteAddressExtended\Test\Integration\Trait\QuoteAddressExtendedTestTrait;
use Magento\Catalog\Test\Fixture\Product as ProductFixture;
use Magento\Checkout\Test\Fixture\SetBillingAddress as SetBillingAddressFixture;
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
]
class QuoteAddressExtendedTest extends TestCase
{
    use QuoteAddressExtendedTestTrait;

    public function testDeleteOrphanedRowsReturnsNumberOfDeletedRows(): void
    {
        $resource = Bootstrap::getObjectManager()->get(QuoteAddressExtendedResource::class);
        $resource->deleteOrphanedRows();

        $cartId = (int)DataFixtureStorageManager::getStorage()->get('cart')->getId();
        $linked = $this->saveQuoteAddressExtended($this->getShippingAddressId($cartId), ['address_type' => 'shipping']);
        $firstOrphan = $this->saveOrphan();
        $secondOrphan = $this->saveOrphan();

        $this->assertSame(2, $resource->deleteOrphanedRows());
        $this->assertSame(0, $resource->deleteOrphanedRows());

        $this->assertEmpty($this->loadQuoteAddressExtended((int)$firstOrphan->getId())->getId());
        $this->assertEmpty($this->loadQuoteAddressExtended((int)$secondOrphan->getId())->getId());
        $this->assertNotEmpty($this->loadQuoteAddressExtended((int)$linked->getId())->getId());
    }

    private function saveOrphan(): QuoteAddressExtended
    {
        $orphan = Bootstrap::getObjectManager()->create(QuoteAddressExtended::class);
        $orphan->setAddressType('shipping');
        $this->getQuoteAddressExtendedRepository()->save($orphan);
        $this->assertNotEmpty($orphan->getId());

        return $orphan;
    }
}
