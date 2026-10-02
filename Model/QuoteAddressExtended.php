<?php

declare(strict_types=1);

namespace Loki\QuoteAddressExtended\Model;

use Loki\QuoteAddressExtended\Model\ResourceModel\QuoteAddressExtended as QuoteAddressExtendedResource;
use Magento\Framework\Model\AbstractModel;

class QuoteAddressExtended extends AbstractModel
{
    public const ENTITY_ID = 'entity_id';
    public const QUOTE_ADDRESS_ID = 'quote_address_id';
    public const ORDER_ADDRESS_ID = 'order_address_id';
    public const ADDRESS_TYPE = 'address_type';
    public const CREATED_AT = 'created_at';
    public const UPDATED_AT = 'updated_at';

    protected $_eventPrefix = 'loki_quote_address_extended';

    protected function _construct(): void
    {
        $this->_init(QuoteAddressExtendedResource::class);
    }

    public function getQuoteAddressId(): ?int
    {
        $value = $this->getData(self::QUOTE_ADDRESS_ID);
        return $value !== null ? (int)$value : null;
    }

    public function setQuoteAddressId(?int $quoteAddressId): self
    {
        return $this->setData(self::QUOTE_ADDRESS_ID, $quoteAddressId);
    }

    public function getOrderAddressId(): ?int
    {
        $value = $this->getData(self::ORDER_ADDRESS_ID);
        return $value !== null ? (int)$value : null;
    }

    public function setOrderAddressId(?int $orderAddressId): self
    {
        return $this->setData(self::ORDER_ADDRESS_ID, $orderAddressId);
    }

    public function getAddressType(): string
    {
        return (string)$this->getData(self::ADDRESS_TYPE);
    }

    public function setAddressType(string $addressType): self
    {
        return $this->setData(self::ADDRESS_TYPE, $addressType);
    }

    public function getCreatedAt(): ?string
    {
        $value = $this->getData(self::CREATED_AT);
        return $value !== null ? (string)$value : null;
    }

    public function getUpdatedAt(): ?string
    {
        $value = $this->getData(self::UPDATED_AT);
        return $value !== null ? (string)$value : null;
    }
}
