<?php

declare(strict_types=1);

namespace Loki\QuoteAddressExtended\Test\Unit\Observer;

use Loki\QuoteAddressExtended\Config\AdminFields;
use Loki\QuoteAddressExtended\Model\QuoteAddressExtended;
use Loki\QuoteAddressExtended\Observer\SaveAdminOrderAddressExtended;
use Loki\QuoteAddressExtended\Repository\QuoteAddressExtendedRepository;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Message\ManagerInterface as MessageManager;
use Magento\Sales\Api\Data\OrderAddressInterface;
use Magento\Sales\Api\OrderAddressRepositoryInterface;
use Magento\Sales\Model\Order\Address as OrderAddress;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class SaveAdminOrderAddressExtendedTest extends TestCase
{
    private QuoteAddressExtendedRepository&MockObject $quoteAddressExtendedRepository;
    private OrderAddressRepositoryInterface&MockObject $orderAddressRepository;
    private RequestInterface&MockObject $request;
    private MessageManager&MockObject $messageManager;
    private LoggerInterface&MockObject $logger;
    private array $fields = ['example_address_field' => 'Example', 'house_number' => 'House number'];

    protected function setUp(): void
    {
        $this->quoteAddressExtendedRepository = $this->createMock(QuoteAddressExtendedRepository::class);
        $this->orderAddressRepository = $this->createMock(OrderAddressRepositoryInterface::class);
        $this->request = $this->createMock(RequestInterface::class);
        $this->messageManager = $this->createMock(MessageManager::class);
        $this->logger = $this->createMock(LoggerInterface::class);
    }

    public function testSavesSubmittedValuesToExistingRow(): void
    {
        $orderAddress = $this->createOrderAddress(7, 'billing');
        $quoteAddressExtended = $this->createQuoteAddressExtended(
            ['entity_id' => 1, 'order_address_id' => 7, 'example_address_field' => 'old', 'house_number' => '12']
        );

        $this->setRequestParams(7, [
            'example_address_field' => '  new  ',
            'house_number' => '',
            'not_configured' => 'ignored',
        ]);
        $this->orderAddressRepository->method('get')->with(7)->willReturn($orderAddress);
        $this->quoteAddressExtendedRepository->method('getByOrderAddress')->with($orderAddress)
            ->willReturn($quoteAddressExtended);
        $this->quoteAddressExtendedRepository->expects($this->once())->method('save')->with($quoteAddressExtended);
        $this->messageManager->expects($this->never())->method('addErrorMessage');

        $this->createObserver()->execute($this->createEventObserver());

        $this->assertSame('new', $quoteAddressExtended->getData('example_address_field'));
        $this->assertNull($quoteAddressExtended->getData('house_number'));
        $this->assertNull($quoteAddressExtended->getData('not_configured'));
        $this->assertSame(7, $quoteAddressExtended->getOrderAddressId());
    }

    public function testSkipsMissingAndNonScalarValues(): void
    {
        $quoteAddressExtended = $this->createQuoteAddressExtended(
            ['entity_id' => 1, 'order_address_id' => 7, 'example_address_field' => 'old', 'house_number' => '12']
        );

        $this->setRequestParams(7, ['example_address_field' => ['array']]);
        $this->orderAddressRepository->method('get')->willReturn($this->createOrderAddress(7, 'billing'));
        $this->quoteAddressExtendedRepository->method('getByOrderAddress')->willReturn($quoteAddressExtended);
        $this->quoteAddressExtendedRepository->expects($this->never())->method('save');

        $this->createObserver()->execute($this->createEventObserver());

        $this->assertSame('old', $quoteAddressExtended->getData('example_address_field'));
        $this->assertSame('12', $quoteAddressExtended->getData('house_number'));
    }

    public function testSkipsSaveWithoutChanges(): void
    {
        $quoteAddressExtended = $this->createQuoteAddressExtended(
            ['entity_id' => 1, 'order_address_id' => 7, 'example_address_field' => 'same']
        );

        $this->setRequestParams(7, ['example_address_field' => 'same']);
        $this->orderAddressRepository->method('get')->willReturn($this->createOrderAddress(7, 'billing'));
        $this->quoteAddressExtendedRepository->method('getByOrderAddress')->willReturn($quoteAddressExtended);
        $this->quoteAddressExtendedRepository->expects($this->never())->method('save');

        $this->createObserver()->execute($this->createEventObserver());
    }

    public function testLinksRowWithoutOrderAddressId(): void
    {
        $quoteAddressExtended = $this->createQuoteAddressExtended(
            ['entity_id' => 1, 'quote_address_id' => 42, 'example_address_field' => 'same']
        );

        $this->setRequestParams(7, ['example_address_field' => 'same']);
        $this->orderAddressRepository->method('get')->willReturn($this->createOrderAddress(7, 'shipping'));
        $this->quoteAddressExtendedRepository->method('getByOrderAddress')->willReturn($quoteAddressExtended);
        $this->quoteAddressExtendedRepository->expects($this->once())->method('save')->with($quoteAddressExtended);

        $this->createObserver()->execute($this->createEventObserver());

        $this->assertSame(7, $quoteAddressExtended->getOrderAddressId());
        $this->assertSame(42, $quoteAddressExtended->getQuoteAddressId());
    }

    public function testCreatesRowWhenMissing(): void
    {
        $quoteAddressExtended = $this->createQuoteAddressExtended([]);

        $this->setRequestParams(7, ['example_address_field' => 'new']);
        $this->orderAddressRepository->method('get')->willReturn($this->createOrderAddress(7, 'shipping'));
        $this->quoteAddressExtendedRepository->method('getByOrderAddress')
            ->willThrowException(new NoSuchEntityException(__('Not found')));
        $this->quoteAddressExtendedRepository->expects($this->once())->method('create')
            ->willReturn($quoteAddressExtended);
        $this->quoteAddressExtendedRepository->expects($this->once())->method('save')->with($quoteAddressExtended);

        $this->createObserver()->execute($this->createEventObserver());

        $this->assertSame('shipping', $quoteAddressExtended->getAddressType());
        $this->assertSame(7, $quoteAddressExtended->getOrderAddressId());
        $this->assertNull($quoteAddressExtended->getQuoteAddressId());
        $this->assertSame('new', $quoteAddressExtended->getData('example_address_field'));
    }

    public function testDoesNotCreateRowWithoutValues(): void
    {
        $this->setRequestParams(7, ['example_address_field' => ' ', 'house_number' => '']);
        $this->orderAddressRepository->method('get')->willReturn($this->createOrderAddress(7, 'shipping'));
        $this->quoteAddressExtendedRepository->method('getByOrderAddress')
            ->willThrowException(new NoSuchEntityException(__('Not found')));
        $this->quoteAddressExtendedRepository->method('create')->willReturn($this->createQuoteAddressExtended([]));
        $this->quoteAddressExtendedRepository->expects($this->never())->method('save');

        $this->createObserver()->execute($this->createEventObserver());
    }

    public function testAddsErrorMessageOnSaveFailure(): void
    {
        $this->setRequestParams(7, ['example_address_field' => 'new']);
        $this->orderAddressRepository->method('get')->willReturn($this->createOrderAddress(7, 'shipping'));
        $this->quoteAddressExtendedRepository->method('getByOrderAddress')
            ->willReturn($this->createQuoteAddressExtended(['entity_id' => 1, 'order_address_id' => 7]));
        $this->quoteAddressExtendedRepository->method('save')
            ->willThrowException(new CouldNotSaveException(__('Failure')));
        $this->messageManager->expects($this->once())->method('addErrorMessage')->with(
            'The address was saved, but the additional address information could not be saved.'
        );
        $this->logger->expects($this->once())->method('error')->with(
            'Loki_QuoteAddressExtended: Could not save the additional address information',
            $this->callback(fn (array $context) => $context['order_address_id'] === 7
                && $context['exception'] instanceof CouldNotSaveException)
        );

        $this->createObserver()->execute($this->createEventObserver());
    }

    public function testDoesNothingWithoutFields(): void
    {
        $this->fields = [];
        $this->setRequestParams(7, ['example_address_field' => 'new']);
        $this->orderAddressRepository->expects($this->never())->method('get');
        $this->quoteAddressExtendedRepository->expects($this->never())->method('save');

        $this->createObserver()->execute($this->createEventObserver());
    }

    public function testDoesNothingWithoutSubmittedValues(): void
    {
        $this->setRequestParams(7, 'invalid');
        $this->orderAddressRepository->expects($this->never())->method('get');
        $this->quoteAddressExtendedRepository->expects($this->never())->method('save');

        $this->createObserver()->execute($this->createEventObserver());
    }

    public function testDoesNothingWithoutAddressId(): void
    {
        $this->setRequestParams(0, ['example_address_field' => 'new']);
        $this->orderAddressRepository->expects($this->never())->method('get');
        $this->quoteAddressExtendedRepository->expects($this->never())->method('save');

        $this->createObserver()->execute($this->createEventObserver());
    }

    public function testDoesNothingWithoutOrderAddress(): void
    {
        $this->setRequestParams(7, ['example_address_field' => 'new']);
        $this->orderAddressRepository->method('get')
            ->willThrowException(new NoSuchEntityException(__('Not found')));
        $this->quoteAddressExtendedRepository->expects($this->never())->method('getByOrderAddress');
        $this->quoteAddressExtendedRepository->expects($this->never())->method('save');

        $this->createObserver()->execute($this->createEventObserver());
    }

    public function testDoesNothingWithOrderAddressThatIsNoOrderAddressModel(): void
    {
        $this->setRequestParams(7, ['example_address_field' => 'new']);
        $this->orderAddressRepository->method('get')
            ->willReturn($this->createMock(OrderAddressInterface::class));
        $this->quoteAddressExtendedRepository->expects($this->never())->method('getByOrderAddress');
        $this->quoteAddressExtendedRepository->expects($this->never())->method('save');

        $this->createObserver()->execute($this->createEventObserver());
    }

    private function setRequestParams(int $addressId, mixed $values): void
    {
        $this->request->method('getParam')->willReturnCallback(
            fn (string $name) => match ($name) {
                'address_id' => (string)$addressId,
                'loki_quote_address_extended' => $values,
                default => null,
            }
        );
    }

    private function createOrderAddress(int $orderAddressId, string $addressType): OrderAddress
    {
        $orderAddress = $this->getMockBuilder(OrderAddress::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();
        $orderAddress->setData(['entity_id' => $orderAddressId, 'address_type' => $addressType]);

        return $orderAddress;
    }

    private function createQuoteAddressExtended(array $data): QuoteAddressExtended
    {
        $quoteAddressExtended = $this->getMockBuilder(QuoteAddressExtended::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();
        $quoteAddressExtended->setData($data);
        $quoteAddressExtended->setHasDataChanges(false);

        return $quoteAddressExtended;
    }

    private function createObserver(): SaveAdminOrderAddressExtended
    {
        return new SaveAdminOrderAddressExtended(
            new AdminFields($this->fields),
            $this->quoteAddressExtendedRepository,
            $this->orderAddressRepository,
            $this->request,
            $this->messageManager,
            $this->logger
        );
    }

    private function createEventObserver(): Observer
    {
        return new Observer(['event' => new Event([])]);
    }
}
