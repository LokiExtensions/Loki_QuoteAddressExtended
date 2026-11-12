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
        ];
    }
}
