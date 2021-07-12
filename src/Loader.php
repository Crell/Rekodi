<?php

declare(strict_types=1);

namespace Crell\Rekodi;

use Doctrine\DBAL\Connection;

class Loader
{
    use AttributeUtil;

    public function __construct(
        protected Connection $conn,
    ) {}

    public function save(object $object): void
    {
        $rObject = new \ReflectionObject($object);

        $tableDefinition = $this->getAttribute($rObject, Table::class) ?? new Table(name: $this->baseClassName($object::class));

        $fields = $this->getFieldDefinitions($rObject);

        //$fieldNames = array_map([$this, 'getFieldName'], $fields);

        foreach ($fields as $field) {
            $insert[$field->field] = $field->property->getValue($object);
        }

        $this->conn->insert($tableDefinition->name, $insert);

        /*
        $qb = $this->conn->createQueryBuilder();
        $qb->addSelect(...$fields);
        $qb->from($tableDefinition->name);
        */
    }

    protected function getFieldName(Field $field): string
    {
        return $field->field;
    }
}
