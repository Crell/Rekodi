<?php

declare(strict_types=1);

namespace Crell\Rekodi;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Id
{
    public function __construct(
        public bool $generate = false,
    ) {}
}
