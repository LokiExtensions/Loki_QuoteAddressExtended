<?php

declare(strict_types=1);

namespace Loki\QuoteAddressExtended\Test\Integration\ViewModel;

use Loki\QuoteAddressExtended\Test\Integration\Trait\QuoteAddressExtendedTestTrait;
use Loki\QuoteAddressExtended\ViewModel\CheckoutQuoteAddressExtendedView;
use LokiCheckout\Core\ViewModel\CheckoutState;
use Magento\Catalog\Test\Fixture\Product as ProductFixture;
use Magento\Catalog\Test\Fixture\Virtual as VirtualProductFixture;
use Magento\Checkout\Test\Fixture\SetBillingAddress as SetBillingAddressFixture;
use Magento\Checkout\Test\Fixture\SetShippingAddress as SetShippingAddressFixture;
use Magento\Quote\Test\Fixture\AddProductToCart as AddProductToCartFixture;
use Magento\Quote\Test\Fixture\GuestCart as GuestCartFixture;
use Magento\TestFramework\Fixture\AppArea;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

#[
    DbIsolation(true),
    AppArea('frontend'),
]
class CheckoutQuoteAddressExtendedViewTest extends TestCase
{
    use QuoteAddressExtendedTestTrait;

    #[
        DataFixture(ProductFixture::class, as: 'product'),
        DataFixture(GuestCartFixture::class, as: 'cart'),
        DataFixture(AddProductToCartFixture::class, ['cart_id' => '$cart.id$', 'product_id' => '$product.id$']),
        DataFixture(SetBillingAddressFixture::class, ['cart_id' => '$cart.id$']),
        DataFixture(SetShippingAddressFixture::class, ['cart_id' => '$cart.id$']),
    ]
    public function testReturnsValuesOfShippingAndBillingAddress(): void
    {
        $cartId = $this->getCartId();
        $this->saveQuoteAddressExtended($this->getShippingAddressId($cartId), ['address_type' => 'shipping']);
        $this->saveQuoteAddressExtended($this->getBillingAddressId($cartId), ['address_type' => 'billing']);

        $viewModel = $this->createViewModel($cartId);

        $this->assertSame('shipping', $viewModel->getValue('address_type'));
        $this->assertSame('shipping', $viewModel->getValue('address_type', 'shipping'));
        $this->assertSame('billing', $viewModel->getValue('address_type', 'billing'));
        $this->assertSame('', $viewModel->getValue('unknown_column'));
    }

    #[
        DataFixture(ProductFixture::class, as: 'product'),
        DataFixture(GuestCartFixture::class, as: 'cart'),
        DataFixture(AddProductToCartFixture::class, ['cart_id' => '$cart.id$', 'product_id' => '$product.id$']),
        DataFixture(SetBillingAddressFixture::class, ['cart_id' => '$cart.id$']),
        DataFixture(SetShippingAddressFixture::class, ['cart_id' => '$cart.id$']),
    ]
    public function testReturnsEmptyStringWithoutRows(): void
    {
        $viewModel = $this->createViewModel($this->getCartId());

        $this->assertSame('', $viewModel->getValue('address_type'));
        $this->assertSame('', $viewModel->getValue('address_type', 'billing'));
    }

    #[
        DataFixture(VirtualProductFixture::class, as: 'product'),
        DataFixture(GuestCartFixture::class, as: 'cart'),
        DataFixture(AddProductToCartFixture::class, ['cart_id' => '$cart.id$', 'product_id' => '$product.id$']),
        DataFixture(SetBillingAddressFixture::class, ['cart_id' => '$cart.id$']),
    ]
    public function testAutoUsesBillingAddressOfVirtualQuote(): void
    {
        $cartId = $this->getCartId();
        $this->assertTrue($this->getQuote($cartId)->isVirtual());
        $this->saveQuoteAddressExtended($this->getBillingAddressId($cartId), ['address_type' => 'billing']);

        $this->assertSame('billing', $this->createViewModel($cartId)->getValue('address_type'));
    }

    private function createViewModel(int $cartId): CheckoutQuoteAddressExtendedView
    {
        $objectManager = Bootstrap::getObjectManager();
        $checkoutState = $objectManager->get(CheckoutState::class);
        $checkoutState->setQuoteId($cartId);
        $checkoutState->setQuote($this->getQuote($cartId));

        return $objectManager->create(CheckoutQuoteAddressExtendedView::class);
    }

    private function getCartId(): int
    {
        return (int)DataFixtureStorageManager::getStorage()->get('cart')->getId();
    }
}
