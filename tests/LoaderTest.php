<?php

declare(strict_types=1);

namespace Crell\Rekodi;

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

        $loader = new Loader($conn);

        // Make the table.
        $schema = new SchemaCreator($conn);
        $table = $schema->createSchemaDefinition(Point::class);
        $conn->createSchemaManager()->createTable($table);

        $p = new Point(x: 5, y: 6, z: 7);

        $loader->save($p);

        $result = $conn->executeQuery("SELECT x, y_axis, z FROM MyPoints WHERE x=?", [5]);
        $data = $result->fetchAssociative();
        self::assertEquals($p->x, $data['x']);
        self::assertEquals($p->y, $data['y_axis']);
        self::assertEquals($p->z, $data['z']);
    }
}
