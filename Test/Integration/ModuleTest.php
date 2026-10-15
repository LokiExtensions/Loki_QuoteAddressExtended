<?php

declare(strict_types=1);

namespace Loki\QuoteAddressExtended\Test\Integration;

use Loki\Components\Config\XmlConfig;
use Loki\QuoteAddressExtended\Component\QuoteAddressExtendedField\QuoteAddressExtendedFieldRepository;
use Loki\QuoteAddressExtended\Component\QuoteAddressExtendedField\QuoteAddressExtendedFieldViewModel;
use Loki\QuoteAddressExtended\Cron\DeleteOrphanedRows;
use Loki\QuoteAddressExtended\Observer\AddQuoteAddressExtendedToEmailVars;
use Loki\QuoteAddressExtended\Observer\LinkOrderAddressesToQuoteAddressExtended;
use LokiCheckout\Core\Config\LokiCheckoutModules;
use Magento\Cron\Model\ConfigInterface as CronConfig;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Event\ConfigInterface as EventConfig;
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
    }

    public function testCronJob(): void
    {
        $jobs = Bootstrap::getObjectManager()->get(CronConfig::class)->getJobs();

        $this->assertArrayHasKey('loki_quote_address_extended_delete_orphaned_rows', $jobs['default']);
        $this->assertSame(
            DeleteOrphanedRows::class,
            $jobs['default']['loki_quote_address_extended_delete_orphaned_rows']['instance']
        );
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
