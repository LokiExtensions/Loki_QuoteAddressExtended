<?php

declare(strict_types=1);

namespace Loki\QuoteAddressExtended\Test\Integration\Plugin;

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
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Data\Form;
use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Registry;
use Magento\Framework\View\LayoutInterface;
use Magento\Quote\Test\Fixture\AddProductToCart as AddProductToCartFixture;
use Magento\Quote\Test\Fixture\GuestCart as GuestCartFixture;
use Magento\Sales\Block\Adminhtml\Order\Address\Form as OrderAddressForm;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Address as OrderAddress;
use Magento\TestFramework\Fixture\AppArea;
use Magento\TestFramework\Fixture\AppIsolation;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

#[
    DbIsolation(false),
    AppIsolation(true),
    AppArea('adminhtml'),
    DataFixture(ProductFixture::class, as: 'product'),
    DataFixture(GuestCartFixture::class, as: 'cart'),
    DataFixture(AddProductToCartFixture::class, ['cart_id' => '$cart.id$', 'product_id' => '$product.id$', 'qty' => 1]),
    DataFixture(SetBillingAddressFixture::class, ['cart_id' => '$cart.id$']),
    DataFixture(SetShippingAddressFixture::class, ['cart_id' => '$cart.id$']),
    DataFixture(SetGuestEmailFixture::class, ['cart_id' => '$cart.id$']),
    DataFixture(SetDeliveryMethodFixture::class, ['cart_id' => '$cart.id$']),
    DataFixture(SetPaymentMethodFixture::class, ['cart_id' => '$cart.id$']),
]
class AddAdminFieldsToOrderAddressFormTest extends TestCase
{
    use QuoteAddressExtendedTestTrait;
    use TemporaryColumnTestTrait;

    private const COLUMN = 'loki_integration_test_admin_field';
    private const OTHER_COLUMN = 'loki_integration_test_admin_other_field';

    public static function setUpBeforeClass(): void
    {
        self::addTemporaryColumn(self::COLUMN, true, false);
        self::addTemporaryColumn(self::OTHER_COLUMN, true, false);
    }

    public static function tearDownAfterClass(): void
    {
        self::dropTemporaryColumn(self::COLUMN, true, false);
        self::dropTemporaryColumn(self::OTHER_COLUMN, true, false);
    }

    protected function setUp(): void
    {
        $this->rememberExtendedRows();
    }

    protected function tearDown(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $objectManager->get(RequestInterface::class)->setParams([
            'address_id' => null,
            AddAdminFieldsToOrderAddressForm::FIELD_NAME => null,
        ]);
        $objectManager->get(Registry::class)->unregister('order_address');
        $this->deleteNewExtendedRows();
    }

    public function testAdminFieldsSkipReservedAndInvalidFields(): void
    {
        $adminFields = $this->createAdminFields([
            self::COLUMN => 'Test field',
            'order_address_id' => 'Order address ID',
            'address_type' => 'Address type',
            'empty_label' => '',
        ]);

        $this->assertSame([self::COLUMN => 'Test field'], $adminFields->getFields());
    }

    public function testFormShowsFieldsWithCurrentValues(): void
    {
        $order = $this->placeOrderWithRow(self::COLUMN, 'current value');
        $form = $this->getFormWithFields((int)$order->getShippingAddress()->getId());

        $fieldset = $form->getElement(AddAdminFieldsToOrderAddressForm::FIELDSET_ID);
        $this->assertNotNull($fieldset);

        $element = $form->getElement(AddAdminFieldsToOrderAddressForm::FIELDSET_ID . '_' . self::COLUMN);
        $this->assertNotNull($element);
        $this->assertSame(AddAdminFieldsToOrderAddressForm::FIELD_NAME . '[' . self::COLUMN . ']', $element->getName());
        $this->assertSame('current value', $element->getValue());
        $this->assertSame('Test field', (string)$element->getLabel());

        $otherElement = $form->getElement(AddAdminFieldsToOrderAddressForm::FIELDSET_ID . '_' . self::OTHER_COLUMN);
        $this->assertNotNull($otherElement);
        $this->assertSame('', $otherElement->getValue());

        $this->assertNull($form->getElement(AddAdminFieldsToOrderAddressForm::FIELDSET_ID . '_order_address_id'));
        $this->assertNotNull($form->getElement('firstname'));
    }

    public function testFormShowsEmptyFieldsForAddressWithoutRow(): void
    {
        $order = $this->placeOrder($this->getCartId());
        $form = $this->getFormWithFields((int)$order->getBillingAddress()->getId());

        $element = $form->getElement(AddAdminFieldsToOrderAddressForm::FIELDSET_ID . '_' . self::COLUMN);
        $this->assertNotNull($element);
        $this->assertSame('', $element->getValue());
    }

    public function testFormIsUnchangedWithoutFields(): void
    {
        $order = $this->placeOrderWithRow(self::COLUMN, 'current value');
        $this->configureAdminFields([]);

        $form = $this->createFormBlock($order->getShippingAddress())->getForm();

        $this->assertNull($form->getElement(AddAdminFieldsToOrderAddressForm::FIELDSET_ID));
        $this->assertNotNull($form->getElement('firstname'));
    }

    public function testFieldsetIsAddedOnlyOnce(): void
    {
        $order = $this->placeOrderWithRow(self::COLUMN, 'current value');
        $this->configureAdminFields([self::COLUMN => 'Test field']);
        $block = $this->createFormBlock($order->getShippingAddress());

        $block->getForm();
        $form = $block->getForm();

        $fieldset = $form->getElement(AddAdminFieldsToOrderAddressForm::FIELDSET_ID);
        $this->assertNotNull($fieldset);
        $this->assertCount(1, $fieldset->getElements());
    }

    public function testSavingCreatesRowWhenMissing(): void
    {
        $order = $this->placeOrder($this->getCartId());
        $orderAddress = $order->getBillingAddress();

        $this->executeSaveObserver((int)$orderAddress->getId(), [self::COLUMN => ' new value ']);

        $row = $this->getQuoteAddressExtendedRepository()->getByOrderAddressId((int)$orderAddress->getId());
        $this->assertSame('new value', $row->getData(self::COLUMN));
        $this->assertSame('billing', $row->getAddressType());
        $this->assertNull($row->getQuoteAddressId());
    }

    public function testSavingEmptyValuesDoesNotCreateRow(): void
    {
        $order = $this->placeOrder($this->getCartId());
        $orderAddressId = (int)$order->getBillingAddress()->getId();

        $this->executeSaveObserver($orderAddressId, [self::COLUMN => ' ', self::OTHER_COLUMN => '']);

        $this->expectException(NoSuchEntityException::class);
        $this->getQuoteAddressExtendedRepository()->getByOrderAddressId($orderAddressId);
    }

    public function testSavingUpdatesExistingRow(): void
    {
        $order = $this->placeOrderWithRow(self::COLUMN, 'old value', [self::OTHER_COLUMN => 'other value']);
        $orderAddressId = (int)$order->getShippingAddress()->getId();
        $rowId = (int)$this->getQuoteAddressExtendedRepository()->getByOrderAddressId($orderAddressId)->getId();

        $this->executeSaveObserver($orderAddressId, [self::COLUMN => 'updated value', self::OTHER_COLUMN => '']);

        $row = $this->getQuoteAddressExtendedRepository()->getByOrderAddressId($orderAddressId);
        $this->assertSame($rowId, (int)$row->getId());
        $this->assertSame('updated value', $row->getData(self::COLUMN));
        $this->assertNull($row->getData(self::OTHER_COLUMN));
        $this->assertSame('shipping', $row->getAddressType());
    }

    public function testSavingIgnoresUndeclaredAndReservedColumns(): void
    {
        $order = $this->placeOrderWithRow(self::COLUMN, 'old value', [self::OTHER_COLUMN => 'other value']);
        $orderAddressId = (int)$order->getShippingAddress()->getId();
        $row = $this->getQuoteAddressExtendedRepository()->getByOrderAddressId($orderAddressId);
        $quoteAddressId = $row->getQuoteAddressId();

        $this->executeSaveObserver($orderAddressId, [
            self::COLUMN => 'updated value',
            self::OTHER_COLUMN => 'hacked value',
            'order_address_id' => '1',
            'quote_address_id' => '1',
            'address_type' => 'hacked',
        ], [self::COLUMN => 'Test field', 'order_address_id' => 'Order address', 'address_type' => 'Type']);

        $row = $this->getQuoteAddressExtendedRepository()->getById((int)$row->getId());
        $this->assertSame('updated value', $row->getData(self::COLUMN));
        $this->assertSame('other value', $row->getData(self::OTHER_COLUMN));
        $this->assertSame($orderAddressId, $row->getOrderAddressId());
        $this->assertSame($quoteAddressId, $row->getQuoteAddressId());
        $this->assertSame('shipping', $row->getAddressType());
    }

    public function testSavingWithoutPostedValuesOrAddressDoesNothing(): void
    {
        $order = $this->placeOrder($this->getCartId());
        $orderAddressId = (int)$order->getShippingAddress()->getId();

        $this->executeSaveObserver(0, [self::COLUMN => 'value']);
        $this->executeSaveObserver(999999999, [self::COLUMN => 'value']);

        $request = Bootstrap::getObjectManager()->get(RequestInterface::class);
        $request->setParams(['address_id' => $orderAddressId, AddAdminFieldsToOrderAddressForm::FIELD_NAME => 'value']);
        $this->createSaveObserver([self::COLUMN => 'Test field'])->execute(
            new Observer(['event' => new Event([])])
        );

        $this->expectException(NoSuchEntityException::class);
        $this->getQuoteAddressExtendedRepository()->getByOrderAddressId($orderAddressId);
    }

    private function getFormWithFields(int $orderAddressId): Form
    {
        $this->configureAdminFields([
            self::COLUMN => 'Test field',
            self::OTHER_COLUMN => 'Other field',
            'order_address_id' => 'Order address',
        ]);
        Bootstrap::getObjectManager()->get(RequestInterface::class)->setParams(['address_id' => $orderAddressId]);
        $orderAddress = Bootstrap::getObjectManager()->create(OrderAddress::class)->load($orderAddressId);

        return $this->createFormBlock($orderAddress)->getForm();
    }

    private function configureAdminFields(array $fields): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $objectManager->configure([AdminFields::class => ['arguments' => ['fields' => $fields]]]);
        $objectManager->removeSharedInstance(AdminFields::class);
        $objectManager->removeSharedInstance(AddAdminFieldsToOrderAddressForm::class);
    }

    private function createFormBlock(OrderAddress $orderAddress): OrderAddressForm
    {
        $registry = Bootstrap::getObjectManager()->get(Registry::class);
        $registry->unregister('order_address');
        $registry->register('order_address', $orderAddress);

        $block = Bootstrap::getObjectManager()->get(LayoutInterface::class)->createBlock(OrderAddressForm::class);
        $this->assertInstanceOf(OrderAddressForm::class, $block);

        return $block;
    }

    private function executeSaveObserver(int $orderAddressId, array $values, ?array $fields = null): void
    {
        Bootstrap::getObjectManager()->get(RequestInterface::class)->setParams([
            'address_id' => $orderAddressId,
            AddAdminFieldsToOrderAddressForm::FIELD_NAME => $values,
        ]);

        $this->createSaveObserver($fields ?? [self::COLUMN => 'Test field', self::OTHER_COLUMN => 'Other field'])
            ->execute(new Observer(['event' => new Event([])]));
    }

    private function createSaveObserver(array $fields): SaveAdminOrderAddressExtended
    {
        return Bootstrap::getObjectManager()->create(
            SaveAdminOrderAddressExtended::class,
            ['adminFields' => $this->createAdminFields($fields)]
        );
    }

    private function createAdminFields(array $fields): AdminFields
    {
        return Bootstrap::getObjectManager()->create(AdminFields::class, ['fields' => $fields]);
    }

    private function placeOrderWithRow(string $column, string $value, array $data = []): Order
    {
        $cartId = $this->getCartId();
        $this->saveQuoteAddressExtended(
            $this->getShippingAddressId($cartId),
            array_merge(['address_type' => 'shipping', $column => $value], $data)
        );

        return $this->placeOrder($cartId);
    }

    private function getCartId(): int
    {
        return (int)DataFixtureStorageManager::getStorage()->get('cart')->getId();
    }
}
