<?php

declare(strict_types=1);

namespace Crell\Rekodi;

trait AttributeUtil
{
    protected function getAttribute(\Reflector $target, string $name): ?object
    {
        return $this->getAttributes($target, $name)[0] ?? null;
    }

    protected function getAttributes(\Reflector $target, string $name): array
    {
        return array_map(fn(\ReflectionAttribute $attrib)
        => $attrib->newInstance(), $target->getAttributes($name, \ReflectionAttribute::IS_INSTANCEOF));
    }
}
