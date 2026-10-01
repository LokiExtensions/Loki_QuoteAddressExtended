<?php

declare(strict_types=1);

namespace Loki\QuoteAddressExtended\ViewModel;

use Loki\QuoteAddressExtended\Repository\QuoteAddressExtendedRepository;
use Loki\QuoteAddressExtended\Util\AddressResolver;
use LokiCheckout\Core\ViewModel\CheckoutState;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Element\Block\ArgumentInterface;

class CheckoutQuoteAddressExtendedView implements ArgumentInterface
{
    public function __construct(
        private readonly QuoteAddressExtendedRepository $quoteAddressExtendedRepository,
        private readonly CheckoutState $checkoutState,
        private readonly AddressResolver $addressResolver
    ) {
    }

    public function getValue(string $column, string $addressType = AddressResolver::TYPE_AUTO): string
    {
        if ($this->checkoutState->getQuoteId() < 1) {
            return '';
        }

        $quoteAddress = $this->addressResolver->getQuoteAddress($this->checkoutState->getQuote(), $addressType);
        $quoteAddressId = (int)$quoteAddress->getId();
        if ($quoteAddressId < 1) {
            return '';
        }

        try {
            $quoteAddressExtended = $this->quoteAddressExtendedRepository->getByQuoteAddressId($quoteAddressId);
        } catch (NoSuchEntityException) {
            return '';
        }

        return (string)$quoteAddressExtended->getData($column);
    }
}
