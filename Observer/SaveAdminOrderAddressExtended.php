<?php

declare(strict_types=1);

namespace Loki\QuoteAddressExtended\Observer;

use Loki\QuoteAddressExtended\Config\AdminFields;
use Loki\QuoteAddressExtended\Model\QuoteAddressExtended;
use Loki\QuoteAddressExtended\Plugin\AddAdminFieldsToOrderAddressForm;
use Loki\QuoteAddressExtended\Repository\QuoteAddressExtendedRepository;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Message\ManagerInterface as MessageManager;
use Magento\Sales\Api\OrderAddressRepositoryInterface;
use Magento\Sales\Model\Order\Address as OrderAddress;

class SaveAdminOrderAddressExtended implements ObserverInterface
{
    public function __construct(
        private readonly AdminFields $adminFields,
        private readonly QuoteAddressExtendedRepository $quoteAddressExtendedRepository,
        private readonly OrderAddressRepositoryInterface $orderAddressRepository,
        private readonly RequestInterface $request,
        private readonly MessageManager $messageManager
    ) {
    }

    public function execute(Observer $observer): void
    {
        $fields = $this->adminFields->getFields();
        $values = $this->request->getParam(AddAdminFieldsToOrderAddressForm::FIELD_NAME);
        if ([] === $fields || false === is_array($values)) {
            return;
        }

        $orderAddress = $this->getOrderAddress();
        if (null === $orderAddress) {
            return;
        }

        $quoteAddressExtended = $this->getQuoteAddressExtended($orderAddress);
        $hasValue = false;
        foreach (array_keys($fields) as $column) {
            if (false === array_key_exists($column, $values) || false === is_scalar($values[$column])) {
                continue;
            }

            $value = trim((string)$values[$column], " \n\r\t\v\x00");
            $quoteAddressExtended->setData($column, $value === '' ? null : $value);
            $hasValue = $hasValue || $value !== '';
        }

        if (false === $quoteAddressExtended->hasDataChanges()) {
            return;
        }

        if (!$quoteAddressExtended->getId() && false === $hasValue) {
            return;
        }

        try {
            $this->quoteAddressExtendedRepository->save($quoteAddressExtended);
        } catch (CouldNotSaveException $exception) {
            $this->messageManager->addErrorMessage($exception->getMessage());
        }
    }

    private function getOrderAddress(): ?OrderAddress
    {
        $orderAddressId = (int)$this->request->getParam('address_id');
        if ($orderAddressId < 1) {
            return null;
        }

        try {
            $orderAddress = $this->orderAddressRepository->get($orderAddressId);
        } catch (NoSuchEntityException) {
            return null;
        }

        return $orderAddress instanceof OrderAddress ? $orderAddress : null;
    }

    private function getQuoteAddressExtended(OrderAddress $orderAddress): QuoteAddressExtended
    {
        try {
            $quoteAddressExtended = $this->quoteAddressExtendedRepository->getByOrderAddress($orderAddress);
        } catch (NoSuchEntityException) {
            $quoteAddressExtended = $this->quoteAddressExtendedRepository->create();
            $quoteAddressExtended->setAddressType((string)$orderAddress->getAddressType());
        }

        if ($quoteAddressExtended->getOrderAddressId() === null) {
            $quoteAddressExtended->setOrderAddressId((int)$orderAddress->getEntityId());
        }

        return $quoteAddressExtended;
    }
}
