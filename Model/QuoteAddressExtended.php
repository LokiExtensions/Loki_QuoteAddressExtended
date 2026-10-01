<?php

declare(strict_types=1);

namespace Loki\QuoteAddressExtended\Model;

use Loki\QuoteAddressExtended\Model\ResourceModel\QuoteAddressExtended as QuoteAddressExtendedResource;
use Magento\Framework\Model\AbstractModel;

class QuoteAddressExtended extends AbstractModel
{
<<<<<<< HEAD
    public const ID = 'id';
    public const QUOTE_ADDRESS_ID = 'quote_address_id';
    public const CREATED_AT = 'created_at';
    public const UPDATED_AT = 'updated_at';
=======
    protected $_eventPrefix = 'loki_quote_address_extended';
>>>>>>> 9526565 (WIP)

    protected function _construct(): void
    {
        $this->_init(QuoteAddressExtendedResource::class);
    }

    public function getQuoteAddressId(): ?int
    {
<<<<<<< HEAD
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
=======
        $value = $this->getData('quote_address_id');
        return $value !== null ? (int)$value : null;
    }

    public function setQuoteAddressId(?int $quoteAddressId): self
    {
        return $this->setData('quote_address_id', $quoteAddressId);
    }

    public function getOrderAddressId(): ?int
    {
        $value = $this->getData('order_address_id');
        return $value !== null ? (int)$value : null;
    }

    public function setOrderAddressId(?int $orderAddressId): self
    {
        return $this->setData('order_address_id', $orderAddressId);
    }

    public function getAddressType(): string
    {
        return (string)$this->getData('address_type');
    }

    public function setAddressType(string $addressType): self
    {
        return $this->setData('address_type', $addressType);
>>>>>>> 9526565 (WIP)
    }
}
