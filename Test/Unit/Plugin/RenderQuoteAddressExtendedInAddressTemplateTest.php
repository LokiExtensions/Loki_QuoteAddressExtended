<?php

declare(strict_types=1);

namespace Loki\QuoteAddressExtended\Test\Unit\Plugin;

use Loki\QuoteAddressExtended\Plugin\RenderQuoteAddressExtendedInAddressTemplate;
use Magento\Customer\Block\Address\Renderer\DefaultRenderer;
use Magento\Framework\DataObject;
use Magento\Framework\Escaper;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class RenderQuoteAddressExtendedInAddressTemplateTest extends TestCase
{
    private DefaultRenderer&MockObject $renderer;
    private ?string $receivedFormat = null;

    protected function setUp(): void
    {
        $this->renderer = $this->createMock(DefaultRenderer::class);
        $this->receivedFormat = null;
    }

    public function testProceedsUnchangedWithoutExtendedValues(): void
    {
        $this->renderer->expects($this->never())->method('getFormatArray');

        $result = $this->createPlugin()->aroundRenderArray(
            $this->renderer,
            $this->createProceed('rendered'),
            ['firstname' => 'John'],
            '{{var firstname}}'
        );

        $this->assertSame('rendered', $result);
        $this->assertSame('{{var firstname}}', $this->receivedFormat);
    }

    public function testProceedsUnchangedForNonArrayAttributes(): void
    {
        $result = $this->createPlugin()->aroundRenderArray(
            $this->renderer,
            $this->createProceed('rendered'),
            null,
            null
        );

        $this->assertSame('rendered', $result);
        $this->assertNull($this->receivedFormat);
    }

    public function testReplacesVariablesAndDirectivesWithEscapedValuesForHtml(): void
    {
        $this->mockType('html', false);

        $result = $this->createPlugin()->aroundRenderArray(
            $this->renderer,
            fn (array $attributes, string $format) => $format,
            ['loki_quote_address_extended_values' => ['field_a' => '<b>A</b>', 'field_b' => 'B']],
            '{{depend field_a}}[{{var field_a}}]{{/depend}}{{if field_b}}({{var field_b|escape}}){{else}}none{{/if}}'
        );

        $this->assertSame('[&lt;b&gt;A&lt;/b&gt;](B)', $result);
    }

    public function testDoesNotEscapeForPdf(): void
    {
        $this->mockType('pdf', false);

        $result = $this->createPlugin()->aroundRenderArray(
            $this->renderer,
            fn (array $attributes, string $format) => $format,
            ['loki_quote_address_extended_values' => ['field_a' => 'A & B']],
            '{{var firstname}}|{{var field_a}}|'
        );

        $this->assertSame('{{var firstname}}|A & B|', $result);
    }

    public function testUsesFormatOfRendererWhenNoFormatIsGiven(): void
    {
        $this->mockType('text', false);
        $attributes = ['loki_quote_address_extended_values' => ['field_a' => 'A']];
        $this->renderer->expects($this->once())->method('getFormatArray')->with($attributes)
            ->willReturn('[{{var field_a}}]');

        $result = $this->createPlugin()->aroundRenderArray(
            $this->renderer,
            fn (array $attributes, string $format) => $format,
            $attributes
        );

        $this->assertSame('[A]', $result);
    }

    public function testSkipsInvalidColumnNames(): void
    {
        $this->mockType('text', true);

        $result = $this->createPlugin()->aroundRenderArray(
            $this->renderer,
            fn (array $attributes, string $format) => $format,
            ['loki_quote_address_extended_values' => ['field.*' => 'X', 0 => 'Y', 'field_a' => 'A']],
            '{{var field.*}}{{var field_a}}'
        );

        $this->assertSame('{{var field.*}}A', $result);
    }

    private function mockType(string $code, bool $escapeHtml): void
    {
        $this->renderer->method('getType')->willReturn(new DataObject([
            'code' => $code,
            'escape_html' => $escapeHtml,
        ]));
    }

    private function createProceed(string $result): callable
    {
        return function (mixed $attributes, mixed $format) use ($result) {
            $this->receivedFormat = $format;
            return $result;
        };
    }

    private function createPlugin(): RenderQuoteAddressExtendedInAddressTemplate
    {
        return new RenderQuoteAddressExtendedInAddressTemplate(new Escaper());
    }
}
