<?php

declare(strict_types=1);

namespace Crell\Rekodi\Records;

/**
 * A basic field that will not exist on its own.
 *
 * This is a dependent value of Employee, and so should get saved
 * serialized to a column in that table.
 *
 * Although if used on its own, it would get its own table. Whether
 * that is good or bad is, I suppose, debatable. :-)
 */
class Address
{
    public function __construct(
        public int $number,
        public string $street,
        public string $city,
        public string $state,
        public string $zip,
    ) {}
}
