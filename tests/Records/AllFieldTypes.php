<?php

declare(strict_types=1);

namespace Crell\Rekodi\Records;

/**
 * A test class that includes meaningful type of field, for testing purposes.
 */
class AllFieldTypes
{
    public function __construct(
        public int $anint = 0,
        public string $string = '',
        public float $afloat = 0,
        public ?\DateTimeImmutable $dateTimeImmutable = null,
//        public ?\DateTime $dateTime = null,
        public array $simpleArray = [],
        public array $assocArray = [],
        public ?Point $simpleObject = null,
    ) {}
}
