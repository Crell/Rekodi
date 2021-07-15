<?php

declare(strict_types=1);

namespace Crell\Rekodi\Records;

use Crell\Rekodi\Id;

/**
 * An example record that has a compound key.
 */
class MultiKey
{
    public function __construct(
        #[Id] public int $scope,
        #[Id] public int $local,
        public string $data,
    ) {}
}
