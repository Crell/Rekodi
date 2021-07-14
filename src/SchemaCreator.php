<?php

declare(strict_types=1);

namespace Crell\Rekodi;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Table as DoctrineTable;


class SchemaCreator
{
    use AttributeUtil;

    public function __construct(protected Connection $conn) {}

    public function createSchemaDefinition(string $className): DoctrineTable
    {
        $schema = $this->conn->createSchemaManager()->createSchema();

        $rClass = new \ReflectionClass($className);

        $tableDef = $this->getAttribute($rClass, Table::class) ?? new Table(name: $this->baseClassName($className));

        $table = $schema->createTable($tableDef->name);
        $fields = $this->getFieldDefinitions($rClass);
        $tableDef->setFields($fields);

        array_map(static fn(Field $f) => $table->addColumn($f->field, $f->type, $f->options()), $fields);

        if ($idFields = $tableDef->getIdFields()) {
            $pkeys = array_map(static fn(Field $f) => $f->field, $idFields);
            $table->setPrimaryKey($pkeys);
        }

        return $table;
    }

}
