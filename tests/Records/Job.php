<?php

declare(strict_types=1);

namespace Crell\Rekodi\Records;

use Crell\Rekodi\Id;

/**
 * A simple object, that is a foreign relation from Employee.
 */
class Job
{
    #[Id(generate: true)]
    public int $id;

    public function __construct(
        public string $title,
        public string $description,
        public int $pay,
    ) {}
}
