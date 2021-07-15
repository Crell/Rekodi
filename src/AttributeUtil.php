<?php

declare(strict_types=1);

namespace Crell\Rekodi;

trait AttributeUtil
{
    /**
     * @var Table[string]
     *
     * Lookup cache.
     */
    protected array $tableDefinitions = [];

    protected function tableDefinition(string $class): Table
    {
        return $this->tableDefinitions[$class] ??= $this->createTableDefinition($class);
    }

    protected function createTableDefinition(string $class): Table
    {
        $rClass = new \ReflectionClass($class);
        $tableDef = $this->getAttribute($rClass, Table::class) ?? new Table(name: $this->baseClassName($class));
        if (is_null($tableDef->name)) {
            $tableDef->name = $this->baseClassName($class);
        }
        $tableDef->setReflection($rClass);

        $fields = $this->getFieldDefinitions($rClass);
        $tableDef->setFields($fields);

        return $tableDef;
    }


    protected function getPropertyDefinition(\ReflectionProperty $property): Field
    {
        $fieldDefinition = $this->getAttribute($property, Field::class) ?? new Field();
        $fieldDefinition->setProperty($property);

        /** @var Id $id */
        $id = $this->getAttribute($property, Id::class);
        if ($id) {
            $fieldDefinition->setId($id);
        }

        return $fieldDefinition;
    }

    /**
     * @return Field[]
     */
    protected function getFieldDefinitions(\ReflectionObject|\ReflectionClass $subject): array
    {
        $rProperties = $subject->getProperties();
        // @todo Convert to first-class-callables when those are merged.
        $fields = array_map([$this, 'getPropertyDefinition'], $rProperties);
        $fields = array_filter($fields, fn(Field $f): bool => !$f->skip);
        // @todo Turn this from an array into a FieldList object or similar.
        return $fields;
    }

    protected function baseClassName(string $className): string
    {
        return substr($className, strrpos($className, '\\') + 1);
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
