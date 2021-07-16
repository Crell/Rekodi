<?php

declare(strict_types=1);

namespace Crell\Rekodi;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Result;

class Loader
{
    use AttributeUtil;

    public function __construct(
        protected Connection $conn,
    ) {}

    /**
     * @todo This should return something useful. Not sure what yet.
     */
    public function save(object $object): void
    {
        $tableDef = $this->tableDefinition($object::class);

        // We need the key field list separate from the non-key-field, so build them separately.
        $keyFields = $this->fieldValueMap($tableDef->getIdFields(), $object);
        $insert = $this->fieldValueMap($tableDef->getValueFields(), $object);

        $insertTypes = array_map(static fn(Field $f): string => $f->type, $tableDef->getValueFields());
        $keyFieldTypes = array_map(static fn(Field $f): string => $f->type, $tableDef->getIdFields());

        // There is no good cross-DB way to do this, so we do it the ugly way.
        // Replace with a less ugly way if possible.
        $this->conn->transactional(function(Connection $conn) use ($tableDef, $keyFields, $keyFieldTypes, $insert, $insertTypes, $object) {
            // If there's no key fields defined for this object, we can't do an existing lookup.
            // It can only be an insert.
            if ($keyFields && $this->recordExists($tableDef->name, $keyFields)) {
                $conn->update($tableDef->name, $insert, $keyFields, $insertTypes);
            } else {
                $insert += $this->fieldValueMap($tableDef->getIdFields(generated: false), $object);
                $insertTypes += [...$insertTypes, ...$keyFieldTypes];
                $conn->insert($tableDef->name, $insert, $insertTypes);
            }
        });
    }

    /**
     * @param Field[] $fields
     */
    protected function fieldValueMap(array $fields, object $object): array
    {
        // This would be much cleaner with PFA and pipes. :-(

        $validFields = array_filter($fields, fn(Field $f) => $f->property->isInitialized($object));
        $callback = fn(Field $f) => $this->getFieldValue($f, $object);
        $ret = [];
        foreach ($validFields as $field) {
            $ret[$field->field] = $callback($field);
        }
        return $ret;
    }

    /**
     * This method is probably wrong. Refactor once we grok Doctrine's type handling better.
     */
    protected function getFieldValue(Field $field, object $object): mixed
    {
        $value = $field->property->getValue($object);
        if (in_array(gettype($value), ['int', 'float', 'string'])) {
            return $value;
        }


        // Other stuff will likely fail, but add those as we go.
        return $value;
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
        $tableDef = $this->tableDefinition($type);

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

    protected function normalizeIds(array $id, string $type, array $keyFields): array
    {
        return match (true) {
            count($id) !== count($keyFields) => throw IdFieldCountMismatch::create($type, count($keyFields), count($id)),
            count($id) > 1 && $this->array_is_list($id) => throw MultiKeyIdHasNumericKeys::create($type, $id),
            count($id) === 1 && $this->array_is_list($id) => [$keyFields[0]->field => $id[0]],
            count($id) > 1 => $id,
        };
    }

    public function loadRecords(string $class, Result $result): iterable
    {
        $tableDef = $this->tableDefinition($class);
        $fields = $tableDef->fields;

        // Bust into the object to set properties, regardless of their
        // visibility or readonly status.
        $populate = function($init) use ($fields) {
            foreach ($init as $k => $v) {
                $this->$k = $fields[$k]->decodeValueFromDb($v);
            }
            return $this;
        };

        foreach ($result->iterateAssociative() as $record) {
            // This weirdness is the most declarative way to array_map into
            // an associative array. Maybe this should get factored out.
            // @see https://www.danielauener.com/howto-use-array_map-on-associative-arrays-to-change-values-and-keys/
            $init = array_reduce($fields, static function (array $init, Field $field) use ($record) {
                $init[$field->property->name] = $record[$field->field];
                return $init;
            }, []);

            $new = $tableDef->rClass->newInstanceWithoutConstructor();
            yield $populate->bindTo($new, $new)($init);
        }
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
        $tableDef = $this->tableDefinition($type);

        $ids = $this->normalizeIds($id, $type, $tableDef->getIdFields());

        $qb = $this->conn->createQueryBuilder();

        $qb->delete($tableDef->name);
        // Where matching on the ID fields.
        $ands = array_map(static fn($k, $v) => $qb->expr()->eq($k, $qb->createNamedParameter($v)), array_keys($ids), array_values($ids));
        $qb->where($qb->expr()->and(...$ands));

        $qb->executeQuery();
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
