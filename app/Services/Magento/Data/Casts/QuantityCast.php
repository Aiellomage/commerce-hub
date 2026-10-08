<?php

namespace App\Services\Magento\Data\Casts;

use Spatie\LaravelData\Casts\Cast;
use Spatie\LaravelData\Support\Creation\CreationContext;
use Spatie\LaravelData\Support\DataProperty;

/**
 * Magento stores ordered quantities as decimals (1.0000): the Hub only sells whole pieces.
 */
class QuantityCast implements Cast
{
    /**
     * @param  array<string, mixed>  $properties
     */
    public function cast(DataProperty $property, mixed $value, array $properties, CreationContext $context): int
    {
        return (int) round((float) $value);
    }
}
