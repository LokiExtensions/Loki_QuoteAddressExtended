<?php

declare(strict_types=1);

namespace Loki\QuoteAddressExtended\Plugin;

use Loki\QuoteAddressExtended\Observer\AddQuoteAddressExtendedToAddressFormat;
use Magento\Customer\Block\Address\Renderer\DefaultRenderer;
use Magento\Framework\Escaper;

class RenderQuoteAddressExtendedInAddressTemplate
{
    public function __construct(
        private readonly Escaper $escaper
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

        $tokenPrefix = 'LOKI' . bin2hex(random_bytes(8));
        $tokens = [];
        foreach ($values as $column => $value) {
            if (false === is_string($column) || 1 !== preg_match('/^[a-z0-9_]+$/i', $column)) {
                continue;
            }

            $token = $tokenPrefix . count($tokens) . 'X';
            $format = $this->removeConditionals($format, $column);
            $format = (string)preg_replace(
                '/\{\{var\s+' . $column . '(\s*\|[^}]*)?\s*\}\}/i',
                $token,
                $format
            );
            $tokens[$token] = (string)$value;
        }

        $rendered = (string)$proceed($addressAttributes, $format);
        $type = $subject->getType();
        if ($type?->getEscapeHtml() || $type?->getCode() === 'html') {
            $tokens = array_map(fn (string $value) => (string)$this->escaper->escapeHtml($value), $tokens);
        }

        return strtr($rendered, $tokens);
    }

    private function removeConditionals(string $format, string $column): string
    {
        foreach (['depend', 'if'] as $directive) {
            $format = (string)preg_replace_callback(
                '/\{\{' . $directive . '\s+' . $column . '\s*\}\}(.*?)\{\{\/' . $directive . '\}\}/is',
                fn (array $matches) => preg_split('/\{\{else\}\}/i', $matches[1])[0],
                $format
            );
        }

        return $format;
    }
}
