<?php

declare(strict_types=1);

namespace Crell\Rekodi;

use Attribute;
use Crell\AttributeUtils\FromReflectionClass;
use Crell\AttributeUtils\ParseProperties;

/**
 * @internal
 */
#[Attribute(Attribute::TARGET_CLASS)]
class Table implements FromReflectionClass, ParseProperties
{
    /**
     * @var Field[]
     */
    /* readonly */ public array $fields;

    /* readonly */ public string $className;

    public function __construct(
        public ?string $name = null,
    ) {}

    public function fromReflection(\ReflectionClass $subject): void
    {
        $this->name ??= $subject->getShortName();
        $this->className ??= $subject->getName();
    }

    public function setProperties(array $properties): void
    {
        $this->fields = $properties;
    }

    public function includeByDefault(): bool
    {
        return true;
    }

    public static function propertyAttribute(): string
    {
        return Field::class;
    }

    /**
     * @todo tri-state logic? Eew. Maybe need separate methods, or an enum, or something.
     * @return Field[]
     */
    public function getIdFields(?bool $generated = null): array
    {
        return match($generated) {
            true => array_values(array_filter($this->fields, static fn(Field $field): bool => $field->isId && $field->isGeneratedId)),
            false => array_values(array_filter($this->fields, static fn(Field $field): bool => $field->isId && !$field->isGeneratedId)),
            null => array_values(array_filter($this->fields, static fn(Field $field): bool => $field->isId)),
        };
    }

    /**
     * @return Field[]
     */
    public function getValueFields(): array
    {
        return array_values(array_filter($this->fields, static fn(Field $field): bool => !$field->isId));
    }
}
