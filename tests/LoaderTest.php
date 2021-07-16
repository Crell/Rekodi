<?php

declare(strict_types=1);

namespace Crell\Rekodi;

use Crell\Rekodi\Records\Employee;
use Crell\Rekodi\Records\MultiKey;
use Crell\Rekodi\Records\Person;
use Crell\Rekodi\Records\PersonSSN;
use Crell\Rekodi\Records\Point;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;

class LoaderTest extends TestCase
{
    use DoctrineConnection;

    public function setUp(): void
    {
        parent::setUp();
        $this->resetDatabase('rekodi');
    }

    /**
     * @test
     * @dataProvider objectSaveProvider
     */
    public function can_save_objects(string $class, array $records, callable $tests): void
    {
        $conn = $this->getConnection();
        $this->ensureTableClass($class);

        $loader = new Loader($conn);

        foreach ($records as $record) {
            $loader->save($record);
        }

        $tests($conn, $loader);
    }

    /**
     * @see can_save_objects()
     */
    public function objectSaveProvider(): iterable
    {
        yield 'Basic save with custom table name' => [
            'class' => Point::class,
            'records' => [
                new Point(x: 5, y: 6, z: 7),
            ],
            'test' => function(Connection $conn, Loader $loader) {
                $result = $conn->executeQuery("SELECT x, y_axis, z FROM MyPoints WHERE x=?", [5]);
                $data = $result->fetchAssociative();
                self::assertEquals(5, $data['x']);
                self::assertEquals(6, $data['y_axis']);
                self::assertEquals(7, $data['z']);
            },
        ];
        yield 'Non-Id objects' => [
            'class' => Point::class,
            'records' => [
                new Point(x: 1, y: 2, z: 3),
                new Point(x: 4, y: 5, z: 6),
                new Point(x: 1, y: 8, z: 9),
            ],
            'test' => function(Connection $conn, Loader $loader) {
                $result = $conn->executeQuery("SELECT x, y_axis, z FROM MyPoints WHERE x=? ORDER BY x, y_axis, z", [1]);
                $records = iterator_to_array($loader->loadRecords(Point::class, $result));
                self::assertEquals(new Point(x: 1, y: 2, z: 3), $records[0]);
                self::assertEquals(new Point(x: 1, y: 8, z: 9), $records[1]);
            },
        ];
        yield 'Auto-increment-id objects (manual)' => [
            'class' => Person::class,
            'records' => [
                new Person(first: 'Larry', last: 'Garfield'),
            ],
            'test' => function(Connection $conn, Loader $loader) {
                $result = $conn->executeQuery("SELECT id, first, last FROM Person");
                $loaded = iterator_to_array($loader->loadRecords(Person::class, $result))[0];
                self::assertEquals(1, $loaded->id);
                self::assertEquals('Larry', $loaded->first);
                self::assertEquals('Garfield', $loaded->last);
            },
        ];
        yield 'Auto-increment-id objects' => [
            'class' => Person::class,
            'records' => [
                new Person(first: 'Larry', last: 'Garfield'),
            ],
            'test' => function(Connection $conn, Loader $loader) {
                $person = $loader->load(Person::class, 1);
                self::assertNotNull($person);
                self::assertEquals('Larry', $person->first);
                self::assertEquals('Garfield', $person->last);
                self::assertIsInt($person->id);
                self::assertSame($person->id, 1);
            },
        ];
        yield 'String-id objects' => [
            'class' => PersonSSN::class,
            'records' => [
                new PersonSSN(ssn: '123-45-6789', first: 'Larry', last: 'Garfield'),
            ],
            'test' => function(Connection $conn, Loader $loader) {
                $person = $loader->load(PersonSSN::class, '123-45-6789');
                self::assertNotNull($person);
                self::assertEquals('Larry', $person->first);
                self::assertEquals('Garfield', $person->last);
                self::assertEquals('123-45-6789', $person->ssn);
            },
        ];
        yield 'Missing record' => [
            'class' => PersonSSN::class,
            'records' => [
                new PersonSSN(ssn: '123-45-6789', first: 'Larry', last: 'Garfield'),
            ],
            'test' => function(Connection $conn, Loader $loader) {
                $ret = $loader->load(PersonSSN::class, '987-654-321');
                self::assertNull($ret);
            },
        ];
        yield 'Multi-key record' => [
            'class' => MultiKey::class,
            'records' => [
                new MultiKey(scope: 4, localId: 5, data: 'beep'),
            ],
            'test' => function(Connection $conn, Loader $loader) {
                $record = $loader->load(MultiKey::class, scope: 4, localId: 5);
                self::assertNotNull($record);
                self::assertEquals(4, $record->scope);
                self::assertEquals(5, $record->localId);
                self::assertEquals('beep', $record->data);
            },
        ];
        yield 'Complex object (Employee)' => [
            'class' => Employee::class,
            'records' => [
                new Employee(
                    first: 'Larry',
                    last: 'Garfield',
                    hireDate: new \DateTimeImmutable('2021-01-01'),
                    tags: ['developer', 'typo3'],
                ),
            ],
            'test' => function(Connection $conn, Loader $loader) {
                $record = $loader->load(Employee::class, 1);
                self::assertNotNull($record);
                self::assertEquals('Larry', $record->first);
                self::assertEquals('Garfield', $record->last);
                self::assertEquals(['developer', 'typo3'], $record->tags);
                self::assertEquals(new \DateTimeImmutable('2021-01-01'), $record->hireDate);
            },
        ];
    }

    /**
     * @test
     * @dataProvider deletionDataProvider
     */
    public function can_delete_by_id(string $class, array $records, array $deleteIds): void
    {
        $conn = $this->getConnection();
        $this->ensureTableClass($class);

        $loader = new Loader($conn);

        foreach ($records as $record) {
            $loader->save($record);
        }

        foreach ($deleteIds as $del) {
            if (is_array($del)) {
                $loader->delete($class, ...$del);
            } else {
                $loader->delete($class, $del);
            }
        }

        foreach ($deleteIds as $del) {
            if (is_array($del)) {
                self::assertNull($loader->load($class, ...$del));
            } else {
                self::assertNull($loader->load($class, $del));
            }
        }
    }

    /**
     * @see can_delete_by_id()
     */
    public function deletionDataProvider(): iterable
    {
        yield 'Single string ID field' => [
            'class' => PersonSSN::class,
            'records' => [
                new PersonSSN('123-45-6789', 'Larry', 'Garfield'),
                new PersonSSN('321-65-4987', 'Jimmy', 'Garfield'),
            ],
            'deleteIds' => [
                '123-45-6789',
                '321-65-4987',
            ]
        ];
        yield 'Multi-key string ID fields' => [
            'class' => MultiKey::class,
            'records' => [
                new MultiKey(scope: 4, localId: 6, data: 'beep'),
            ],
            'deleteIds' => [
                ['scope' => 4, 'localId' => 6],
            ]
        ];
    }

    /**
     * @test
     */
    public function too_few_keys_throws(): void
    {
        $this->expectException(IdFieldCountMismatch::class);

        $conn = $this->getConnection();
        $this->ensureTableClass(MultiKey::class);

        $loader = new Loader($conn);

        $loader->save(new MultiKey(scope: 4, localId: 5, data: 'beep'));

        $loader->load(MultiKey::class, scope: 4);
    }

    /**
     * @test
     */
    public function too_many_keys_throws(): void
    {
        $this->expectException(IdFieldCountMismatch::class);

        $conn = $this->getConnection();
        $this->ensureTableClass(MultiKey::class);

        $loader = new Loader($conn);

        $loader->save(new MultiKey(scope: 4, localId: 5, data: 'beep'));

        $loader->load(MultiKey::class, scope: 4, localId: 5, data: 'beep');
    }

    /**
     * @test
     */
    public function numeric_multi_key_throws(): void
    {
        $this->expectException(MultiKeyIdHasNumericKeys::class);

        $conn = $this->getConnection();
        $this->ensureTableClass(MultiKey::class);

        $loader = new Loader($conn);

        $loader->save(new MultiKey(scope: 4, localId: 5, data: 'beep'));

        $loader->load(MultiKey::class, ...[4, 5]);
    }

    protected function ensureTableClass(string $class): void
    {
        $conn = $this->getConnection();
        // Make the table.
        $schema = new SchemaCreator($conn);
        $table = $schema->createSchemaDefinition($class);
        $conn->createSchemaManager()->createTable($table);
    }
}
