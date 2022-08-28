<?php

declare(strict_types=1);

namespace Crell\Rekodi;

use Attribute;
use Crell\AttributeUtils\Excludable;
use Crell\AttributeUtils\FromReflectionProperty;
use Crell\AttributeUtils\HasSubAttributes;

/**
 * @internal
 *
 * @todo This should probably get subclasses for the different types.
 * We're basically going to end up recreating the Annotations version from Doctrine ORM. :-/
 * cf: https://www.doctrine-project.org/projects/doctrine-dbal/en/latest/reference/schema-representation.html#portable-options
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class Field implements FromReflectionProperty, HasSubAttributes, Excludable
{
    /* readonly */ public bool $isId;

    /* readonly */ public bool $isGeneratedId;

    /**
     * The Doctrine SQL type of this field.
     *
     * @see https://www.doctrine-project.org/projects/doctrine-dbal/en/latest/reference/types.html
     */
    public readonly string $doctrineType;

    /**
     * The native PHP type, as the reflection system defines it.
     */
    public readonly string $phpType;

    /**
     * The name of the property in PHP. ($field is the name of the DB field.)
     */
    public readonly string $name;

    public readonly ?string $field;

    public readonly mixed $default;

    public function __construct(
        // $field is the name of the field in the DB.
        ?string $field = null,
        private readonly bool $exclude = false,
        mixed $default = null,
        public readonly ?int $length = null,
        public readonly ?bool $unsigned = null,
    ) {
        if ($field) {
            $this->field = $field;
        }
        if ($default) {
            $this->default = $default;
        }
    }

    public function exclude(): bool
    {
        return $this->exclude;
    }

    public function fromReflection(\ReflectionProperty $subject): void
    {
        $this->name = $subject->name;
        $this->phpType ??= $this->getNativeType($subject);
        $this->doctrineType ??= $this->getDoctrineType($this->phpType);
        $this->field ??= $subject->name;
        $this->default ??= $subject->getDefaultValue();
    }

    public function subAttributes(): array
    {
        return [Id::class => 'fromId'];
    }

    public function fromId(?Id $id = null): void
    {
        $this->isId = isset($id);
        $this->isGeneratedId = $id?->generate ?? false;
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
        if ($this->isGeneratedId) {
            $ret['autoincrement'] = true;
        }
        return $ret;
    }

    protected function getNativeType(\ReflectionProperty $property): string
    {
        $rType = $property->getType();
        return match(true) {
            $rType instanceof \ReflectionUnionType => throw UnionTypesNotSupported::create($property),
            $rType instanceof \ReflectionIntersectionType => throw IntersectionTypesNotSupported::create($property),
            $rType instanceof \ReflectionNamedType => $rType->getName(),
        };
    }

    protected function getDoctrineType(string $phpType): string
    {
        return match ($phpType) {
            'int' => 'integer',
            'string' => 'string',
            'float' => 'float',
            // Only ever allow storing datetime with TZ data.
            \DateTime::class => 'datetimetz',
            \DateTimeImmutable::class => 'datetimetz_immutable',
            'array' => 'json',
            // @todo Need a test case for this.
            'resource' => throw ResourcePropertiesNotAllowed::create('Fix this string'),
            default => 'json',
        };
    }
}
