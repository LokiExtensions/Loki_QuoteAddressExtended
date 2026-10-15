<?php

declare(strict_types=1);

namespace Loki\QuoteAddressExtended\Test\Unit\Model;

use Loki\QuoteAddressExtended\Model\QuoteAddressExtended;
use PHPUnit\Framework\TestCase;

class QuoteAddressExtendedTest extends TestCase
{
    public function testGettersReturnNullWithoutData(): void
    {
        $quoteAddressExtended = $this->createQuoteAddressExtended([]);

        $this->assertNull($quoteAddressExtended->getQuoteAddressId());
        $this->assertNull($quoteAddressExtended->getOrderAddressId());
        $this->assertSame('', $quoteAddressExtended->getAddressType());
        $this->assertNull($quoteAddressExtended->getCreatedAt());
        $this->assertNull($quoteAddressExtended->getUpdatedAt());
    }

    public function testGettersCastDatabaseValues(): void
    {
        $quoteAddressExtended = $this->createQuoteAddressExtended([
            'quote_address_id' => '42',
            'order_address_id' => '7',
            'address_type' => 'billing',
            'created_at' => '2026-01-01 00:00:00',
            'updated_at' => '2026-01-02 00:00:00',
        ]);

        $this->assertSame(42, $quoteAddressExtended->getQuoteAddressId());
        $this->assertSame(7, $quoteAddressExtended->getOrderAddressId());
        $this->assertSame('billing', $quoteAddressExtended->getAddressType());
        $this->assertSame('2026-01-01 00:00:00', $quoteAddressExtended->getCreatedAt());
        $this->assertSame('2026-01-02 00:00:00', $quoteAddressExtended->getUpdatedAt());
    }

    public function testSettersRoundTrip(): void
    {
        $quoteAddressExtended = $this->createQuoteAddressExtended([]);
        $quoteAddressExtended->setQuoteAddressId(42)
            ->setOrderAddressId(7)
            ->setAddressType('shipping');

        $this->assertSame(42, $quoteAddressExtended->getQuoteAddressId());
        $this->assertSame(7, $quoteAddressExtended->getOrderAddressId());
        $this->assertSame('shipping', $quoteAddressExtended->getAddressType());

        $quoteAddressExtended->setQuoteAddressId(null)->setOrderAddressId(null);

        $this->assertNull($quoteAddressExtended->getQuoteAddressId());
        $this->assertNull($quoteAddressExtended->getOrderAddressId());
    }

    private function createQuoteAddressExtended(array $data): QuoteAddressExtended
    {
        $quoteAddressExtended = $this->getMockBuilder(QuoteAddressExtended::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();
        $quoteAddressExtended->setData($data);

        return $quoteAddressExtended;
    }
}
