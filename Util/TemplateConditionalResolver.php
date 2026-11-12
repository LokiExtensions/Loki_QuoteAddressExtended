<?php

declare(strict_types=1);

namespace Loki\QuoteAddressExtended\Util;

class TemplateConditionalResolver
{
    private const INNERMOST_BLOCK = '/\{\{(depend|if)\s+([^}\s]+)\s*\}\}'
        . '((?:(?!\{\{(?:depend|if)\s|\{\{\/(?:depend|if)\s*\}\}).)*)'
        . '\{\{\/\1\s*\}\}/is';

    public function resolve(string $template, array $values): string
    {
        $placeholderPrefix = 'LOKIBLOCK' . bin2hex(random_bytes(8));
        $placeholders = [];

        do {
            $resolved = preg_replace_callback(
                self::INNERMOST_BLOCK,
                function (array $matches) use ($values, $placeholderPrefix, &$placeholders): string {
                    [$block, $directive, $variable, $body] = $matches;
                    if (false === array_key_exists($variable, $values)) {
                        $placeholder = $placeholderPrefix . count($placeholders) . 'X';
                        $placeholders[$placeholder] = $block;
                        return $placeholder;
                    }

                    $parts = strtolower($directive) === 'if'
                        ? preg_split('/\{\{else\s*\}\}/i', $body, 2)
                        : [$body];

                    return (string)$values[$variable] !== '' ? $parts[0] : (string)($parts[1] ?? '');
                },
                $template,
                -1,
                $count
            );
            if (null === $resolved) {
                break;
            }

            $template = $resolved;
        } while ($count > 0);

        while (str_contains($template, $placeholderPrefix)) {
            $template = strtr($template, $placeholders);
        }

        return $template;
    }
}
