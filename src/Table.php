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

    public function setFields(array $fields): void
    {
        $this->fields = $fields;
    }

    /**
     * @todo tri-state logic? Eew. Maybe need separate methods, or an enum, or something.
     * @return Field[]
     */
    public function getIdFields(?bool $generated = null): array
    {
        return match($generated) {
            true => array_filter($this->fields, static fn(Field $field): bool => $field->isId() && $field->idDef->generate),
            false => array_filter($this->fields, static fn(Field $field): bool => $field->isId() && !$field->idDef->generate),
            null => array_filter($this->fields, static fn(Field $field): bool => $field->isId()),
        };
    }

    /**
     * @return Field[]
     */
    public function getValueFields(): array
    {
        return array_filter($this->fields, static fn(Field $field): bool => !$field->isId());
    }
}
