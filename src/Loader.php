<?php

declare(strict_types=1);

namespace Crell\Rekodi;

use Crell\AttributeUtils\ClassAnalyzer;
use Crell\Serde\Serde;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Result;

class Loader
{
    public function __construct(
        protected Connection $conn,
        protected ClassAnalyzer $analyzer,
        protected Serde $serde,
    ) {}

    /**
     * @todo This should return something useful. Not sure what yet.
     *
     * @todo This relies on Doctrine calling json_encode() on any object
     * fields. That nominally works, but we need to pre-serialize with Serde
     * ourselves instead so that it is symmetric with the load process.
     */
    public function save(object $object): void
    {
        $tableDef = $this->analyzer->analyze($object, Table::class);

        // We need the key field list separate from the non-key-field, so build them separately.
        $keyFields = $this->buildFieldValueMap($tableDef->getIdFields(), $object);
        $insert = $this->buildFieldValueMap($tableDef->getValueFields(), $object);

        $insertTypes = array_map(static fn(Field $f): string => $f->doctrineType, $tableDef->getValueFields());
        $keyFieldTypes = array_map(static fn(Field $f): string => $f->doctrineType, $tableDef->getIdFields());

        // There is no good cross-DB way to do this, so we do it the ugly way.
        // Replace with a less ugly way if possible.
        $this->conn->transactional(function(Connection $conn) use ($tableDef, $keyFields, $keyFieldTypes, $insert, $insertTypes, $object) {
            // If there's no key fields defined for this object, we can't do an existing lookup.
            // It can only be an insert.
            if ($keyFields && $this->recordExists($tableDef->name, $keyFields)) {
                $conn->update($tableDef->name, $insert, $keyFields, $insertTypes);
            } else {
                $insert += $this->buildFieldValueMap($tableDef->getIdFields(generated: false), $object);
                $insertTypes = [...$insertTypes, ...$keyFieldTypes];
                $conn->insert($tableDef->name, $insert, $insertTypes);
            }
        });
    }

    /**
     * Load a single record from the database.
     *
     * Only works for tables that have at least one ID field.
     *
     * @param string $type
     *   The class name to load.
     * @param int|float|string ...$id
     *   This variadic parameter allows the passing of one or more key fields.
     *   Most records will have a single primary key field, in which case just pass
     *   a single argument on its own, like load(MyClass::class, 5);
     *   If a record has multiple key fields, you can pass each key field variadically
     *   by name, like so: load(MyClass::class, key1: 5, key2: 6);
     *   To build a multi-key list dynamically, splat an array: load(MyClass::class, ...$keys);
     *
     * @return ?object
     *   The loaded object, or null if not found.
     */
    public function load(string $type, int|float|string ...$id): ?object
    {
        $tableDef = $this->analyzer->analyze($type, Table::class);

        $keyFields = $tableDef->getIdFields();
        $valueFields = $tableDef->getValueFields();

        $ids = $this->normalizeIds($id, $type, $keyFields);

        $qb = $this->conn->createQueryBuilder();
        // Select all fields
        $selectFields = array_map(static fn(Field $f) => $f->field, [...$keyFields, ...$valueFields]);
        $qb->select(...$selectFields);
        // From the table
        $qb->from($tableDef->name);
        // Where matching on the ID fields.
        $ands = array_map(static fn($k, $v) => $qb->expr()->eq($k, $qb->createNamedParameter($v)), array_keys($ids), array_values($ids));
        $qb->where($qb->expr()->and(...$ands));

        $result = $qb->executeQuery();
        // It would be really nice to replace this with an enum error value instead.
        // Aka, a proper monad.
        return iterator_to_array($this->loadRecords($type, $result))[0] ?? null;
    }

    /**
     * Delete a single record from the database.
     *
     * Only works for tables that have at least one ID field.
     *
     * @todo This should probably return something meaningful.
     *
     * @param string $type
     *   The class name to load.
     * @param int|float|string ...$id
     *   This variadic parameter allows the passing of one or more key fields.
     *   Most records will have a single primary key field, in which case just pass
     *   a single argument on its own, like load(MyClass::class, 5);
     *   If a record has multiple key fields, you can pass each key field variadically
     *   by name, like so: load(MyClass::class, key1: 5, key2: 6);
     *   To build a multi-key list dynamically, splat an array: load(MyClass::class, ...$keys);
     */
    public function delete(string $type, int|float|string ...$id): void
    {
        $tableDef = $this->analyzer->analyze($type, Table::class);

        $ids = $this->normalizeIds($id, $type, $tableDef->getIdFields());

        $qb = $this->conn->createQueryBuilder();

        $qb->delete($tableDef->name);
        // Where matching on the ID fields.
        $ands = array_map(static fn($k, $v) => $qb->expr()->eq($k, $qb->createNamedParameter($v)), array_keys($ids), array_values($ids));
        $qb->where($qb->expr()->and(...$ands));

        $qb->executeQuery();
    }

    public function loadRecords(string $class, Result $result): iterable
    {
        $tableDef = $this->analyzer->analyze($class, Table::class);
        $fields = $tableDef->fields;

        $decoder = $this->decodeValueFromDb(...);

        // Bust into the object to set properties, regardless of their
        // visibility or readonly status.
        $populate = function($init) use ($fields, $decoder) {
            foreach ($init as $k => $v) {
                $this->$k = $decoder($v, $fields[$k]);
            }
            return $this;
        };

        foreach ($result->iterateAssociative() as $record) {
            // This weirdness is the most declarative way to array_map into
            // an associative array. Maybe this should get factored out.
            // @see https://www.danielauener.com/howto-use-array_map-on-associative-arrays-to-change-values-and-keys/
            $init = array_reduce($fields, static function (array $init, Field $field) use ($record) {
                $init[$field->name] = $record[$field->field];
                return $init;
            }, []);

            $new = (new \ReflectionClass($tableDef->className))->newInstanceWithoutConstructor();
            yield $populate->bindTo($new, $new)($init);
        }
    }

    private function decodeValueFromDb(int|float|string $value, Field $field): mixed
    {
        return match ($field->doctrineType) {
            'integer', 'float', 'string' => $value,
            'array' => json_decode($value, true, 512, \JSON_THROW_ON_ERROR),
            'datetimetz' => new \DateTime($value),
            'datetimetz_immutable' => new \DateTimeImmutable($value),
            'object', 'json' =>
                class_exists($field->phpType) || interface_exists($field->phpType)
                ? $this->serde->deserialize($value, from: 'json', to: $field->phpType)
                : json_decode($value, true, 512, \JSON_THROW_ON_ERROR),
        };
    }

    /**
     * @param Field[] $fields
     *
     * @todo This method is probably wrong. Refactor once we grok Doctrine's type handling better.
     */
    protected function buildFieldValueMap(array $fields, object $object): array
    {
        // @todo I'm not sure how to make this nicely functional, since $rProp would
        // be needed in both the map and the filter portion.
        $rObject = new \ReflectionObject($object);

        $ret = [];
        foreach ($fields as $field) {
            $rProp = $rObject->getProperty($field->name);
            if (! $rProp->isInitialized($object)) {
                continue;
            }
            $value = $rProp->getValue($object);
            $ret[$field->field] = $value;
        }
        return $ret;
    }

    protected function recordExists(string $table, array $where): bool
    {
        $qb = $this->conn->createQueryBuilder();
        $ands = array_map(static fn($k, $v) => $qb->expr()->eq($k, $v), array_keys($where), array_values($where));
        $qb->addSelect('1')->from($table);
        if ($ands) {
            $qb->where($qb->expr()->and(...$ands));
        }
        $result = $qb->executeQuery();

        return (bool)$result->fetchFirstColumn();
    }


    protected function normalizeIds(array $id, string $type, array $keyFields): array
    {
        return match (true) {
            count($id) !== count($keyFields) => throw IdFieldCountMismatch::create($type, count($keyFields), count($id)),
            count($id) > 1 && $this->array_is_list($id) => throw MultiKeyIdHasNumericKeys::create($type, $id),
            count($id) === 1 && $this->array_is_list($id) => [$keyFields[0]->field => $id[0]],
            count($id) > 1 => $id,
        };
    }

    // @todo Polyfill. This function is in PHP 8.1. Remove when updating.
    private function array_is_list(array $array): bool {
        $expectedKey = 0;
        foreach ($array as $i => $_) {
            if ($i !== $expectedKey) { return false; }
            $expectedKey++;
        }
        return true;
    }
}
