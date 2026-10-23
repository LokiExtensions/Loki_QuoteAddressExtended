<?php

declare(strict_types=1);

namespace Loki\QuoteAddressExtended\Test\Integration\Email;

use Loki\QuoteAddressExtended\Test\Integration\Trait\QuoteAddressExtendedTestTrait;
use Magento\Catalog\Test\Fixture\Product as ProductFixture;
use Magento\Checkout\Test\Fixture\SetBillingAddress as SetBillingAddressFixture;
use Magento\Checkout\Test\Fixture\SetDeliveryMethod as SetDeliveryMethodFixture;
use Magento\Checkout\Test\Fixture\SetGuestEmail as SetGuestEmailFixture;
use Magento\Checkout\Test\Fixture\SetPaymentMethod as SetPaymentMethodFixture;
use Magento\Checkout\Test\Fixture\SetShippingAddress as SetShippingAddressFixture;
use Magento\Framework\Filter\Template as TemplateFilter;
use Magento\Quote\Test\Fixture\AddProductToCart as AddProductToCartFixture;
use Magento\Quote\Test\Fixture\GuestCart as GuestCartFixture;
use Magento\Sales\Model\Order\Email\Container\Template as TemplateContainer;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;
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
class TemplateVariableTest extends TestCase
{
    use QuoteAddressExtendedTestTrait;

    private const TEMPLATE = 'shipping={{var loki_shipping_address_extended.address_type}};'
        . 'billing={{var loki_billing_address_extended.address_type}}';

    public function testOrderEmailVariablesRenderColumnValues(): void
    {
        $cartId = $this->getCartId();
        $this->saveQuoteAddressExtended($this->getShippingAddressId($cartId), ['address_type' => 'shipping']);
        $this->saveQuoteAddressExtended($this->getBillingAddressId($cartId), ['address_type' => 'billing']);
        $order = $this->placeOrder($cartId);

        Bootstrap::getObjectManager()->get(OrderSender::class)->send($order, true);

        $this->assertSame('shipping=shipping;billing=billing', $this->renderTemplate());
    }

    public function testInvoiceEmailVariablesRenderColumnValues(): void
    {
        $cartId = $this->getCartId();
        $this->saveQuoteAddressExtended($this->getShippingAddressId($cartId), ['address_type' => 'shipping']);
        $this->saveQuoteAddressExtended($this->getBillingAddressId($cartId), ['address_type' => 'billing']);
        $order = $this->placeOrder($cartId);
        $invoice = $this->createInvoice($order);

        Bootstrap::getObjectManager()->get(InvoiceSender::class)->send($invoice, true);

        $this->assertSame('shipping=shipping;billing=billing', $this->renderTemplate());
    }

    public function testEmailVariablesRenderEmptyWithoutRows(): void
    {
        $order = $this->placeOrder($this->getCartId());

        Bootstrap::getObjectManager()->get(OrderSender::class)->send($order, true);

        $this->assertSame('shipping=;billing=', $this->renderTemplate());
    }

    private function renderTemplate(): string
    {
        $templateVars = Bootstrap::getObjectManager()->get(TemplateContainer::class)->getTemplateVars();
        $filter = Bootstrap::getObjectManager()->create(TemplateFilter::class);
        $filter->setVariables($templateVars);

        return $filter->filter(self::TEMPLATE);
    }

    private function getCartId(): int
    {
        return (int)DataFixtureStorageManager::getStorage()->get('cart')->getId();
    }
}
