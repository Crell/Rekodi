<?php

declare(strict_types=1);

namespace Crell\Rekodi\Records;

use Crell\Rekodi\Id;

/**
 * Basic test for string-based Ids.
 *
 * This ID isn't auto-generated, but it's still marked as the primary key field.
 * It is still loadable/saveable by that ID, but it has to be provided.
 */
class PersonSSN
{
    public function __construct(
        #[Id] public string $ssn,
        public string $first,
        public string $last,
    ) {}
}
