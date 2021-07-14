<?php

declare(strict_types=1);

namespace Crell\Rekodi\Records;

use Crell\Rekodi\Id;

class Person
{
    #[Id(generate: true)]
    public int $id;

    public function __construct(
        public string $first,
        public string $last,
    ) {}
}
