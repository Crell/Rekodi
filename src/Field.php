<?php

declare(strict_types=1);

namespace Crell\Rekodi;

use Attribute;

/**
 * @internal
 *
 * @todo This should probably get subclasses for the different types.
 * We're basically going to end up recreating the Annotations version from Doctrine ORM. :-/
 * cf: https://www.doctrine-project.org/projects/doctrine-dbal/en/latest/reference/schema-representation.html#portable-options
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class Field
{
    public \ReflectionProperty $property;

    // Nullable so that nullsafe property calls work.
    public ?Id $idDef = null;

    /**
     * The Doctrine SQL type of this field.
     *
     * @see https://www.doctrine-project.org/projects/doctrine-dbal/en/latest/reference/types.html
     */
    public string $type;

    public function __construct(
        public ?string $field = null,
        public bool $skip = false,
        public mixed $default = null,
        public ?int $length = null,
        public ?bool $unsigned = null,
    ) {}

    // I don't love this function; it's not very functional. But I'm not sure
    // what the better alternative is other than just returning a new object.
    public function setProperty(\ReflectionProperty $property): void
    {
        $this->property = $property;

        if ($this->skip) {
            return;
        }

        $this->field ??= $property->name;
        $this->type ??= $this->getDoctrineType();
        $this->default ??= $property->getDefaultValue();
    }

    public function setId(Id $idDef): void
    {
        $this->idDef = $idDef;
    }

    public function isId(): bool
    {
        return isset($this->idDef);
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
        if ($this->idDef?->generate) {
            $ret['autoincrement'] = true;
        }
        return $ret;
    }

    public function decodeValueFromDb(int|float|string $value): mixed
    {
        return match ($this->getNativeType()) {
            'int', 'float', 'string' => $value,
            \DateTime::class => new \DateTime($value),
            \DateTimeImmutable::class => new \DateTimeImmutable($value),
        };
    }

    protected function getNativeType(): string
    {
        /** @var \ReflectionType $rType */
        $rType = $this->property->getType();
        return match(true) {
            $rType instanceof \ReflectionUnionType => throw UnionTypesNotSupported::create($this->property),
            $rType instanceof \ReflectionIntersectionType => throw IntersectionTypesNotSupported::create($this->property),
            $rType instanceof \ReflectionNamedType => $rType->getName(),
        };
    }

    protected function getDoctrineType(): string
    {
        return match ($this->getNativeType()) {
            'int' => 'integer',
            'string' => 'string',
            // Only ever allow storing datetime with TZ data.
            \DateTime::class => 'datetimetz',
            \DateTimeImmutable::class => 'datetimetz_immutable',
        };
    }
}
