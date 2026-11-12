<?php

declare(strict_types=1);

namespace Loki\QuoteAddressExtended\Plugin;

use Loki\QuoteAddressExtended\Observer\AddQuoteAddressExtendedToAddressFormat;
use Loki\QuoteAddressExtended\Util\TemplateConditionalResolver;
use Magento\Customer\Block\Address\Renderer\DefaultRenderer;
use Magento\Framework\Escaper;

class RenderQuoteAddressExtendedInAddressTemplate
{
    public function __construct(
        private readonly Escaper $escaper,
        private readonly TemplateConditionalResolver $templateConditionalResolver
    ) {
    }

    public function aroundRenderArray(
        DefaultRenderer $subject,
        callable $proceed,
        mixed $addressAttributes,
        mixed $format = null
    ): mixed {
        $values = is_array($addressAttributes)
            ? ($addressAttributes[AddQuoteAddressExtendedToAddressFormat::DATA_KEY] ?? [])
            : [];
        if (false === is_array($values) || [] === $values) {
            return $proceed($addressAttributes, $format);
        }

        $format = (string)($format ?? $subject->getFormatArray($addressAttributes));
        if ($format === '') {
            return $proceed($addressAttributes, $format);
        }

        $values = array_filter(
            $values,
            fn (mixed $value, mixed $column) => is_string($column)
                && 1 === preg_match('/^[a-z0-9_]+$/i', $column)
                && is_scalar($value),
            ARRAY_FILTER_USE_BOTH
        );
        $format = $this->templateConditionalResolver->resolve($format, $values);

        $tokenPrefix = 'LOKI' . bin2hex(random_bytes(8));
        $tokens = [];
        foreach ($values as $column => $value) {
            $token = $tokenPrefix . count($tokens) . 'X';
            $format = preg_replace(
                '/\{\{var\s+' . $column . '(\s*\|[^}]*)?\s*\}\}/i',
                $token,
                $format
            ) ?? $format;
            $tokens[$token] = (string)$value;
        }

        $rendered = (string)$proceed($addressAttributes, $format);
        $type = $subject->getType();
        if ($type?->getEscapeHtml() || $type?->getCode() === 'html') {
            $tokens = array_map(fn (string $value) => (string)$this->escaper->escapeHtml($value), $tokens);
        }

        return strtr($rendered, $tokens);
    }
}
