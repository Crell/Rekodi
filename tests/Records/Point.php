<?php

declare(strict_types=1);

namespace Crell\Rekodi\Records;
use Crell\Rekodi\Field;
use Crell\Rekodi\Table;

/**
 * The most basic test for a non-entity record.
 *
 * Saving one of these objects should always create a new record.
 * It also cannot be loaded by ID, obviously, but you can load it
 * directly from a query result.
 */
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
