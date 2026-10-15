<?php

declare(strict_types=1);

namespace Loki\QuoteAddressExtended\Test\Integration\ViewModel\Adminhtml;

use Loki\QuoteAddressExtended\Test\Integration\Trait\QuoteAddressExtendedTestTrait;
use Loki\QuoteAddressExtended\ViewModel\Adminhtml\OrderAddressExtendedView;
use Magento\Catalog\Test\Fixture\Product as ProductFixture;
use Magento\Checkout\Test\Fixture\SetBillingAddress as SetBillingAddressFixture;
use Magento\Checkout\Test\Fixture\SetDeliveryMethod as SetDeliveryMethodFixture;
use Magento\Checkout\Test\Fixture\SetGuestEmail as SetGuestEmailFixture;
use Magento\Checkout\Test\Fixture\SetPaymentMethod as SetPaymentMethodFixture;
use Magento\Checkout\Test\Fixture\SetShippingAddress as SetShippingAddressFixture;
use Magento\Framework\App\RequestInterface;
use Magento\Quote\Test\Fixture\AddProductToCart as AddProductToCartFixture;
use Magento\Quote\Test\Fixture\GuestCart as GuestCartFixture;
use Magento\Sales\Model\Order;
use Magento\TestFramework\Fixture\AppArea;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

#[
    DbIsolation(true),
    AppArea('frontend'),
    DataFixture(ProductFixture::class, as: 'product'),
    DataFixture(GuestCartFixture::class, as: 'cart'),
    DataFixture(AddProductToCartFixture::class, ['cart_id' => '$cart.id$', 'product_id' => '$product.id$', 'qty' => 1]),
    DataFixture(SetBillingAddressFixture::class, ['cart_id' => '$cart.id$']),
    DataFixture(SetShippingAddressFixture::class, ['cart_id' => '$cart.id$']),
    DataFixture(SetGuestEmailFixture::class, ['cart_id' => '$cart.id$']),
    DataFixture(SetDeliveryMethodFixture::class, ['cart_id' => '$cart.id$']),
    DataFixture(SetPaymentMethodFixture::class, ['cart_id' => '$cart.id$']),
]
class OrderAddressExtendedViewTest extends TestCase
{
    use QuoteAddressExtendedTestTrait;

    public function testReturnsValuesForOrderView(): void
    {
        $order = $this->placeOrderWithRows();

        $viewModel = $this->createViewModel(['order_id' => $order->getId()]);

        $this->assertSame('shipping', $viewModel->getValue('address_type'));
        $this->assertSame('shipping', $viewModel->getValue('address_type', 'shipping'));
        $this->assertSame('billing', $viewModel->getValue('address_type', 'billing'));
    }

    public function testReturnsValuesForInvoiceView(): void
    {
        $order = $this->placeOrderWithRows();
        $invoice = $this->createInvoice($order);

        $viewModel = $this->createViewModel(['invoice_id' => $invoice->getEntityId()]);

        $this->assertSame('shipping', $viewModel->getValue('address_type'));
        $this->assertSame('billing', $viewModel->getValue('address_type', 'billing'));
    }

    public function testReturnsEmptyStringForUnknownOrderAndInvoice(): void
    {
        $this->placeOrderWithRows();

        $this->assertSame('', $this->createViewModel(['order_id' => 999999999])->getValue('address_type'));
        $this->assertSame('', $this->createViewModel(['invoice_id' => 999999999])->getValue('address_type'));
        $this->assertSame('', $this->createViewModel([])->getValue('address_type'));
    }

    private function placeOrderWithRows(): Order
    {
        $cartId = (int)DataFixtureStorageManager::getStorage()->get('cart')->getId();
        $this->saveQuoteAddressExtended($this->getShippingAddressId($cartId), ['address_type' => 'shipping']);
        $this->saveQuoteAddressExtended($this->getBillingAddressId($cartId), ['address_type' => 'billing']);

        return $this->placeOrder($cartId);
    }

    private function createViewModel(array $params): OrderAddressExtendedView
    {
        $objectManager = Bootstrap::getObjectManager();
        $request = $objectManager->get(RequestInterface::class);
        $request->setParams(['order_id' => null, 'invoice_id' => null]);
        $request->setParams($params);

        return $objectManager->create(OrderAddressExtendedView::class);
    }
}
