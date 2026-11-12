<?php

declare(strict_types=1);

namespace Loki\QuoteAddressExtended\Config;

use Loki\QuoteAddressExtended\Repository\QuoteAddressExtendedRepository;

class AdminFields
{
    public function __construct(
        private readonly array $fields = []
    ) {
    }

    public function getFields(): array
    {
        $fields = [];
        foreach ($this->fields as $column => $label) {
            if (false === is_string($column) || false === is_string($label) || $label === '') {
                continue;
            }

            if (in_array($column, QuoteAddressExtendedRepository::RESERVED_COLUMNS, true)) {
                continue;
            }

            $fields[$column] = $label;
        }

        return $fields;
    }
}
