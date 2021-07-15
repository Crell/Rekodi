<?php

declare(strict_types=1);

namespace Crell\Rekodi\Records;

use Crell\Rekodi\Id;

class PersonSSN
{

    public function __construct(
        #[Id]
        public string $ssn,
        public string $first,
        public string $last,
    ) {}
}
