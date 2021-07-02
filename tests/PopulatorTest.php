<?php

declare(strict_types=1);

namespace Crell\Rekodi;

use \PHPUnit\Framework\TestCase;

class PopulatorTest extends TestCase
{

    /**
     * @test
     * @dataProvider validConstructionsProvider
     */
    public function valid_constructions_populate(string $class, array $data): void
    {
        $p = new Populator();
        $result = $p->populate($class, $data);

        self::assertInstanceOf($class, $result);
    }

    public function validConstructionsProvider(): iterable
    {
        yield [
            'class' => Point::class,
            'data' => ['x' => 1, 'y' => 2, 'z' => 3],
        ];
        yield [
            'class' => Point::class,
            'data' => ['y' => 2, 'z' => 3, 'x' => 1,],
        ];
        yield [
            'class' => OptionalPoint::class,
            'data' => ['y' => 2, 'x' => 1,],
        ];
        yield [
            'class' => OptionalPoint::class,
            'data' => [],
        ];
    }
}

class Point
{
    public function __construct(
        public int $x,
        public int $y,
        public int $z,
    ) {}
}

class OptionalPoint
{
    public function __construct(
        public int $x = 0,
        public int $y = 0,
        public int $z = 0,
    ) {}
}
