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
        $rObject = new \ReflectionObject($object);

        $tableDefinition = $this->getAttribute($rObject, Table::class) ?? new Table(name: $this->baseClassName($object::class));

        $fields = $this->getFieldDefinitions($rObject);
        $tableDefinition->setFields($fields);

        // We need the key field list separate from the non-key-field, so build them separately.
        $keyFields = [];
        foreach ($tableDefinition->getIdFields() as $field) {
            // PHPStorms stubs are out of date again.
            $keyFields[$field->field] = $field->property->isInitialized($object) ? $field->property->getValue($object) : null;
        }
        $keyFields = array_filter($keyFields);

        $insert = [];
        foreach ($tableDefinition->getValueFields() as $field) {
            $insert[$field->field] = $field->property->isInitialized($object) ? $field->property->getValue($object) : null;
        }
        $insert = array_filter($insert);

        // There is no good cross-DB way to do this, so we do it the ugly way.
        // Replace with a less ugly way if possible.
        $this->conn->transactional(function(Connection $conn) use ($tableDefinition, $keyFields, $insert) {
            // If there's no key fields defined for this object, we can't do an existing lookup.
            // It can only be an insert.
            if ($keyFields && $this->recordExists($tableDefinition->name, $keyFields)) {
                $conn->update($tableDefinition->name, $insert, $keyFields);
            } else {
                $conn->insert($tableDefinition->name, $insert);
            }
        });
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
    public function load(string $type, int|float|string|array $id): object
    {
        $rClass = new \ReflectionClass($type);
        $tableDef = $this->getAttribute($rClass, Table::class) ?? new Table(name: $this->baseClassName($type));
        $tableDef->setFields($this->getFieldDefinitions($rClass));

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
        $ands = array_map(fn($k, $v) => $qb->expr()->eq($k, $v), array_keys($id), array_values($id));
        $qb->where($qb->expr()->and(...$ands));

        $result = $qb->executeQuery();
        return iterator_to_array($this->loadRecords($result, $type))[0];
    }

    public function loadRecords(Result $result, string $class): iterable
    {
        $rClass = new \ReflectionClass($class);
        $fields = $this->getFieldDefinitions($rClass);

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

            $new = $rClass->newInstanceWithoutConstructor();
            yield $populate->bindTo($new, $new)($init);
        }
    }
}
