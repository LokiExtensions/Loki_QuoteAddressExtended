<?php

declare(strict_types=1);

namespace Loki\QuoteAddressExtended\Test\Unit\Model;

use Loki\QuoteAddressExtended\Model\CustomerAddressExtended;
use PHPUnit\Framework\TestCase;

class CustomerAddressExtendedTest extends TestCase
{
    public function testGetCustomerAddressIdReturnsNullWithoutData(): void
    {
        $customerAddressExtended = $this->createCustomerAddressExtended([]);

        $this->assertNull($customerAddressExtended->getCustomerAddressId());
    }

    public function testGetCustomerAddressIdCastsDatabaseValue(): void
    {
        $customerAddressExtended = $this->createCustomerAddressExtended(['customer_address_id' => '42']);

        $this->assertSame(42, $customerAddressExtended->getCustomerAddressId());
    }

    public function testSetCustomerAddressIdRoundTrip(): void
    {
        $customerAddressExtended = $this->createCustomerAddressExtended([]);

        $this->assertSame($customerAddressExtended, $customerAddressExtended->setCustomerAddressId(7));
        $this->assertSame(7, $customerAddressExtended->getCustomerAddressId());
    }

    private function createCustomerAddressExtended(array $data): CustomerAddressExtended
    {
        $customerAddressExtended = $this->getMockBuilder(CustomerAddressExtended::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();
        $customerAddressExtended->setData($data);

        return $customerAddressExtended;
    }
}
