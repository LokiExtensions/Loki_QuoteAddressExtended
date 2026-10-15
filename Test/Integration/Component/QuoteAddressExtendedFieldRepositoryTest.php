<?php

declare(strict_types=1);

namespace Loki\QuoteAddressExtended\Test\Integration\Component;

use Loki\Components\Component\Component;
use Loki\Components\Component\ComponentInterface;
use Loki\QuoteAddressExtended\Component\QuoteAddressExtendedField\QuoteAddressExtendedFieldRepository;
use Loki\QuoteAddressExtended\Model\ResourceModel\QuoteAddressExtended as QuoteAddressExtendedResource;
use Loki\QuoteAddressExtended\Test\Integration\Trait\QuoteAddressExtendedTestTrait;
use LokiCheckout\Core\Component\Base\Generic\CheckoutContext;
use LokiCheckout\Core\ViewModel\CheckoutState;
use Magento\Catalog\Test\Fixture\Product as ProductFixture;
use Magento\Catalog\Test\Fixture\Virtual as VirtualProductFixture;
use Magento\Checkout\Test\Fixture\SetBillingAddress as SetBillingAddressFixture;
use Magento\Checkout\Test\Fixture\SetShippingAddress as SetShippingAddressFixture;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Ddl\Table;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\LayoutInterface;
use Magento\Quote\Test\Fixture\AddProductToCart as AddProductToCartFixture;
use Magento\Quote\Test\Fixture\GuestCart as GuestCartFixture;
use Magento\TestFramework\Fixture\AppArea;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

#[
    DbIsolation(false),
    AppArea('frontend'),
]
class QuoteAddressExtendedFieldRepositoryTest extends TestCase
{
    use QuoteAddressExtendedTestTrait;

    private const COLUMN = 'loki_integration_test_address_field';

    public static function setUpBeforeClass(): void
    {
        $connection = self::getConnection();
        $table = self::getTable();
        if (false === $connection->tableColumnExists($table, self::COLUMN)) {
            $connection->addColumn($table, self::COLUMN, [
                'type' => Table::TYPE_TEXT,
                'length' => 255,
                'nullable' => true,
                'comment' => 'Temporary integration test column',
            ]);
        }

        $connection->resetDdlCache($table);
    }

    public static function tearDownAfterClass(): void
    {
        $connection = self::getConnection();
        $table = self::getTable();
        $connection->dropColumn($table, self::COLUMN);
        $connection->resetDdlCache($table);
    }

    protected function tearDown(): void
    {
        $quote = $this->getQuote($this->getCartId());
        $quoteAddressIds = [(int)$quote->getShippingAddress()->getId(), (int)$quote->getBillingAddress()->getId()];
        self::getConnection()->delete(self::getTable(), ['quote_address_id IN (?)' => $quoteAddressIds]);
    }

    #[
        DataFixture(ProductFixture::class, as: 'product'),
        DataFixture(GuestCartFixture::class, as: 'cart'),
        DataFixture(AddProductToCartFixture::class, ['cart_id' => '$cart.id$', 'product_id' => '$product.id$']),
        DataFixture(SetBillingAddressFixture::class, ['cart_id' => '$cart.id$']),
        DataFixture(SetShippingAddressFixture::class, ['cart_id' => '$cart.id$']),
    ]
    public function testSaveValueStoresColumnOfShippingAddress(): void
    {
        $repository = $this->createRepository();

        $this->assertSame('', $repository->getValue());

        $repository->saveValue('foobar');

        $this->assertSame('foobar', $repository->getValue());
        $row = $this->getQuoteAddressExtendedRepository()->getByQuoteAddressId(
            $this->getShippingAddressId($this->getCartId())
        );
        $this->assertSame('foobar', $row->getData(self::COLUMN));
        $this->assertSame('shipping', $row->getAddressType());

        $this->expectException(NoSuchEntityException::class);
        $this->getQuoteAddressExtendedRepository()->getByQuoteAddressId(
            $this->getBillingAddressId($this->getCartId())
        );
    }

    #[
        DataFixture(ProductFixture::class, as: 'product'),
        DataFixture(GuestCartFixture::class, as: 'cart'),
        DataFixture(AddProductToCartFixture::class, ['cart_id' => '$cart.id$', 'product_id' => '$product.id$']),
        DataFixture(SetBillingAddressFixture::class, ['cart_id' => '$cart.id$']),
        DataFixture(SetShippingAddressFixture::class, ['cart_id' => '$cart.id$']),
    ]
    public function testSaveValueOverwritesExistingValue(): void
    {
        $repository = $this->createRepository();
        $repository->saveValue('first');
        $repository->saveValue('second');

        $this->assertSame('second', $repository->getValue());
        $this->assertSame(
            1,
            (int)self::getConnection()->fetchOne(
                'SELECT COUNT(*) FROM ' . self::getTable() . ' WHERE quote_address_id = ?',
                [$this->getShippingAddressId($this->getCartId())]
            )
        );
    }

    #[
        DataFixture(ProductFixture::class, as: 'product'),
        DataFixture(GuestCartFixture::class, as: 'cart'),
        DataFixture(AddProductToCartFixture::class, ['cart_id' => '$cart.id$', 'product_id' => '$product.id$']),
        DataFixture(SetBillingAddressFixture::class, ['cart_id' => '$cart.id$']),
        DataFixture(SetShippingAddressFixture::class, ['cart_id' => '$cart.id$']),
    ]
    public function testSaveValueStoresColumnOfBillingAddress(): void
    {
        $repository = $this->createRepository(['address_type' => 'billing']);
        $repository->saveValue('billing value');

        $row = $this->getQuoteAddressExtendedRepository()->getByQuoteAddressId(
            $this->getBillingAddressId($this->getCartId())
        );
        $this->assertSame('billing value', $row->getData(self::COLUMN));
        $this->assertSame('billing', $row->getAddressType());
        $this->assertSame('', $this->createRepository(['address_type' => 'shipping'])->getValue());
    }

    #[
        DataFixture(VirtualProductFixture::class, as: 'product'),
        DataFixture(GuestCartFixture::class, as: 'cart'),
        DataFixture(AddProductToCartFixture::class, ['cart_id' => '$cart.id$', 'product_id' => '$product.id$']),
        DataFixture(SetBillingAddressFixture::class, ['cart_id' => '$cart.id$']),
    ]
    public function testSaveValueUsesBillingAddressOfVirtualQuote(): void
    {
        $this->assertTrue($this->getQuote($this->getCartId())->isVirtual());

        $repository = $this->createRepository();
        $repository->saveValue('virtual value');

        $this->assertSame('virtual value', $repository->getValue());
        $row = $this->getQuoteAddressExtendedRepository()->getByQuoteAddressId(
            $this->getBillingAddressId($this->getCartId())
        );
        $this->assertSame('virtual value', $row->getData(self::COLUMN));
        $this->assertSame('billing', $row->getAddressType());
    }

    #[
        DataFixture(ProductFixture::class, as: 'product'),
        DataFixture(GuestCartFixture::class, as: 'cart'),
        DataFixture(AddProductToCartFixture::class, ['cart_id' => '$cart.id$', 'product_id' => '$product.id$']),
        DataFixture(SetBillingAddressFixture::class, ['cart_id' => '$cart.id$']),
        DataFixture(SetShippingAddressFixture::class, ['cart_id' => '$cart.id$']),
    ]
    public function testSaveValueIgnoresReservedColumns(): void
    {
        $repository = $this->createRepository(['field_name' => 'order_address_id']);
        $repository->saveValue('1');

        $this->expectException(NoSuchEntityException::class);
        $this->getQuoteAddressExtendedRepository()->getByQuoteAddressId(
            $this->getShippingAddressId($this->getCartId())
        );
    }

    private function createRepository(array $blockData = []): QuoteAddressExtendedFieldRepository
    {
        $objectManager = Bootstrap::getObjectManager();
        $cartId = $this->getCartId();
        $checkoutState = $objectManager->get(CheckoutState::class);
        $checkoutState->setQuoteId($cartId);
        $checkoutState->setQuote($this->getQuote($cartId));

        $componentName = 'loki-checkout.customer.' . self::COLUMN;
        $layout = $objectManager->get(LayoutInterface::class);
        if ($layout->getBlock($componentName)) {
            $layout->unsetElement($componentName);
        }

        $layout->createBlock(Template::class, $componentName, ['data' => $blockData]);

        $component = $objectManager->create(Component::class, [
            'name' => $componentName,
            'context' => $objectManager->get(CheckoutContext::class),
            'repositoryClass' => QuoteAddressExtendedFieldRepository::class,
        ]);
        $this->assertInstanceOf(ComponentInterface::class, $component);

        $repository = $objectManager->create(QuoteAddressExtendedFieldRepository::class);
        $repository->setComponent($component);

        return $repository;
    }

    private function getCartId(): int
    {
        return (int)DataFixtureStorageManager::getStorage()->get('cart')->getId();
    }

    private static function getConnection(): AdapterInterface
    {
        return Bootstrap::getObjectManager()->get(ResourceConnection::class)->getConnection();
    }

    private static function getTable(): string
    {
        return Bootstrap::getObjectManager()->get(ResourceConnection::class)
            ->getTableName(QuoteAddressExtendedResource::TABLE_NAME);
    }
}
