<?php

declare(strict_types=1);

namespace Loki\QuoteAddressExtended\Test\Integration;

use Loki\Components\Config\XmlConfig;
use Loki\QuoteAddressExtended\Component\QuoteAddressExtendedField\QuoteAddressExtendedFieldRepository;
use Loki\QuoteAddressExtended\Component\QuoteAddressExtendedField\QuoteAddressExtendedFieldViewModel;
use Loki\QuoteAddressExtended\Cron\DeleteOrphanedRows;
use Loki\QuoteAddressExtended\Observer\AddQuoteAddressExtendedToAddressFormat;
use Loki\QuoteAddressExtended\Observer\AddQuoteAddressExtendedToEmailVars;
use Loki\QuoteAddressExtended\Observer\CopyCustomerAddressExtendedToQuoteAddress;
use Loki\QuoteAddressExtended\Observer\CopyQuoteAddressExtendedOnQuoteMerge;
use Loki\QuoteAddressExtended\Observer\CopyQuoteAddressExtendedToCustomerAddress;
use Loki\QuoteAddressExtended\Observer\LinkOrderAddressesToQuoteAddressExtended;
use Loki\QuoteAddressExtended\Observer\SaveAdminOrderAddressExtended;
use Loki\QuoteAddressExtended\Plugin\AddAdminFieldsToOrderAddressForm;
use Loki\QuoteAddressExtended\Plugin\CopyQuoteAddressExtendedOnInitFromOrder;
use LokiCheckout\Core\Config\LokiCheckoutModules;
use Magento\Cron\Model\ConfigInterface as CronConfig;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Event\Config\Reader as EventConfigReader;
use Magento\Framework\Event\ConfigInterface as EventConfig;
use Magento\Framework\ObjectManager\Config\Reader\Dom as DiConfigReader;
use Magento\Sales\Block\Adminhtml\Order\Address\Form as OrderAddressForm;
use Magento\Sales\Model\AdminOrder\Create as AdminOrderCreate;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use Yireo\IntegrationTestHelper\Test\Integration\Traits\AssertModuleIsEnabled;
use Yireo\IntegrationTestHelper\Test\Integration\Traits\AssertModuleIsRegistered;

class ModuleTest extends TestCase
{
    use AssertModuleIsEnabled;
    use AssertModuleIsRegistered;

    public function testModule(): void
    {
        $this->assertModuleIsEnabled('Loki_QuoteAddressExtended');
        $this->assertModuleIsRegistered('Loki_QuoteAddressExtended');
    }

    public function testTable(): void
    {
        $resource = Bootstrap::getObjectManager()->get(ResourceConnection::class);
        $connection = $resource->getConnection();
        $table = $resource->getTableName('loki_quote_address_extended');

        $this->assertTrue($connection->isTableExists($table));

        $columns = $connection->describeTable($table);
        $expectedColumns = ['entity_id', 'quote_address_id', 'order_address_id', 'address_type', 'created_at', 'updated_at'];
        foreach ($expectedColumns as $column) {
            $this->assertArrayHasKey($column, $columns);
        }

        $this->assertTrue((bool)$columns['quote_address_id']['NULLABLE']);
        $this->assertTrue((bool)$columns['order_address_id']['NULLABLE']);

        $uniqueColumns = [];
        foreach ($connection->getIndexList($table) as $index) {
            if ($index['INDEX_TYPE'] === 'unique') {
                $uniqueColumns = array_merge($uniqueColumns, $index['COLUMNS_LIST']);
            }
        }

        $this->assertContains('quote_address_id', $uniqueColumns);
        $this->assertContains('order_address_id', $uniqueColumns);

        $foreignKeys = [];
        foreach ($connection->getForeignKeys($table) as $foreignKey) {
            $foreignKeys[$foreignKey['COLUMN_NAME']] = $foreignKey;
        }

        $this->assertSame($resource->getTableName('quote_address'), $foreignKeys['quote_address_id']['REF_TABLE_NAME']);
        $this->assertSame('SET NULL', $foreignKeys['quote_address_id']['ON_DELETE']);
        $this->assertSame(
            $resource->getTableName('sales_order_address'),
            $foreignKeys['order_address_id']['REF_TABLE_NAME']
        );
        $this->assertSame('CASCADE', $foreignKeys['order_address_id']['ON_DELETE']);
    }

    public function testObservers(): void
    {
        $eventConfig = Bootstrap::getObjectManager()->get(EventConfig::class);

        $this->assertObserver(
            $eventConfig->getObservers('sales_model_service_quote_submit_success'),
            LinkOrderAddressesToQuoteAddressExtended::class
        );
        $this->assertObserver(
            $eventConfig->getObservers('email_order_set_template_vars_before'),
            AddQuoteAddressExtendedToEmailVars::class
        );
        $this->assertObserver(
            $eventConfig->getObservers('email_invoice_set_template_vars_before'),
            AddQuoteAddressExtendedToEmailVars::class
        );
        $this->assertObserver(
            $eventConfig->getObservers('email_shipment_set_template_vars_before'),
            AddQuoteAddressExtendedToEmailVars::class
        );
        $this->assertObserver(
            $eventConfig->getObservers('email_creditmemo_set_template_vars_before'),
            AddQuoteAddressExtendedToEmailVars::class
        );
        $this->assertObserver(
            $eventConfig->getObservers('sales_model_service_quote_submit_success'),
            CopyQuoteAddressExtendedToCustomerAddress::class
        );
        $this->assertObserver(
            $eventConfig->getObservers('sales_quote_merge_after'),
            CopyQuoteAddressExtendedOnQuoteMerge::class
        );
        $this->assertObserver(
            $eventConfig->getObservers('sales_quote_address_save_after'),
            CopyCustomerAddressExtendedToQuoteAddress::class
        );
        $this->assertObserver(
            $eventConfig->getObservers('customer_address_format'),
            AddQuoteAddressExtendedToAddressFormat::class
        );
    }

    public function testAdminhtmlObservers(): void
    {
        $events = Bootstrap::getObjectManager()->create(EventConfigReader::class)->read('adminhtml');

        $this->assertArrayHasKey('admin_sales_order_address_update', $events);
        $this->assertObserver(
            $events['admin_sales_order_address_update'],
            SaveAdminOrderAddressExtended::class
        );
    }

    public function testAdminhtmlPlugins(): void
    {
        $config = Bootstrap::getObjectManager()->create(DiConfigReader::class)->read('adminhtml');

        $this->assertSame(
            CopyQuoteAddressExtendedOnInitFromOrder::class,
            $config[AdminOrderCreate::class]['plugins']['Loki_QuoteAddressExtended::copyQuoteAddressExtendedOnInitFromOrder']['instance']
        );
        $this->assertSame(
            AddAdminFieldsToOrderAddressForm::class,
            $config[OrderAddressForm::class]['plugins']['Loki_QuoteAddressExtended::addAdminFieldsToOrderAddressForm']['instance']
        );

        $globalConfig = Bootstrap::getObjectManager()->create(DiConfigReader::class)->read('global');
        $this->assertArrayNotHasKey(
            'Loki_QuoteAddressExtended::copyQuoteAddressExtendedOnInitFromOrder',
            $globalConfig[AdminOrderCreate::class]['plugins'] ?? []
        );
    }

    public function testCustomerAddressTable(): void
    {
        $resource = Bootstrap::getObjectManager()->get(ResourceConnection::class);
        $connection = $resource->getConnection();
        $table = $resource->getTableName('loki_customer_address_extended');

        $this->assertTrue($connection->isTableExists($table));

        $columns = $connection->describeTable($table);
        foreach (['entity_id', 'customer_address_id', 'created_at', 'updated_at'] as $column) {
            $this->assertArrayHasKey($column, $columns);
        }

        $this->assertFalse((bool)$columns['customer_address_id']['NULLABLE']);

        $uniqueColumns = [];
        foreach ($connection->getIndexList($table) as $index) {
            if ($index['INDEX_TYPE'] === 'unique') {
                $uniqueColumns = array_merge($uniqueColumns, $index['COLUMNS_LIST']);
            }
        }

        $this->assertContains('customer_address_id', $uniqueColumns);

        $foreignKeys = [];
        foreach ($connection->getForeignKeys($table) as $foreignKey) {
            $foreignKeys[$foreignKey['COLUMN_NAME']] = $foreignKey;
        }

        $this->assertArrayHasKey('customer_address_id', $foreignKeys);
        $this->assertSame(
            $resource->getTableName('customer_address_entity'),
            $foreignKeys['customer_address_id']['REF_TABLE_NAME']
        );
        $this->assertSame('entity_id', $foreignKeys['customer_address_id']['REF_COLUMN_NAME']);
        $this->assertSame('CASCADE', $foreignKeys['customer_address_id']['ON_DELETE']);
    }

    public function testCronJob(): void
    {
        $jobs = Bootstrap::getObjectManager()->get(CronConfig::class)->getJobs();

        $this->assertArrayHasKey('loki_quote_address_extended_delete_orphaned_rows', $jobs['default']);
        $this->assertSame(
            DeleteOrphanedRows::class,
            $jobs['default']['loki_quote_address_extended_delete_orphaned_rows']['instance']
        );
        $this->assertSame('execute', $jobs['default']['loki_quote_address_extended_delete_orphaned_rows']['method']);
        $this->assertSame('35 3 * * *', $jobs['default']['loki_quote_address_extended_delete_orphaned_rows']['schedule']);
    }

    public function testLokiComponentGroup(): void
    {
        $groups = (array)Bootstrap::getObjectManager()->get(XmlConfig::class)->get('groups');

        $this->assertArrayHasKey('quoteAddressExtendedFields', $groups);
        $group = $groups['quoteAddressExtendedFields'];
        $this->assertSame(QuoteAddressExtendedFieldRepository::class, $group['repository']);
        $this->assertSame(QuoteAddressExtendedFieldViewModel::class, $group['viewModel']);
        $this->assertContains(
            'loki-checkout.sidebar.preview.quote_details',
            array_column($group['targets'], 'name')
        );
    }

    public function testLokiCheckoutModuleIsRegistered(): void
    {
        $modules = Bootstrap::getObjectManager()->get(LokiCheckoutModules::class)->getModules();

        $this->assertContains('Loki_QuoteAddressExtended', $modules);
    }

    private function assertObserver(array $observers, string $observerClass): void
    {
        $this->assertContains($observerClass, array_column($observers, 'instance'));
    }
}
