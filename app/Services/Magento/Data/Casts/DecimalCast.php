<?php

namespace App\Services\Magento\Data\Casts;

use Spatie\LaravelData\Casts\Cast;
use Spatie\LaravelData\Support\Creation\CreationContext;
use Spatie\LaravelData\Support\DataProperty;

/**
 * Magento sends amounts as JSON numbers (34, 19.99): keep them as exact decimal strings with 4 digits.
 */
class DecimalCast implements Cast
{
    /**
     * @param  array<string, mixed>  $properties
     */
    public function cast(DataProperty $property, mixed $value, array $properties, CreationContext $context): string
    {
        $number = is_float($value) ? rtrim(sprintf('%.10F', $value), '0') : (string) $value;

        return bcadd($number, '0', 4);
    }
}
