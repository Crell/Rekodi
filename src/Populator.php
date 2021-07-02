<?php

declare(strict_types=1);

namespace Crell\Rekodi;

class Populator
{

    public function populate(string $class, array $data): object
    {
        return new $class(...$data);
    }
}
