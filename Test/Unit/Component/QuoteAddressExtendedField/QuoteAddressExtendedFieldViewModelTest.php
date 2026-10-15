<?php

declare(strict_types=1);

namespace Loki\QuoteAddressExtended\Test\Unit\Component\QuoteAddressExtendedField;

use Loki\Components\Attribute\JsProperty;
use Loki\Components\Component\ComponentInterface;
use Loki\QuoteAddressExtended\Component\QuoteAddressExtendedField\QuoteAddressExtendedFieldViewModel;
use LokiCheckout\Core\Component\Base\Generic\CheckoutContext;
use Magento\Quote\Model\Quote;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class QuoteAddressExtendedFieldViewModelTest extends TestCase
{
    public function testGetStepReturnsResolvedStep(): void
    {
        $context = $this->createMock(CheckoutContext::class);
        $context->expects($this->never())->method('getQuote');

        $viewModel = $this->createViewModel($context);
        $viewModel->setStep('billing');

        $this->assertSame('billing', $viewModel->getStep());
    }

    public static function getStepFallbackProvider(): array
    {
        return [
            [false, 'shipping'],
            [true, 'billing'],
        ];
    }

    #[DataProvider('getStepFallbackProvider')]
    public function testGetStepFallsBackOnQuoteType(bool $isVirtual, string $expectedStep): void
    {
        $quote = $this->createMock(Quote::class);
        $quote->method('isVirtual')->willReturn($isVirtual);
        $context = $this->createMock(CheckoutContext::class);
        $context->method('getQuote')->willReturn($quote);

        $viewModel = $this->createViewModel($context);
        $viewModel->setStep('none');

        $this->assertSame($expectedStep, $viewModel->getStep());
    }

    public function testGetStepIsExposedAsJsProperty(): void
    {
        $method = new ReflectionMethod(QuoteAddressExtendedFieldViewModel::class, 'getStep');
        $jsPropertyNames = array_map(
            fn ($attribute) => $attribute->newInstance()->name,
            $method->getAttributes(JsProperty::class)
        );

        $this->assertSame(['step'], $jsPropertyNames);
    }

    private function createViewModel(CheckoutContext $context): QuoteAddressExtendedFieldViewModel
    {
        $component = $this->createMock(ComponentInterface::class);
        $component->method('getContext')->willReturn($context);

        $viewModel = new QuoteAddressExtendedFieldViewModel();
        $viewModel->setComponent($component);

        return $viewModel;
    }
}
