<?php

declare(strict_types=1);

namespace Loki\QuoteAddressExtended\Test\Integration\Observer;

use Loki\QuoteAddressExtended\Model\QuoteAddressExtended;
use Loki\QuoteAddressExtended\Observer\AddQuoteAddressExtendedToEmailVars;
use Loki\QuoteAddressExtended\Test\Integration\Trait\QuoteAddressExtendedTestTrait;
use Magento\Catalog\Test\Fixture\Product as ProductFixture;
use Magento\Checkout\Test\Fixture\SetBillingAddress as SetBillingAddressFixture;
use Magento\Checkout\Test\Fixture\SetDeliveryMethod as SetDeliveryMethodFixture;
use Magento\Checkout\Test\Fixture\SetGuestEmail as SetGuestEmailFixture;
use Magento\Checkout\Test\Fixture\SetPaymentMethod as SetPaymentMethodFixture;
use Magento\Checkout\Test\Fixture\SetShippingAddress as SetShippingAddressFixture;
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
class AddQuoteAddressExtendedToEmailVarsTest extends TestCase
{
    use QuoteAddressExtendedTestTrait;

    public function testOrderEmailContainsShippingAndBillingVariables(): void
    {
        $cartId = $this->getCartId();
        $shipping = $this->saveQuoteAddressExtended($this->getShippingAddressId($cartId), ['address_type' => 'shipping']);
        $billing = $this->saveQuoteAddressExtended($this->getBillingAddressId($cartId), ['address_type' => 'billing']);
        $order = $this->placeOrder($cartId);

        Bootstrap::getObjectManager()->get(OrderSender::class)->send($order, true);

        $this->assertTemplateVariable(AddQuoteAddressExtendedToEmailVars::SHIPPING_TEMPLATE_VARIABLE, (int)$shipping->getId());
        $this->assertTemplateVariable(AddQuoteAddressExtendedToEmailVars::BILLING_TEMPLATE_VARIABLE, (int)$billing->getId());
    }

    public function testInvoiceEmailContainsShippingAndBillingVariables(): void
    {
        $cartId = $this->getCartId();
        $shipping = $this->saveQuoteAddressExtended($this->getShippingAddressId($cartId), ['address_type' => 'shipping']);
        $billing = $this->saveQuoteAddressExtended($this->getBillingAddressId($cartId), ['address_type' => 'billing']);
        $order = $this->placeOrder($cartId);
        $invoice = $this->createInvoice($order);

        Bootstrap::getObjectManager()->get(InvoiceSender::class)->send($invoice, true);

        $this->assertTemplateVariable(AddQuoteAddressExtendedToEmailVars::SHIPPING_TEMPLATE_VARIABLE, (int)$shipping->getId());
        $this->assertTemplateVariable(AddQuoteAddressExtendedToEmailVars::BILLING_TEMPLATE_VARIABLE, (int)$billing->getId());
    }

    public function testOrderEmailFindsRowNotLinkedYet(): void
    {
        $cartId = $this->getCartId();
        $order = $this->placeOrder($cartId);
        $shipping = $this->saveQuoteAddressExtended($this->getShippingAddressId($cartId), ['address_type' => 'shipping']);

        Bootstrap::getObjectManager()->get(OrderSender::class)->send($order, true);

        $this->assertTemplateVariable(AddQuoteAddressExtendedToEmailVars::SHIPPING_TEMPLATE_VARIABLE, (int)$shipping->getId());
        $this->assertArrayNotHasKey(
            AddQuoteAddressExtendedToEmailVars::BILLING_TEMPLATE_VARIABLE,
            $this->getTemplateVars()
        );
    }

    public function testOrderEmailWithoutRowsHasNoVariables(): void
    {
        $order = $this->placeOrder($this->getCartId());

        Bootstrap::getObjectManager()->get(OrderSender::class)->send($order, true);

        $templateVars = $this->getTemplateVars();
        $this->assertArrayHasKey('order', $templateVars);
        $this->assertArrayNotHasKey(AddQuoteAddressExtendedToEmailVars::SHIPPING_TEMPLATE_VARIABLE, $templateVars);
        $this->assertArrayNotHasKey(AddQuoteAddressExtendedToEmailVars::BILLING_TEMPLATE_VARIABLE, $templateVars);
    }

    private function assertTemplateVariable(string $variable, int $expectedEntityId): void
    {
        $templateVars = $this->getTemplateVars();

        $this->assertArrayHasKey($variable, $templateVars);
        $quoteAddressExtended = $templateVars[$variable];
        $this->assertInstanceOf(QuoteAddressExtended::class, $quoteAddressExtended);
        $this->assertSame($expectedEntityId, (int)$quoteAddressExtended->getId());
    }

    private function getTemplateVars(): array
    {
        return Bootstrap::getObjectManager()->get(TemplateContainer::class)->getTemplateVars();
    }

    private function getCartId(): int
    {
        return (int)DataFixtureStorageManager::getStorage()->get('cart')->getId();
    }
}
