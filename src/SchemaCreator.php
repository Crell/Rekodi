<?php

declare(strict_types=1);

namespace Crell\Rekodi;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Table as DoctrineTable;


class SchemaCreator
{
    public function __construct(protected Connection $conn) {}

    public function createSchemaDefinition(string $className): DoctrineTable
    {
        $schema = $this->conn->createSchemaManager()->createSchema();

        $rClass = new \ReflectionClass($className);

        $tableDefinition = $this->getAttribute($rClass, Table::class);
        $tableName = $tableDefinition?->name ?? $this->baseClassName($className);

        $table = $schema->createTable($tableName);

        $rProperties = $rClass->getProperties();
        foreach ($rProperties as $property) {
            $fieldDefinition = $this->getAttribute($property, Field::class) ?? new Field();
            $fieldDefinition->setProperty($property);
            if ($fieldDefinition->skip) {
                continue;
            }

            $table->addColumn($fieldDefinition->field, $fieldDefinition->type, $fieldDefinition->options());
        }

        return $table;

    }

    protected function baseClassName(string $className): string
    {
        $pos = strrpos($className, '\\');

        return substr($className, $pos + 1);
    }

    protected function getAttribute(\Reflector $target, string $name): ?object
    {
        return $this->getAttributes($target, $name)[0] ?? null;
    }

    protected function getAttributes(\Reflector $target, string $name): array
    {
        return array_map(fn(\ReflectionAttribute $attrib)
            => $attrib->newInstance(), $target->getAttributes($name, \ReflectionAttribute::IS_INSTANCEOF));
    }
}
