<?php

declare(strict_types=1);

namespace Crell\Rekodi;

use Crell\Rekodi\Records\MultiKey;
use Crell\Rekodi\Records\OptionalPoint;
use Crell\Rekodi\Records\Person;
use Crell\Rekodi\Records\PersonSSN;
use Crell\Rekodi\Records\Point;
use PHPUnit\Framework\TestCase;

class SchemaCreatorTest extends TestCase
{
    use DoctrineConnection;

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
            if (isset($def['autoincrement'])) {
                self::assertTrue($columns[$columnName]->getAutoincrement());
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
        yield Person::class => [
            'class' => Person::class,
            'table' => 'Person',
            'expectedColumns' => [
                'id' => [
                    'type' => 'integer',
                    'autoincrement' => true,
                ],
                'first' => [
                    'type' => 'string',
                ],
                'last' => [
                    'type' => 'string',
                ],
            ],
        ];
        yield PersonSSN::class => [
            'class' => PersonSSN::class,
            'table' => 'PersonSSN',
            'expectedColumns' => [
                'ssn' => [
                    'type' => 'string',
                ],
                'first' => [
                    'type' => 'string',
                ],
                'last' => [
                    'type' => 'string',
                ],
            ],
        ];
        yield MultiKey::class => [
            'class' => MultiKey::class,
            'table' => 'MultiKey',
            'expectedColumns' => [
                'scope' => [
                    'type' => 'integer',
                ],
                'local' => [
                    'type' => 'integer',
                ],
                'data' => [
                    'type' => 'string',
                ],
            ]
        ];
    }
}
