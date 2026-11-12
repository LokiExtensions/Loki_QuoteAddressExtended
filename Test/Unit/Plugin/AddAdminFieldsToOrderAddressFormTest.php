<?php

declare(strict_types=1);

namespace Loki\QuoteAddressExtended\Test\Unit\Plugin;

use Loki\QuoteAddressExtended\Config\AdminFields;
use Loki\QuoteAddressExtended\Model\QuoteAddressExtended;
use Loki\QuoteAddressExtended\Plugin\AddAdminFieldsToOrderAddressForm;
use Loki\QuoteAddressExtended\Repository\QuoteAddressExtendedRepository;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Data\Form;
use Magento\Framework\Data\Form\Element\Fieldset;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\Data\OrderAddressInterface;
use Magento\Sales\Api\OrderAddressRepositoryInterface;
use Magento\Sales\Block\Adminhtml\Order\Address\Form as OrderAddressForm;
use Magento\Sales\Model\Order\Address as OrderAddress;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class AddAdminFieldsToOrderAddressFormTest extends TestCase
{
    private QuoteAddressExtendedRepository&MockObject $quoteAddressExtendedRepository;
    private OrderAddressRepositoryInterface&MockObject $orderAddressRepository;
    private RequestInterface&MockObject $request;
    private array $fields = ['example_address_field' => 'Example', 'house_number' => 'House number'];
    private array $addedFields = [];

    protected function setUp(): void
    {
        $this->quoteAddressExtendedRepository = $this->createMock(QuoteAddressExtendedRepository::class);
        $this->orderAddressRepository = $this->createMock(OrderAddressRepositoryInterface::class);
        $this->request = $this->createMock(RequestInterface::class);
    }

    public function testAddsFieldsetWithValuesOfQuoteAddressExtended(): void
    {
        $orderAddress = $this->createOrderAddress(7);
        $this->setAddressId(7);
        $this->orderAddressRepository->method('get')->with(7)->willReturn($orderAddress);
        $this->quoteAddressExtendedRepository->method('getByOrderAddress')->with($orderAddress)
            ->willReturn(
                $this->createQuoteAddressExtended(['example_address_field' => 'foobar', 'house_number' => 12])
            );

        $form = $this->createForm(true);

        $this->assertSame($form, $this->createPlugin()->afterGetForm($this->createSubject(), $form));
        $this->assertSame([
            'loki_quote_address_extended_example_address_field' => [
                'text',
                'loki_quote_address_extended[example_address_field]',
                'Example',
                'Example',
                'foobar',
            ],
            'loki_quote_address_extended_house_number' => [
                'text',
                'loki_quote_address_extended[house_number]',
                'House number',
                'House number',
                '12',
            ],
        ], $this->addedFields);
    }

    public function testAddsEmptyFieldsWithoutAddressId(): void
    {
        $this->setAddressId(0);
        $this->orderAddressRepository->expects($this->never())->method('get');

        $this->createPlugin()->afterGetForm($this->createSubject(), $this->createForm(true));

        $this->assertSame(['', ''], array_column($this->addedFields, 4));
    }

    public function testAddsEmptyFieldsWithoutOrderAddress(): void
    {
        $this->setAddressId(7);
        $this->orderAddressRepository->method('get')
            ->willThrowException(new NoSuchEntityException(__('Not found')));
        $this->quoteAddressExtendedRepository->expects($this->never())->method('getByOrderAddress');

        $this->createPlugin()->afterGetForm($this->createSubject(), $this->createForm(true));

        $this->assertSame(['', ''], array_column($this->addedFields, 4));
    }

    public function testAddsEmptyFieldsForOrderAddressThatIsNoOrderAddressModel(): void
    {
        $this->setAddressId(7);
        $this->orderAddressRepository->method('get')->willReturn($this->createMock(OrderAddressInterface::class));
        $this->quoteAddressExtendedRepository->expects($this->never())->method('getByOrderAddress');

        $this->createPlugin()->afterGetForm($this->createSubject(), $this->createForm(true));

        $this->assertSame(['', ''], array_column($this->addedFields, 4));
    }

    public function testAddsEmptyFieldsWithoutQuoteAddressExtended(): void
    {
        $this->setAddressId(7);
        $this->orderAddressRepository->method('get')->willReturn($this->createOrderAddress(7));
        $this->quoteAddressExtendedRepository->method('getByOrderAddress')
            ->willThrowException(new NoSuchEntityException(__('Not found')));

        $this->createPlugin()->afterGetForm($this->createSubject(), $this->createForm(true));

        $this->assertSame(['', ''], array_column($this->addedFields, 4));
    }

    public function testSkipsWithoutFields(): void
    {
        $this->fields = ['entity_id' => 'Entity ID'];
        $this->request->expects($this->never())->method('getParam');

        $form = $this->createForm(false);

        $this->assertSame($form, $this->createPlugin()->afterGetForm($this->createSubject(), $form));
    }

    public function testSkipsExistingFieldset(): void
    {
        $this->request->expects($this->never())->method('getParam');

        $form = $this->createMock(Form::class);
        $form->method('getElement')->with('loki_quote_address_extended')
            ->willReturn($this->createMock(Fieldset::class));
        $form->expects($this->never())->method('addFieldset');

        $this->assertSame($form, $this->createPlugin()->afterGetForm($this->createSubject(), $form));
    }

    private function createForm(bool $expectFieldset): Form
    {
        $fieldset = $this->createMock(Fieldset::class);
        $fieldset->method('addField')->willReturnCallback(
            function (string $elementId, string $type, array $config) use ($fieldset) {
                $this->addedFields[$elementId] = [
                    $type,
                    $config['name'],
                    (string)$config['label'],
                    (string)$config['title'],
                    $config['value'],
                ];

                return $fieldset;
            }
        );

        $form = $this->createMock(Form::class);
        $form->method('getElement')->willReturn(null);
        $form->expects($expectFieldset ? $this->once() : $this->never())->method('addFieldset')
            ->with('loki_quote_address_extended', $this->callback(
                fn (array $config) => (string)$config['legend'] === 'Additional Address Information'
            ))
            ->willReturn($fieldset);

        return $form;
    }

    private function setAddressId(int $addressId): void
    {
        $this->request->method('getParam')->with('address_id')->willReturn((string)$addressId);
    }

    private function createSubject(): OrderAddressForm
    {
        return $this->createMock(OrderAddressForm::class);
    }

    private function createOrderAddress(int $orderAddressId): OrderAddress
    {
        $orderAddress = $this->getMockBuilder(OrderAddress::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();
        $orderAddress->setData(['entity_id' => $orderAddressId]);

        return $orderAddress;
    }

    private function createQuoteAddressExtended(array $data): QuoteAddressExtended
    {
        $quoteAddressExtended = $this->getMockBuilder(QuoteAddressExtended::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();
        $quoteAddressExtended->setData($data);

        return $quoteAddressExtended;
    }

    private function createPlugin(): AddAdminFieldsToOrderAddressForm
    {
        return new AddAdminFieldsToOrderAddressForm(
            new AdminFields($this->fields),
            $this->quoteAddressExtendedRepository,
            $this->orderAddressRepository,
            $this->request
        );
    }
}
