<?php

declare(strict_types=1);

namespace Loki\QuoteAddressExtended\Model;

use Loki\QuoteAddressExtended\Model\ResourceModel\QuoteAddressExtended as QuoteAddressExtendedResource;
use Magento\Framework\Model\AbstractModel;

class QuoteAddressExtended extends AbstractModel
{
    public const ID = 'id';
    public const QUOTE_ADDRESS_ID = 'quote_address_id';
    public const CREATED_AT = 'created_at';
    public const UPDATED_AT = 'updated_at';

    protected function _construct(): void
    {
        $this->_init(QuoteAddressExtendedResource::class);
    }

    public function getQuoteAddressId(): ?int
    {
        $quoteAddressId = $this->getData(self::QUOTE_ADDRESS_ID);

        return $quoteAddressId === null ? null : (int)$quoteAddressId;
    }

    public function setQuoteAddressId(int $quoteAddressId): self
    {
        return $this->setData(self::QUOTE_ADDRESS_ID, $quoteAddressId);
    }

    public function getCreatedAt(): ?string
    {
        $createdAt = $this->getData(self::CREATED_AT);

        return $createdAt === null ? null : (string)$createdAt;
    }

    public function getUpdatedAt(): ?string
    {
        $updatedAt = $this->getData(self::UPDATED_AT);

        return $updatedAt === null ? null : (string)$updatedAt;
    }
}
