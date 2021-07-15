<?php

declare(strict_types=1);

namespace Crell\Rekodi;

use Crell\Rekodi\Records\MultiKey;
use Crell\Rekodi\Records\Person;
use Crell\Rekodi\Records\PersonSSN;
use Crell\Rekodi\Records\Point;
use \PHPUnit\Framework\TestCase;

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
     */
    public function can_save_objects(): void
    {
        $conn = $this->getConnection();
        $this->ensureTableClass(Point::class);

        $loader = new Loader($conn);

        $p = new Point(x: 5, y: 6, z: 7);

        $loader->save($p);

        $result = $conn->executeQuery("SELECT x, y_axis, z FROM MyPoints WHERE x=?", [5]);
        $data = $result->fetchAssociative();
        self::assertEquals($p->x, $data['x']);
        self::assertEquals($p->y, $data['y_axis']);
        self::assertEquals($p->z, $data['z']);
    }

    /**
     * @test
     */
    public function can_load_objects(): void
    {
        $conn = $this->getConnection();
        $this->ensureTableClass(Point::class);

        $loader = new Loader($conn);

        $loader->save(new Point(x: 1, y: 2, z: 3));
        $loader->save(new Point(x: 4, y: 5, z: 6));
        $loader->save(new Point(x: 1, y: 8, z: 9));

        $result = $conn->executeQuery("SELECT x, y_axis, z FROM MyPoints WHERE x=? ORDER BY x, y_axis, z", [1]);
        $records = iterator_to_array($loader->loadRecords($result, Point::class));
        self::assertEquals(new Point(x: 1, y: 2, z: 3), $records[0]);
        self::assertEquals(new Point(x: 1, y: 8, z: 9), $records[1]);
    }

    /**
     * @test
     */
    public function can_save_new_record_with_no_id(): void
    {
        $conn = $this->getConnection();
        $this->ensureTableClass(Person::class);

        $loader = new Loader($conn);

        // Assume the generated ID is 1, because the table is fresh. We hope.
        $loader->save(new Person(first: 'Larry', last: 'Garfield'));

        $result = $conn->executeQuery("SELECT id, first, last FROM Person");
        $loaded = iterator_to_array($loader->loadRecords($result, Person::class))[0];
        self::assertEquals(1, $loaded->id);
        self::assertEquals('Larry', $loaded->first);
        self::assertEquals('Garfield', $loaded->last);
    }

    /**
     * @test
     */
    public function can_save_and_load_by_autoinc_id(): void
    {
        $conn = $this->getConnection();
        $this->ensureTableClass(Person::class);

        $loader = new Loader($conn);

        // Assume the generated ID is 1, because the table is fresh. We hope.
        $loader->save(new Person(first: 'Larry', last: 'Garfield'));

        $person = $loader->load(Person::class, 1);

        self::assertEquals('Larry', $person->first);
        self::assertEquals('Garfield', $person->last);
        self::assertIsInt($person->id);
        self::assertSame($person->id, 1);
    }

    /**
     * @test
     */
    public function can_save_and_load_by_string_id(): void
    {
        $conn = $this->getConnection();
        $this->ensureTableClass(PersonSSN::class);

        $loader = new Loader($conn);

        $loader->save(new PersonSSN(ssn: '123-45-6789', first: 'Larry', last: 'Garfield'));

        $person = $loader->load(PersonSSN::class, '123-45-6789');

        self::assertEquals('Larry', $person->first);
        self::assertEquals('Garfield', $person->last);
        self::assertEquals('123-45-6789', $person->ssn);
    }

    public function test_load_missing_record(): void
    {
        $conn = $this->getConnection();
        $this->ensureTableClass(PersonSSN::class);

        $loader = new Loader($conn);

        $loader->save(new PersonSSN(ssn: '123-45-6789', first: 'Larry', last: 'Garfield'));

        $ret = $loader->load(PersonSSN::class, '987-654-321');

        self::assertNull($ret);
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
            $loader->delete($class, $del);
        }

        foreach ($deleteIds as $del) {
            self::assertNull($loader->load($class, $del));
        }
    }

    public function can_save_load_and_delete_compound_keys(): void
    {
        $conn = $this->getConnection();
        $this->ensureTableClass(MultiKey::class);

        $loader = new Loader($conn);

        $loader->save(new MultiKey(scope: 4, local: 5, data: 'beep'));

        // @todo I don't love this, because of the array. Maybe there's a better way with variadics?
        $record = $loader->load(MultiKey::class, ['scope' => 4, 'local' => 5]);

        self::assertEquals(4, $record->scope);
        self::assertEquals(5, $record->local);
        self::assertEquals('beep', $record->data);

        $loader->delete(MultiKey::class, ['scope' => 4, 'local' => 5]);

        self::assertNull($loader->load(MultiKey::class, ['scope' => 4, 'local' => 5]));
    }

    public function deletionDataProvider(): iterable
    {
        yield [
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
