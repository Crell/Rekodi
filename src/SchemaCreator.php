<?php

declare(strict_types=1);

namespace Crell\Rekodi;

use Crell\AttributeUtils\ClassAnalyzer;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Table as DoctrineTable;


class SchemaCreator
{
    public function __construct(
        protected readonly Connection $conn,
        protected ClassAnalyzer $analyzer,
    ) {}

    public function createSchemaDefinition(string $className): DoctrineTable
    {
        $schema = $this->conn->createSchemaManager()->createSchema();

        $tableDef = $this->analyzer->analyze($className, Table::class);

        $table = $schema->createTable($tableDef->name);

        array_map(static fn(Field $f) => $table->addColumn($f->field, $f->doctrineType, $f->options()), $tableDef->fields);

        if ($idFields = $tableDef->getIdFields()) {
            $pkeys = array_map(static fn(Field $f) => $f->field, $idFields);
            $table->setPrimaryKey($pkeys);
        }

        return $table;
    }

}
