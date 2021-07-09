<?php

declare(strict_types=1);

namespace Crell\Rekodi;

use Crell\Rekodi\Records\OptionalPoint;
use Crell\Rekodi\Records\Point;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;

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
     * @dataProvider tableCreationProvider
     */
    public function can_create_tables_from_classes(string $class, string $tableName, array $expectedColumns): void
    {
        $conn = $this->getConnection();

        $subject = new SchemaCreator($conn);
        $table = $subject->createSchemaDefinition($class);

        $schemaManager = $conn ->createSchemaManager();
        $schemaManager->createTable($table);

        self::assertTrue($schemaManager->tablesExist($tableName));

        $columns = $schemaManager->listTableColumns($tableName);
        foreach ($expectedColumns as $columnName => $def) {
            self::assertArrayHasKey($columnName, $columns);
            if (isset($def['type'])) {
                self::assertEquals($def['type'], $columns[$columnName]->getType()->getName());
            }
            if (isset($def['default'])) {
                self::assertEquals($def['default'], $columns[$columnName]->getDefault());
            }
        }
    }

    public function tableCreationProvider(): iterable
    {
        yield Point::class => [
            'class' => Point::class,
            'table' => 'MyPoints',
            'expectedColumns' => [
                'x' => [
                    'type' => 'integer',
                ],
                'y_axis' => [
                    'type' => 'integer',
                ],
                'z' => [
                    'type' => 'integer',
                ]
            ],
        ];
        yield OptionalPoint::class => [
            'class' => OptionalPoint::class,
            'table' => 'OptionalPoint',
            'expectedColumns' => [
                'x' => [
                    'type' => 'integer',
                    'default' => 0,
                ],
                'y' => [
                    'type' => 'integer',
                    'default' => 0,
                ],
                'z' => [
                    'type' => 'integer',
                    'default' => 0,
                ]
            ],
        ];
    }
}
