<?php

declare(strict_types=1);

namespace Crell\Rekodi;

use Doctrine\DBAL\Connection;
use \PHPUnit\Framework\TestCase;
use Crell\Rekodi\Records\Point;

class SchemaCreatorTest extends TestCase
{
    use DoctrineConnection;

    protected Connection $conn;

    public function setUp(): void
    {
        parent::setUp();
        $this->resetDatabase('rekodi');
    }

    /**
     * @test
     */
    public function stuff(): void
    {
        $conn = $this->getConnection();

        $subject = new SchemaCreator($conn);
        $table = $subject->createSchemaDefinition(Point::class);

        $schemaManager = $conn ->createSchemaManager();
        $schemaManager->createTable($table);

        $columns = $schemaManager->listTableColumns('MyPoints');

        self::assertTrue($schemaManager->tablesExist('MyPoints'));
        self::assertArrayHasKey('x', $columns);
        self::assertArrayHasKey('y_axis', $columns);
        self::assertArrayHasKey('z', $columns);
        self::assertEquals('integer', $columns['x']->getType()->getName());
    }


}
