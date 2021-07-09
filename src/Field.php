<?php

declare(strict_types=1);

namespace Crell\Rekodi;

use \Attribute;

/**
 * @internal
 *
 * @todo This should get subclasses for the different types.
 * We're basically going to end up recreating the Annotations version from Doctrine ORM. :-/
 * cf: https://www.doctrine-project.org/projects/doctrine-dbal/en/latest/reference/schema-representation.html#portable-options
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class Field
{
    public \ReflectionProperty $property;

    public function __construct(
        public ?string $field = null,
        public bool $skip = false,
        public mixed $default = null,
        public ?int $length = null,
        public ?bool $unsigned = null,
    ) {}

    public function setProperty(\ReflectionProperty $property): void
    {
        $this->property = $property;

        if ($this->skip) {
            return;
        }

        $this->field ??= $property->name;
        $this->type ??= $this->mapType($property);
        $this->default ??= $property->getDefaultValue();
    }

    /**
     * Maps to the Doctrine Schema addField(options) array. Check the docs there.
     */
    public function options(): array
    {
        $ret = [];
        foreach (['default', 'length', 'unsigned'] as $key) {
            if ($this->$key) {
                $ret[$key] = $this->$key;
            }
        }
        return $ret;
    }

    protected function mapType(\ReflectionProperty $property): string
    {
        /** @var \ReflectionType $rType */
        $rType = $property->getType();
        // @todo Some union types we may be able to support, eg int|float.
        if ($rType instanceof \ReflectionUnionType) {
            throw UnionTypesNotSupported::create($property);
        }
        if ($rType instanceof \ReflectionIntersectionType) {
            throw IntersectionTypesNotSupported::create($property);
        }

        return match ($rType->getName()) {
            'int' => 'integer',
            'string' => 'varchar',
        };
    }
}
