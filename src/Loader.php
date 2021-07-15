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

        // There is no good cross-DB way to do this, so we do it the ugly way.
        // Replace with a less ugly way if possible.
        $this->conn->transactional(function(Connection $conn) use ($tableDef, $keyFields, $insert, $object) {
            // If there's no key fields defined for this object, we can't do an existing lookup.
            // It can only be an insert.
            if ($keyFields && $this->recordExists($tableDef->name, $keyFields)) {
                $conn->update($tableDef->name, $insert, $keyFields);
            } else {
                $insert += $this->fieldValueMap($tableDef->getIdFields(generated: false), $object);
                $conn->insert($tableDef->name, $insert);
            }
        });
    }

    /**
     * @param Field[] $fields
     */
    protected function fieldValueMap(array $fields, object $object): array
    {
        $ret = [];
        foreach ($fields as $field) {
            $ret[$field->field] = $field->property->isInitialized($object) ? $field->property->getValue($object) : null;
        }
        return array_filter($ret);
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
     * Only works for tables that have at least one ID field.
     */
    public function load(string $type, int|float|string|array $id): ?object
    {
        $tableDef = $this->tableDefinition($type);

        $keyFields = $tableDef->getIdFields();
        $valueFields = $tableDef->getValueFields();

        // Normalize data.
        // This works iff there is only one key field, which is the typical case.
        // Better error checking is probably useful.
        if (!is_array($id)) {
            $id = [$keyFields[0]->field => $id];
        }

        if (count($keyFields) !== count($id)) {
            throw new IdFieldCountMismatch($type, count($keyFields), count($id));
        }

        $qb = $this->conn->createQueryBuilder();
        // Select all fields
        $selectFields = array_map(static fn(Field $f) => $f->field, [...$keyFields, ...$valueFields]);
        $qb->select(...$selectFields);
        // From the table
        $qb->from($tableDef->name);
        // Where matching on the ID fields.
        $ands = array_map(static fn($k, $v) => $qb->expr()->eq($k, $qb->createNamedParameter($v)), array_keys($id), array_values($id));
        $qb->where($qb->expr()->and(...$ands));

        $result = $qb->executeQuery();
        // It would be really nice to replace this with an enum error value instead.
        // Aka, a proper monad.
        return iterator_to_array($this->loadRecords($result, $type))[0] ?? null;
    }

    public function loadRecords(Result $result, string $class): iterable
    {
        $tableDef = $this->tableDefinition($class);
        $fields = $tableDef->fields;

        // Bust into the object to set properties, regardless of their
        // visibility or readonly status.
        $populate = function($init) {
            foreach ($init as $k => $v) {
                $this->$k = $v;
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

    // @todo This should probably return something meaningful.
    public function delete(string $type, int|float|string|array $id): void
    {
        $tableDef = $this->tableDefinition($type);

        $keyFields = $tableDef->getIdFields();

        // Normalize data.
        // This works iff there is only one key field, which is the typical case.
        // Better error checking is probably useful.
        if (!is_array($id)) {
            $id = [$keyFields[0]->field => $id];
        }

        $qb = $this->conn->createQueryBuilder();

        $qb->delete($tableDef->name);
        // Where matching on the ID fields.
        $ands = array_map(static fn($k, $v) => $qb->expr()->eq($k, $qb->createNamedParameter($v)), array_keys($id), array_values($id));
        $qb->where($qb->expr()->and(...$ands));

        $qb->executeQuery();
    }
}
