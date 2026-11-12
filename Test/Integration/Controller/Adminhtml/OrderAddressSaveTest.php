<?php

declare(strict_types=1);

namespace Loki\QuoteAddressExtended\Test\Integration\Controller\Adminhtml;

use Loki\QuoteAddressExtended\Config\AdminFields;
use Loki\QuoteAddressExtended\Observer\SaveAdminOrderAddressExtended;
use Loki\QuoteAddressExtended\Plugin\AddAdminFieldsToOrderAddressForm;
use Loki\QuoteAddressExtended\Test\Integration\Trait\QuoteAddressExtendedTestTrait;
use Loki\QuoteAddressExtended\Test\Integration\Trait\TemporaryColumnTestTrait;
use Magento\Catalog\Test\Fixture\Product as ProductFixture;
use Magento\Checkout\Test\Fixture\SetBillingAddress as SetBillingAddressFixture;
use Magento\Checkout\Test\Fixture\SetDeliveryMethod as SetDeliveryMethodFixture;
use Magento\Checkout\Test\Fixture\SetGuestEmail as SetGuestEmailFixture;
use Magento\Checkout\Test\Fixture\SetPaymentMethod as SetPaymentMethodFixture;
use Magento\Checkout\Test\Fixture\SetShippingAddress as SetShippingAddressFixture;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\Data\Form\FormKey;
use Magento\Quote\Test\Fixture\AddProductToCart as AddProductToCartFixture;
use Magento\Quote\Test\Fixture\GuestCart as GuestCartFixture;
use Magento\Sales\Api\OrderAddressRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\TestFramework\Fixture\AppArea;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\TestCase\AbstractBackendController;

#[
    DbIsolation(false),
    AppArea('adminhtml'),
]
class OrderAddressSaveTest extends AbstractBackendController
{
    use QuoteAddressExtendedTestTrait;
    use TemporaryColumnTestTrait;

    private const COLUMN = 'loki_integration_test_admin_controller_field';

    protected $resource = 'Magento_Sales::actions_edit';
    protected $uri = 'backend/sales/order/address';

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
        parent::setUp();
        $this->rememberExtendedRows();
        $this->_objectManager->configure([
            AdminFields::class => ['arguments' => ['fields' => [self::COLUMN => 'Controller test field']]],
        ]);
        foreach ([
            AdminFields::class,
            AddAdminFieldsToOrderAddressForm::class,
            SaveAdminOrderAddressExtended::class,
        ] as $className) {
            $this->_objectManager->removeSharedInstance($className);
        }
    }

    protected function tearDown(): void
    {
        $this->deleteNewExtendedRows();
        parent::tearDown();
    }

    #[
        DataFixture(ProductFixture::class, as: 'product'),
        DataFixture(GuestCartFixture::class, as: 'cart'),
        DataFixture(AddProductToCartFixture::class, ['cart_id' => '$cart.id$', 'product_id' => '$product.id$']),
        DataFixture(SetBillingAddressFixture::class, ['cart_id' => '$cart.id$']),
        DataFixture(SetShippingAddressFixture::class, ['cart_id' => '$cart.id$']),
        DataFixture(SetGuestEmailFixture::class, ['cart_id' => '$cart.id$']),
        DataFixture(SetDeliveryMethodFixture::class, ['cart_id' => '$cart.id$']),
        DataFixture(SetPaymentMethodFixture::class, ['cart_id' => '$cart.id$']),
    ]
    public function testAddressFormShowsCurrentValue(): void
    {
        $order = $this->placeOrderWithRow('current admin value');

        $this->dispatch('backend/sales/order/address/address_id/' . $order->getShippingAddress()->getId());

        $body = $this->getResponse()->getBody();
        $this->assertSame(200, $this->getResponse()->getHttpResponseCode());
        $this->assertStringContainsString('Controller test field', $body);
        $this->assertStringContainsString(
            'name="' . AddAdminFieldsToOrderAddressForm::FIELD_NAME . '[' . self::COLUMN . ']"',
            $body
        );
        $this->assertStringContainsString('value="current admin value"', $body);
    }

    #[
        DataFixture(ProductFixture::class, as: 'product'),
        DataFixture(GuestCartFixture::class, as: 'cart'),
        DataFixture(AddProductToCartFixture::class, ['cart_id' => '$cart.id$', 'product_id' => '$product.id$']),
        DataFixture(SetBillingAddressFixture::class, ['cart_id' => '$cart.id$']),
        DataFixture(SetShippingAddressFixture::class, ['cart_id' => '$cart.id$']),
        DataFixture(SetGuestEmailFixture::class, ['cart_id' => '$cart.id$']),
        DataFixture(SetDeliveryMethodFixture::class, ['cart_id' => '$cart.id$']),
        DataFixture(SetPaymentMethodFixture::class, ['cart_id' => '$cart.id$']),
    ]
    public function testAddressSaveStoresPostedValue(): void
    {
        $order = $this->placeOrderWithRow('old admin value');
        $orderAddressId = (int)$order->getShippingAddress()->getId();
        $rowId = (int)$this->getQuoteAddressExtendedRepository()->getByOrderAddressId($orderAddressId)->getId();

        $this->postAddress($orderAddressId, [self::COLUMN => 'new admin value', 'address_type' => 'hacked']);

        $this->assertRedirect($this->stringContains('sales/order/view/order_id/' . $order->getId()));
        $row = $this->getQuoteAddressExtendedRepository()->getByOrderAddressId($orderAddressId);
        $this->assertSame($rowId, (int)$row->getId());
        $this->assertSame('new admin value', $row->getData(self::COLUMN));
        $this->assertSame('shipping', $row->getAddressType());
        $this->assertSame(
            'Changed city',
            Bootstrap::getObjectManager()->create(OrderAddressRepositoryInterface::class)->get($orderAddressId)->getCity()
        );
    }

    #[
        DataFixture(ProductFixture::class, as: 'product'),
        DataFixture(GuestCartFixture::class, as: 'cart'),
        DataFixture(AddProductToCartFixture::class, ['cart_id' => '$cart.id$', 'product_id' => '$product.id$']),
        DataFixture(SetBillingAddressFixture::class, ['cart_id' => '$cart.id$']),
        DataFixture(SetShippingAddressFixture::class, ['cart_id' => '$cart.id$']),
        DataFixture(SetGuestEmailFixture::class, ['cart_id' => '$cart.id$']),
        DataFixture(SetDeliveryMethodFixture::class, ['cart_id' => '$cart.id$']),
        DataFixture(SetPaymentMethodFixture::class, ['cart_id' => '$cart.id$']),
    ]
    public function testAddressSaveCreatesMissingRow(): void
    {
        $order = $this->placeOrder($this->getCartId());
        $orderAddressId = (int)$order->getBillingAddress()->getId();

        $this->postAddress($orderAddressId, [self::COLUMN => 'created admin value']);

        $row = $this->getQuoteAddressExtendedRepository()->getByOrderAddressId($orderAddressId);
        $this->assertSame('created admin value', $row->getData(self::COLUMN));
        $this->assertSame('billing', $row->getAddressType());
    }

    private function postAddress(int $orderAddressId, array $values): void
    {
        $orderAddress = Bootstrap::getObjectManager()->create(OrderAddressRepositoryInterface::class)
            ->get($orderAddressId);
        $postData = [
            'form_key' => $this->_objectManager->get(FormKey::class)->getFormKey(),
            'firstname' => $orderAddress->getFirstname(),
            'lastname' => $orderAddress->getLastname(),
            'street' => $orderAddress->getStreet(),
            'city' => 'Changed city',
            'country_id' => $orderAddress->getCountryId(),
            'region_id' => $orderAddress->getRegionId(),
            'postcode' => $orderAddress->getPostcode(),
            'telephone' => $orderAddress->getTelephone(),
            AddAdminFieldsToOrderAddressForm::FIELD_NAME => $values,
        ];

        $this->getRequest()->setMethod(HttpRequest::METHOD_POST);
        $this->getRequest()->setPostValue($postData);
        $this->dispatch('backend/sales/order/addressSave/address_id/' . $orderAddressId);
    }

    private function placeOrderWithRow(string $value): Order
    {
        $cartId = $this->getCartId();
        $this->saveQuoteAddressExtended($this->getShippingAddressId($cartId), [
            'address_type' => 'shipping',
            self::COLUMN => $value,
        ]);

        return $this->placeOrder($cartId);
    }

    private function getCartId(): int
    {
        return (int)DataFixtureStorageManager::getStorage()->get('cart')->getId();
    }
}
