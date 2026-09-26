<?php

namespace App\Models\Concerns;

use InvalidArgumentException;

/**
 * Guards decimal columns on the way in.
 *
 * Spec §0.7: no raw float may take part in an amount. Laravel's native
 * `decimal:2` cast formats on read, but on write it will happily take a PHP
 * float, which is where a rounding error would enter and stay.
 *
 * So money and percentages are assigned as strings ("1234.56", and "1234,56"
 * because the user types a decimal comma, spec §10.5). This trait rejects
 * anything else at the boundary, where the mistake is still easy to find.
 */
trait GuardsDecimalInput
{
    /**
     * @param  array<string, int>  $fields  field => scale, e.g. ['avg_price_eur_t' => 2]
     */
    protected function guardDecimalInput(array $fields): void
    {
        $attributes = $fields;
        foreach ($attributes as $field => $scale) {
            if (! array_key_exists($field, $this->getAttributes())) {
                continue;
            }

            $value = $this->getAttributes()[$field];
            if ($value === null || is_string($value)) {
                continue;
            }

            throw new InvalidArgumentException(sprintf(
                '%s::$%s must be assigned a decimal string, got %s. '
                .'Money and percentages are never PHP floats (spec §0.7).',
                static::class,
                $field,
                get_debug_type($value),
            ));
        }
    }
}
