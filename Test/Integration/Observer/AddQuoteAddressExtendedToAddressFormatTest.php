<?php

declare(strict_types=1);

namespace Loki\QuoteAddressExtended\Test\Integration\Observer;

use Loki\QuoteAddressExtended\Observer\AddQuoteAddressExtendedToAddressFormat;
use Loki\QuoteAddressExtended\Test\Integration\Trait\QuoteAddressExtendedTestTrait;
use Loki\QuoteAddressExtended\Test\Integration\Trait\TemporaryColumnTestTrait;
use Magento\Catalog\Test\Fixture\Product as ProductFixture;
use Magento\Checkout\Test\Fixture\SetBillingAddress as SetBillingAddressFixture;
use Magento\Checkout\Test\Fixture\SetDeliveryMethod as SetDeliveryMethodFixture;
use Magento\Checkout\Test\Fixture\SetGuestEmail as SetGuestEmailFixture;
use Magento\Checkout\Test\Fixture\SetPaymentMethod as SetPaymentMethodFixture;
use Magento\Checkout\Test\Fixture\SetShippingAddress as SetShippingAddressFixture;
use Magento\Customer\Model\Address\Config as AddressConfig;
use Magento\Framework\App\Config\MutableScopeConfigInterface;
use Magento\Quote\Test\Fixture\AddProductToCart as AddProductToCartFixture;
use Magento\Quote\Test\Fixture\GuestCart as GuestCartFixture;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Address\Renderer;
use Magento\TestFramework\Fixture\AppArea;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

#[
    DbIsolation(false),
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
class AddQuoteAddressExtendedToAddressFormatTest extends TestCase
{
    use QuoteAddressExtendedTestTrait;
    use TemporaryColumnTestTrait;

    private const COLUMN = 'loki_integration_test_format_field';
    private const TEMPLATES = [
        'customer/address_templates/html' => '{{var firstname}}<br />[{{var loki_integration_test_format_field}}]'
            . '{{depend loki_integration_test_format_field}}<br />Has value{{/depend}}'
            . '<br />[{{var order_address_id}}][{{var created_at}}]',
        'customer/address_templates/pdf' => '{{var firstname}}|[{{var loki_integration_test_format_field}}]|',
    ];

    private array $originalTemplates = [];

    public static function setUpBeforeClass(): void
    {
        self::addTemporaryColumn(self::COLUMN, true, false);
    }

    public static function tearDownAfterClass(): void
    {
        self::dropTemporaryColumn(self::COLUMN, true, false);
    }

    protected function setUp(): void
    {
        $this->rememberExtendedRows();
        $scopeConfig = Bootstrap::getObjectManager()->get(MutableScopeConfigInterface::class);
        foreach (self::TEMPLATES as $path => $template) {
            $this->originalTemplates[$path] = $scopeConfig->getValue($path, 'store', 'default');
            $scopeConfig->setValue($path, $template, 'store', 'default');
        }
    }

    protected function tearDown(): void
    {
        $scopeConfig = Bootstrap::getObjectManager()->get(MutableScopeConfigInterface::class);
        foreach ($this->originalTemplates as $path => $template) {
            $scopeConfig->setValue($path, $template, 'store', 'default');
        }

        $this->originalTemplates = [];
        $this->deleteNewExtendedRows();
    }

    public function testHtmlFormatAddsExtendedValueToAddress(): void
    {
        $order = $this->placeOrderWithRows();
        $shippingAddress = $order->getShippingAddress();
        $billingAddress = $order->getBillingAddress();
        $hadDataChanges = $shippingAddress->hasDataChanges();

        $shippingHtml = (string)$this->createRenderer()->format($shippingAddress, 'html');
        $this->createRenderer()->format($billingAddress, 'html');

        $this->assertNull($shippingAddress->getData(self::COLUMN));
        $this->assertSame($hadDataChanges, $shippingAddress->hasDataChanges());
        $this->assertStringContainsString((string)$shippingAddress->getFirstname(), $shippingHtml);
        $this->assertStringContainsString('[shipping extended]<br />Has value', $shippingHtml);
        $this->assertStringContainsString(
            '[billing extended]',
            (string)$this->createRenderer()->format($billingAddress, 'html')
        );
    }

    public function testPdfFormatAddsExtendedValueToAddress(): void
    {
        $order = $this->placeOrderWithRows();
        $shippingAddress = $order->getShippingAddress();

        $pdf = (string)$this->createRenderer()->format($shippingAddress, 'pdf');

        $this->assertSame(
            [self::COLUMN => 'shipping extended'],
            $shippingAddress->getData(AddQuoteAddressExtendedToAddressFormat::DATA_KEY)
        );
        $this->assertStringContainsString((string)$shippingAddress->getFirstname() . '|[shipping extended]|', $pdf);
    }

    public function testReservedColumnsAreNotAdded(): void
    {
        $order = $this->placeOrderWithRows();
        $shippingAddress = $order->getShippingAddress();

        $html = (string)$this->createRenderer()->format($shippingAddress, 'html');

        $this->assertStringContainsString('[][]', $html);
        $this->assertNull($shippingAddress->getData('order_address_id'));
        $this->assertNull($shippingAddress->getData('created_at'));
        $this->assertSame('shipping', $shippingAddress->getAddressType());
    }

    public function testHtmlValueIsEscaped(): void
    {
        $cartId = $this->getCartId();
        $this->saveQuoteAddressExtended($this->getShippingAddressId($cartId), [
            'address_type' => 'shipping',
            self::COLUMN => '<script>alert(1)</script>',
        ]);
        $order = $this->placeOrder($cartId);

        $html = (string)$this->createRenderer()->format($order->getShippingAddress(), 'html');

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function testExistingOrderAddressDataIsNotOverwritten(): void
    {
        $order = $this->placeOrderWithRows();
        $shippingAddress = $order->getShippingAddress();
        $shippingAddress->setData(self::COLUMN, 'order address value');

        $this->createRenderer()->format($shippingAddress, 'html');

        $this->assertSame('order address value', $shippingAddress->getData(self::COLUMN));
    }

    public function testAddressWithoutRowRendersEmptyValue(): void
    {
        $order = $this->placeOrder($this->getCartId());

        $shippingAddress = $order->getShippingAddress();
        $html = (string)$this->createRenderer()->format($shippingAddress, 'html');

        $this->assertNull($shippingAddress->getData(self::COLUMN));
        $this->assertStringContainsString('[]', $html);
        $this->assertStringNotContainsString('Has value', $html);
        $this->assertStringContainsString((string)$shippingAddress->getFirstname(), $html);
    }

    private function placeOrderWithRows(): Order
    {
        $cartId = $this->getCartId();
        $this->saveQuoteAddressExtended($this->getShippingAddressId($cartId), [
            'address_type' => 'shipping',
            self::COLUMN => 'shipping extended',
        ]);
        $this->saveQuoteAddressExtended($this->getBillingAddressId($cartId), [
            'address_type' => 'billing',
            self::COLUMN => 'billing extended',
        ]);

        return $this->placeOrder($cartId);
    }

    private function createRenderer(): Renderer
    {
        $objectManager = Bootstrap::getObjectManager();

        return $objectManager->create(Renderer::class, [
            'addressConfig' => $objectManager->create(AddressConfig::class),
        ]);
    }

    private function getCartId(): int
    {
        return (int)DataFixtureStorageManager::getStorage()->get('cart')->getId();
    }
}
