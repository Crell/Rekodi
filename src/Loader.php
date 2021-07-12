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

        foreach ($fields as $field) {
            $insert[$field->field] = $field->property->getValue($object);
        }

        $this->conn->insert($tableDefinition->name, $insert);
    }

    public function loadRecords(Result $result, string $class): iterable
    {
        $fields = $this->getFieldDefinitions(new \ReflectionClass($class));

        foreach ($result->iterateAssociative() as $record) {
            // This weirdness is the most declarative way to array_map into
            // an associative array. Maybe this should get factored out.
            // @see https://www.danielauener.com/howto-use-array_map-on-associative-arrays-to-change-values-and-keys/
            $init = array_reduce($fields, function (array $init, Field $field) use ($record) {
                $init[$field->property->name] = $record[$field->field];
                return $init;
            }, []);
            yield new $class(...$init);
        }
    }
}