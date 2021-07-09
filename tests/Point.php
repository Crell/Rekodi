<?php

declare(strict_types=1);

namespace Crell\Rekodi;

#[Table(name: 'MyPoints')]
class Point
{
    public function __construct(
        #[Field(field: 'x')]
        public int $x,
        #[Field(field: 'y_axis')]
        public int $y,
        public int $z,
    ) {}
}
