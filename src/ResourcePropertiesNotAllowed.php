<?php

declare(strict_types=1);

namespace Crell\Rekodi;

class ResourcePropertiesNotAllowed extends \InvalidArgumentException
{
    // @todo Make readonly.
    public string $name;

    public static function create(string $name): static
    {
        $new = new static();
        $new->name = $name;

        $new->message = sprintf('Resource properties cannot be persisted.  Please exclude %s.', $name);

        return $new;
    }
}
