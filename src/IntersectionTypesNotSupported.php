<?php

declare(strict_types=1);

namespace Crell\Rekodi;

class IntersectionTypesNotSupported extends \TypeError
{
    // @todo Use this in the error message.
    public readonly \ReflectionProperty $property;

    public static function create(\ReflectionProperty $property): static
    {
        $new = new static();
        $new->property = $property;
        return $new;
    }
}
