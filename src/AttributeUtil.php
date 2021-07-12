<?php

declare(strict_types=1);

namespace Crell\Rekodi;

trait AttributeUtil
{
    protected function getPropertyDefinition(\ReflectionProperty $property): Field
    {
        $fieldDefinition = $this->getAttribute($property, Field::class) ?? new Field();
        $fieldDefinition->setProperty($property);
        return $fieldDefinition;
    }

    /**
     *
     *
     * @param \ReflectionObject|\ReflectionClass $subject
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
