<?php

namespace App\Services\Magento\Data\Casts;

use Spatie\LaravelData\Casts\Cast;
use Spatie\LaravelData\Support\Creation\CreationContext;
use Spatie\LaravelData\Support\DataProperty;

/**
 * Turns Magento's [{"attribute_code": "url_key", "value": "..."}] list into ["url_key" => "..."].
 */
class CustomAttributesCast implements Cast
{
    /**
     * @param  array<string, mixed>  $properties
     * @return array<string, mixed>
     */
    public function cast(DataProperty $property, mixed $value, array $properties, CreationContext $context): array
    {
        return collect($value)->pluck('value', 'attribute_code')->all();
    }
}
