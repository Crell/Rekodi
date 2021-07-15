<?php

declare(strict_types=1);

namespace Crell\Rekodi\Records;

use Crell\Rekodi\Id;

/**
 * Basic test for auto-inc IDs.
 *
 * The ID field cannot be in the constructor, because we need to allow
 * for newly created objects to not have one.  By making it `public readonly`,
 * it can be hydrated by the loading process and accessible, but then immutable.
 * Whereas for a new object, it cannot be set so it will get set automatically
 * by the database on save.
 */
class Person
{
    #[Id(generate: true)]
    public int $id;

    public function __construct(
        public string $first,
        public string $last,
    ) {}
}
