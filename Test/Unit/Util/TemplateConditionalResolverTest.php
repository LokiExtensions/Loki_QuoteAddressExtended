<?php

declare(strict_types=1);

namespace Loki\QuoteAddressExtended\Test\Unit\Util;

use Loki\QuoteAddressExtended\Util\TemplateConditionalResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TemplateConditionalResolverTest extends TestCase
{
    #[DataProvider('provideTemplates')]
    public function testResolve(string $template, array $values, string $expected): void
    {
        $this->assertSame($expected, (new TemplateConditionalResolver())->resolve($template, $values));
    }

    public function testKeepsTemplateWhenPcreFails(): void
    {
        $backtrackLimit = ini_get('pcre.backtrack_limit');
        ini_set('pcre.backtrack_limit', '1');
        try {
            $template = '{{depend a}}' . str_repeat('x', 1000) . '{{/depend}}';
            $result = (new TemplateConditionalResolver())->resolve($template, ['a' => 'x']);
        } finally {
            ini_set('pcre.backtrack_limit', (string)$backtrackLimit);
        }

        $this->assertSame($template, $result);
    }

    public static function provideTemplates(): array
    {
        return [
            'depend with value' => ['[{{depend a}}A{{/depend}}]', ['a' => 'x'], '[A]'],
            'depend without value' => ['[{{depend a}}A{{/depend}}]', ['a' => ''], '[]'],
            'nested depend' => [
                '{{depend a}}A{{depend b}}B{{/depend}}C{{/depend}}|{{depend b}}{{depend a}}X{{/depend}}Y{{/depend}}',
                ['a' => 'x', 'b' => ''],
                'AC|',
            ],
            'nested if' => [
                '{{if a}}A{{if b}}B{{else}}NB{{/if}}{{else}}NA{{/if}}',
                ['a' => 'x', 'b' => ''],
                'ANB',
            ],
            'nested if in else branch' => [
                '{{if a}}A{{else}}{{if b}}B{{else}}NB{{/if}}{{/if}}',
                ['a' => '', 'b' => 'y'],
                'B',
            ],
            'depend inside if' => [
                '{{if a}}{{depend b}}B{{/depend}}A{{else}}{{depend b}}NB{{/depend}}{{/if}}',
                ['a' => '', 'b' => 'y'],
                'NB',
            ],
            'if inside depend' => [
                '{{depend a}}{{if b}}B{{else}}NB{{/if}}{{/depend}}',
                ['a' => 'x', 'b' => ''],
                'NB',
            ],
            'if else with value' => ['{{if a}}yes{{else}}no{{/if}}', ['a' => 'x'], 'yes'],
            'if else without value' => ['{{if a}}yes{{else}}no{{/if}}', ['a' => ''], 'no'],
            'if without else and without value' => ['[{{if a}}yes{{/if}}]', ['a' => ''], '[]'],
            'unknown variable is kept' => [
                '{{depend company}}C{{/depend}}{{if company}}C{{else}}N{{/if}}',
                ['a' => 'x'],
                '{{depend company}}C{{/depend}}{{if company}}C{{else}}N{{/if}}',
            ],
            'known block inside unknown block' => [
                '{{depend company}}{{var company}}{{depend a}} - {{var a}}{{/depend}}{{/depend}}',
                ['a' => ''],
                '{{depend company}}{{var company}}{{/depend}}',
            ],
            'unknown block inside known block' => [
                '{{if a}}{{depend company}}C{{/depend}}A{{else}}N{{/if}}',
                ['a' => 'x'],
                '{{depend company}}C{{/depend}}A',
            ],
            'case insensitive directives' => ['{{IF a}}yes{{ELSE}}no{{/IF}}', ['a' => ''], 'no'],
            'unbalanced block is kept' => ['{{depend a}}A', ['a' => 'x'], '{{depend a}}A'],
            'without blocks' => ['{{var a}}', ['a' => 'x'], '{{var a}}'],
            'whitespace variants' => ['{{depend   a  }}A{{/depend }}{{if a }}B{{else }}C{{/if}}', ['a' => 'x'], 'AB'],
            'multi-line body' => [
                "{{depend a}}\nline 1\n{{if b}}x{{else}}line 2\n{{/if}}{{/depend}}",
                ['a' => 'x', 'b' => ''],
                "\nline 1\nline 2\n",
            ],
            'three nesting levels' => [
                '{{depend a}}1{{if b}}2{{depend c}}3{{/depend}}{{else}}N{{/if}}{{/depend}}',
                ['a' => 'x', 'b' => 'y', 'c' => ''],
                '12',
            ],
            'empty known column with unknown nested block' => [
                '{{depend a}}A{{depend company}}C{{/depend}}B{{/depend}}|{{var company}}',
                ['a' => ''],
                '|{{var company}}',
            ],
        ];
    }
}
