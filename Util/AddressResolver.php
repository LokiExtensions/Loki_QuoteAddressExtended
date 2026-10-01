<?php

declare(strict_types=1);

namespace Loki\QuoteAddressExtended\Util;

use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address as QuoteAddress;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Address as OrderAddress;

class AddressResolver
{
    public const TYPE_AUTO = 'auto';
    public const TYPE_SHIPPING = 'shipping';
    public const TYPE_BILLING = 'billing';

    public function getQuoteAddress(Quote $quote, string $addressType = self::TYPE_AUTO): QuoteAddress
    {
        return match ($addressType) {
            self::TYPE_BILLING => $quote->getBillingAddress(),
            self::TYPE_SHIPPING => $quote->getShippingAddress(),
            default => $quote->isVirtual() ? $quote->getBillingAddress() : $quote->getShippingAddress(),
        };
    }

    public function getOrderAddress(Order $order, string $addressType = self::TYPE_AUTO): ?OrderAddress
    {
        return match ($addressType) {
            self::TYPE_BILLING => $order->getBillingAddress(),
            self::TYPE_SHIPPING => $order->getShippingAddress(),
            default => $order->getShippingAddress() ?? $order->getBillingAddress(),
        };
    }
}
