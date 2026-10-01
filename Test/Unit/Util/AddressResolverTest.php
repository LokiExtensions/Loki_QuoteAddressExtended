<?php

declare(strict_types=1);

namespace Loki\QuoteAddressExtended\Test\Unit\Util;

use Loki\QuoteAddressExtended\Util\AddressResolver;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address as QuoteAddress;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Address as OrderAddress;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class AddressResolverTest extends TestCase
{
    public static function getQuoteAddressProvider(): array
    {
        return [
            ['shipping', false, 'shipping'],
            ['shipping', true, 'shipping'],
            ['billing', false, 'billing'],
            ['billing', true, 'billing'],
            ['auto', false, 'shipping'],
            ['auto', true, 'billing'],
            ['', false, 'shipping'],
            ['unknown', true, 'billing'],
        ];
    }

    #[DataProvider('getQuoteAddressProvider')]
    public function testGetQuoteAddress(string $addressType, bool $isVirtual, string $expected): void
    {
        $addresses = [
            'shipping' => $this->createMock(QuoteAddress::class),
            'billing' => $this->createMock(QuoteAddress::class),
        ];

        $quote = $this->createMock(Quote::class);
        $quote->method('isVirtual')->willReturn($isVirtual);
        $quote->method('getShippingAddress')->willReturn($addresses['shipping']);
        $quote->method('getBillingAddress')->willReturn($addresses['billing']);

        $this->assertSame(
            $addresses[$expected],
            (new AddressResolver())->getQuoteAddress($quote, $addressType)
        );
    }

    public static function getOrderAddressProvider(): array
    {
        return [
            ['shipping', true, 'shipping'],
            ['shipping', false, null],
            ['billing', true, 'billing'],
            ['billing', false, 'billing'],
            ['auto', true, 'shipping'],
            ['auto', false, 'billing'],
        ];
    }

    #[DataProvider('getOrderAddressProvider')]
    public function testGetOrderAddress(string $addressType, bool $hasShippingAddress, ?string $expected): void
    {
        $addresses = [
            'shipping' => $this->createMock(OrderAddress::class),
            'billing' => $this->createMock(OrderAddress::class),
        ];

        $order = $this->createMock(Order::class);
        $order->method('getShippingAddress')->willReturn($hasShippingAddress ? $addresses['shipping'] : null);
        $order->method('getBillingAddress')->willReturn($addresses['billing']);

        $this->assertSame(
            $expected !== null ? $addresses[$expected] : null,
            (new AddressResolver())->getOrderAddress($order, $addressType)
        );
    }
}
