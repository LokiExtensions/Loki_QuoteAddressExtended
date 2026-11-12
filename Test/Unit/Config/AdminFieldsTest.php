<?php

declare(strict_types=1);

namespace Loki\QuoteAddressExtended\Test\Unit\Config;

use Loki\QuoteAddressExtended\Config\AdminFields;
use PHPUnit\Framework\TestCase;

class AdminFieldsTest extends TestCase
{
    public function testGetFieldsReturnsEmptyArrayByDefault(): void
    {
        $this->assertSame([], (new AdminFields())->getFields());
    }

    public function testGetFieldsReturnsValidFields(): void
    {
        $adminFields = new AdminFields([
            'example_address_field' => 'Example',
            'house_number' => 'House number',
        ]);

        $this->assertSame([
            'example_address_field' => 'Example',
            'house_number' => 'House number',
        ], $adminFields->getFields());
    }

    public function testGetFieldsSkipsInvalidFields(): void
    {
        $adminFields = new AdminFields([
            0 => 'Numeric key',
            'no_string_label' => ['Label'],
            'null_label' => null,
            'empty_label' => '',
            'entity_id' => 'Entity ID',
            'quote_address_id' => 'Quote Address ID',
            'order_address_id' => 'Order Address ID',
            'address_type' => 'Address Type',
            'created_at' => 'Created At',
            'updated_at' => 'Updated At',
            'example_address_field' => 'Example',
        ]);

        $this->assertSame(['example_address_field' => 'Example'], $adminFields->getFields());
    }
}
