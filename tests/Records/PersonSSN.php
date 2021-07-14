<?php

declare(strict_types=1);

namespace Crell\Rekodi\Records;

use Crell\Rekodi\Id;

class PersonSSN
{
    #[Id]
    public string $ssn;

    public function __construct(
        public string $first,
        public string $last,
    ) {}
}
