<?php

declare(strict_types=1);

namespace Loki\QuoteAddressExtended\Plugin;

use Loki\QuoteAddressExtended\Config\AdminFields;
use Loki\QuoteAddressExtended\Model\QuoteAddressExtended;
use Loki\QuoteAddressExtended\Repository\QuoteAddressExtendedRepository;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Data\Form;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\OrderAddressRepositoryInterface;
use Magento\Sales\Block\Adminhtml\Order\Address\Form as OrderAddressForm;
use Magento\Sales\Model\Order\Address as OrderAddress;

class AddAdminFieldsToOrderAddressForm
{
    public const FIELDSET_ID = 'loki_quote_address_extended';
    public const FIELD_NAME = 'loki_quote_address_extended';

    public function __construct(
        private readonly AdminFields $adminFields,
        private readonly QuoteAddressExtendedRepository $quoteAddressExtendedRepository,
        private readonly OrderAddressRepositoryInterface $orderAddressRepository,
        private readonly RequestInterface $request
    ) {
    }

    public function afterGetForm(OrderAddressForm $subject, Form $form): Form
    {
        $fields = $this->adminFields->getFields();
        if ([] === $fields || null !== $form->getElement(self::FIELDSET_ID)) {
            return $form;
        }

        $quoteAddressExtended = $this->getQuoteAddressExtended();
        $fieldset = $form->addFieldset(self::FIELDSET_ID, ['legend' => __('Additional Address Information')]);
        foreach ($fields as $column => $label) {
            $fieldset->addField(self::FIELDSET_ID . '_' . $column, 'text', [
                'name' => self::FIELD_NAME . '[' . $column . ']',
                'label' => __($label),
                'title' => __($label),
                'value' => (string)$quoteAddressExtended?->getData($column),
            ]);
        }

        return $form;
    }

    private function getQuoteAddressExtended(): ?QuoteAddressExtended
    {
        $orderAddressId = (int)$this->request->getParam('address_id');
        if ($orderAddressId < 1) {
            return null;
        }

        try {
            $orderAddress = $this->orderAddressRepository->get($orderAddressId);
            if (false === $orderAddress instanceof OrderAddress) {
                return null;
            }

            return $this->quoteAddressExtendedRepository->getByOrderAddress($orderAddress);
        } catch (NoSuchEntityException) {
            return null;
        }
    }
}
