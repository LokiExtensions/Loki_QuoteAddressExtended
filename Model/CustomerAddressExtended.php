<?php

declare(strict_types=1);

namespace Loki\QuoteAddressExtended\Model;

use Loki\QuoteAddressExtended\Model\ResourceModel\CustomerAddressExtended as CustomerAddressExtendedResource;
use Magento\Framework\Model\AbstractModel;

class CustomerAddressExtended extends AbstractModel
{
    public const ENTITY_ID = 'entity_id';
    public const CUSTOMER_ADDRESS_ID = 'customer_address_id';
    public const CREATED_AT = 'created_at';
    public const UPDATED_AT = 'updated_at';

    protected $_eventPrefix = 'loki_customer_address_extended';

    protected function _construct(): void
    {
        $this->_init(CustomerAddressExtendedResource::class);
    }

    public function getCustomerAddressId(): ?int
    {
        $value = $this->getData(self::CUSTOMER_ADDRESS_ID);
        return $value !== null ? (int)$value : null;
    }

    public function setCustomerAddressId(int $customerAddressId): self
    {
        return $this->setData(self::CUSTOMER_ADDRESS_ID, $customerAddressId);
    }
}
