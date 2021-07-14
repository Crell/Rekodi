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

    public function __construct(
        public ?string $name = null,
    ) {}

    public function setFields(array $fields): void
    {
        $this->fields = $fields;
    }

    /**
     * @return Field[]
     */
    public function getIdFields(): array
    {
        return array_filter($this->fields, static fn(Field $field): bool => $field->isId());
    }

    /**
     * @return Field[]
     */
    public function getValueFields(): array
    {
        return array_filter($this->fields, static fn(Field $field): bool => !$field->isId());
    }
}
