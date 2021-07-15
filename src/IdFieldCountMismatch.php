<?php

declare(strict_types=1);

namespace Crell\Rekodi;

class IdFieldCountMismatch extends \InvalidArgumentException
{
    // @todo Make readonly.
    public string $table;
    public int $expectedNumber;
    public int $providedNumber;

    public static function create(string $table, int $expectedNumber, int $providedNumber): static
    {
        $new = new static();
        $new->table = $table;
        $new->expectedNumber = $expectedNumber;
        $new->providedNumber = $providedNumber;

        $new->message = sprintf('The table "%s" expects %d key fields. %d were provided.', $table, $expectedNumber, $providedNumber);

        return $new;
    }
}
