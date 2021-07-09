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

        $tableDefinition = $this->getAttribute($rClass, Table::class) ?? new Table(name: $this->baseClassName($className));

        $table = $schema->createTable($tableDefinition?->name);

        $rProperties = $rClass->getProperties();
        // @todo Convert to first-class-callables when those are merged.
        $definitions = array_map([$this, 'getDefinition'], $rProperties);
        $definitions = array_filter($definitions, fn(Field $f): bool => !$f->skip);
        array_map(fn(Field $f) => $table->addColumn($f->field, $f->type, $f->options()), $definitions);

        return $table;
    }

    protected function getDefinition(\ReflectionProperty $property): Field
    {
        $fieldDefinition = $this->getAttribute($property, Field::class) ?? new Field();
        $fieldDefinition->setProperty($property);
        return $fieldDefinition;
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
