<?php

declare(strict_types=1);

namespace Crell\Rekodi\Records;

class OptionalPoint
{
    public function __construct(
        public int $x = 0,
        public int $y = 0,
        public int $z = 0,
    ) {}
}
