<?php

declare(strict_types=1);

namespace Crell\Rekodi;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Table as DoctrineTable;


class SchemaCreator
{
    use AttributeUtil;

    public function __construct(protected Connection $conn) {}

    // @todo There's a bug in here somewhere that lowercases all field names, instead
    // of respecting mixed case.  I expect it's a Doctrine bug of some sort.
    // Need to track that down.  It doesn't seem to happen to table names.
    public function createSchemaDefinition(string $className): DoctrineTable
    {
        $schema = $this->conn->createSchemaManager()->createSchema();

        $tableDef = $this->tableDefinition($className);

        $table = $schema->createTable($tableDef->name);

        array_map(static fn(Field $f) => $table->addColumn($f->field, $f->type, $f->options()), $tableDef->fields);

        if ($idFields = $tableDef->getIdFields()) {
            $pkeys = array_map(static fn(Field $f) => $f->field, $idFields);
            $table->setPrimaryKey($pkeys);
        }

        return $table;
    }

}
