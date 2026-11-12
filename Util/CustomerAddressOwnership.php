<?php

declare(strict_types=1);

namespace Loki\QuoteAddressExtended\Util;

use Magento\Framework\App\ResourceConnection;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address as QuoteAddress;

class CustomerAddressOwnership
{
    private array $customerIds = [];

    public function __construct(
        private readonly ResourceConnection $resourceConnection
    ) {
    }

    public function isOwnedByQuoteCustomer(int $customerAddressId, QuoteAddress $quoteAddress): bool
    {
        return $this->isOwnedBy($customerAddressId, $this->getQuoteCustomerId($quoteAddress));
    }

    public function isOwnedBy(int $customerAddressId, int $customerId): bool
    {
        if ($customerAddressId < 1 || $customerId < 1) {
            return false;
        }

        return $this->getCustomerId($customerAddressId) === $customerId;
    }

    private function getQuoteCustomerId(QuoteAddress $quoteAddress): int
    {
        $quote = $quoteAddress->getQuote();
        if ($quote instanceof Quote) {
            return (int)$quote->getCustomerId();
        }

        $quoteId = (int)$quoteAddress->getQuoteId();
        if ($quoteId < 1) {
            return 0;
        }

        $connection = $this->resourceConnection->getConnection();
        $select = $connection->select()
            ->from($this->resourceConnection->getTableName('quote'), 'customer_id')
            ->where('entity_id = ?', $quoteId);

        return (int)$connection->fetchOne($select);
    }

    private function getCustomerId(int $customerAddressId): int
    {
        if (false === array_key_exists($customerAddressId, $this->customerIds)) {
            $connection = $this->resourceConnection->getConnection();
            $select = $connection->select()
                ->from($this->resourceConnection->getTableName('customer_address_entity'), 'parent_id')
                ->where('entity_id = ?', $customerAddressId);
            $this->customerIds[$customerAddressId] = (int)$connection->fetchOne($select);
        }

        return $this->customerIds[$customerAddressId];
    }
}
