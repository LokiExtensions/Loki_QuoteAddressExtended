<?php

declare(strict_types=1);

namespace Loki\QuoteAddressExtended\Component\QuoteAddressExtendedField;

use Loki\Field\Component\Base\Field\FieldRepository;
use Loki\QuoteAddressExtended\Repository\QuoteAddressExtendedRepository;
use Loki\QuoteAddressExtended\Util\AddressResolver;
use LokiCheckout\Core\ViewModel\CheckoutState;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Model\Quote\Address as QuoteAddress;
use Magento\Quote\Model\ResourceModel\Quote\Address as QuoteAddressResource;

class QuoteAddressExtendedFieldRepository extends FieldRepository
{
    public function __construct(
        private readonly QuoteAddressExtendedRepository $quoteAddressExtendedRepository,
        private readonly CheckoutState $checkoutState,
        private readonly AddressResolver $addressResolver,
        private readonly QuoteAddressResource $quoteAddressResource
    ) {
    }

    public function getValue(): mixed
    {
        $quoteAddressId = (int)$this->getQuoteAddress()?->getId();
        if ($quoteAddressId < 1) {
            return '';
        }

        try {
            $quoteAddressExtended = $this->quoteAddressExtendedRepository->getByQuoteAddressId($quoteAddressId);
        } catch (NoSuchEntityException) {
            return '';
        }

        return (string)$quoteAddressExtended->getData($this->getFieldName());
    }

    public function saveValue(mixed $value): void
    {
        $fieldName = $this->getFieldName();
        if (in_array($fieldName, QuoteAddressExtendedRepository::RESERVED_COLUMNS, true)) {
            return;
        }

        $quoteAddress = $this->getQuoteAddress();
        if (null === $quoteAddress) {
            return;
        }

        if (!$quoteAddress->getId()) {
            $this->quoteAddressResource->save($quoteAddress);
        }

        $quoteAddressId = (int)$quoteAddress->getId();
        if ($quoteAddressId < 1) {
            return;
        }

        $quoteAddressExtended = $this->quoteAddressExtendedRepository->getOrCreateByQuoteAddressId($quoteAddressId);
        $quoteAddressExtended->setAddressType((string)$quoteAddress->getAddressType());
        $quoteAddressExtended->setData($fieldName, is_scalar($value) ? $value : null);
        $this->quoteAddressExtendedRepository->save($quoteAddressExtended);
    }

    public function getFieldName(): string
    {
        $fieldName = (string)$this->getComponent()->getBlock()?->getFieldName();
        if ($fieldName !== '') {
            return $fieldName;
        }

        return parent::getFieldName();
    }

    public function getAddressType(): string
    {
        $addressType = (string)$this->getComponent()->getBlock()?->getAddressType();
        if ($addressType !== '') {
            return $addressType;
        }

        return AddressResolver::TYPE_AUTO;
    }

    private function getQuoteAddress(): ?QuoteAddress
    {
        if ($this->checkoutState->getQuoteId() < 1) {
            return null;
        }

        return $this->addressResolver->getQuoteAddress($this->checkoutState->getQuote(), $this->getAddressType());
    }
}
