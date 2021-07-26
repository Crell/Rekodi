<?php

declare(strict_types=1);

namespace Crell\Rekodi;

use Attribute;
use Crell\AttributeUtils\FromReflectionProperty;
use Crell\AttributeUtils\GetAttribute;

/**
 * @internal
 *
 * @todo This should probably get subclasses for the different types.
 * We're basically going to end up recreating the Annotations version from Doctrine ORM. :-/
 * cf: https://www.doctrine-project.org/projects/doctrine-dbal/en/latest/reference/schema-representation.html#portable-options
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class Field implements FromReflectionProperty
{
    use GetAttribute;

    /* readonly */ public bool $isId;

    /* readonly */ public bool $isGeneratedId;

    /**
     * The Doctrine SQL type of this field.
     *
     * @see https://www.doctrine-project.org/projects/doctrine-dbal/en/latest/reference/types.html
     */
    public string $doctrineType;

    /**
     * The native PHP type, as the reflection system defines it.
     */
    public string $phpType;

    /**
     * The name of the property in PHP. ($field is the name of the DB field.)
     */
    public string $name;

    public function __construct(
        // $field is the name of the field in the DB.
        public ?string $field = null,
        public bool $exclude = false,
        public mixed $default = null,
        public ?int $length = null,
        public ?bool $unsigned = null,
    ) {}

    public function fromReflection(\ReflectionProperty $subject): void
    {
        $this->name = $subject->name;
        $this->phpType ??= $this->getNativeType($subject);
        $this->doctrineType ??= $this->getDoctrineType($this->phpType);
        $this->field ??= $subject->name;
        $this->default ??= $subject->getDefaultValue();

        // @todo Make multiple attributes easier to handle cleaner.
        // Maybe change Id to be a sub-property of Field instead, in PHP 8.1?
        $idRef = $this->getAttribute($subject, Id::class);
        $this->isId = isset($idRef);
        $this->isGeneratedId = $this->isId && $idRef?->generate;
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

    public function decodeValueFromDb(int|float|string $value): mixed
    {
        return match ($this->phpType) {
            'int', 'float', 'string' => $value,
            'array' => json_decode($value, true, 512, \JSON_THROW_ON_ERROR),
            \DateTime::class => new \DateTime($value),
            \DateTimeImmutable::class => new \DateTimeImmutable($value),
        };
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
            // Only ever allow storing datetime with TZ data.
            \DateTime::class => 'datetimetz',
            \DateTimeImmutable::class => 'datetimetz_immutable',
            'array' => 'json',
            // @todo Need a test case for this.
            'resource' => throw ResourcePropertiesNotAllowed::create('Fix this string'),
        };
    }
}
