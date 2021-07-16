<?php

declare(strict_types=1);

namespace Crell\Rekodi;

use Attribute;

/**
 * @internal
 */
#[Attribute(Attribute::TARGET_CLASS)]
class Table
{
    /**
     * @var Field[]
     */
    public array $fields;

    public \ReflectionClass $rClass;

    public function __construct(
        public ?string $name = null,
    ) {}

    public function setReflection(\ReflectionClass $rClass): void
    {
        $this->rClass = $rClass;
    }

    /**
     * @param Field[] $fields
     */
    public function setFields(array $fields): void
    {
        foreach ($fields as $field) {
            $this->fields[$field->property->getName()] = $field;
        }
    }

    /**
     * @todo tri-state logic? Eew. Maybe need separate methods, or an enum, or something.
     * @return Field[]
     */
    public function getIdFields(?bool $generated = null): array
    {
        return match($generated) {
            true => array_values(array_filter($this->fields, static fn(Field $field): bool => $field->isId() && $field->idDef->generate)),
            false => array_values(array_filter($this->fields, static fn(Field $field): bool => $field->isId() && !$field->idDef->generate)),
            null => array_values(array_filter($this->fields, static fn(Field $field): bool => $field->isId())),
        };
    }

    /**
     * @return Field[]
     */
    public function getValueFields(): array
    {
        return array_values(array_filter($this->fields, static fn(Field $field): bool => !$field->isId()));
    }
}
