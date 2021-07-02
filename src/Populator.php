<?php

declare(strict_types=1);

namespace Crell\Rekodi;

class Populator
{

    /**
     *
     *
     * @param string $class
     * @param array $data
     * @return object|PopulatorErrors
     *   The instantiated object, or an instance of the PopulatorErrors enum.
     */
    public function populate(string $class, array $data): object
    {
        if (!class_exists($class)) {
            return PopulatorErrors::ClassNotFound;
        }

        /*
        $rConstructor = (new \ReflectionClass($class))->getConstructor();
        $rParams = $rConstructor->getParameters();
        if (count($data) > count($rParams)) {
            return PopulatorErrors::TooManyArguments();
        }
        */

        try {
            return new $class(...$data);
        } catch (\ArgumentCountError $e) {
            return PopulatorErrors::WrongArgumentCount;
        } catch (\Error $e) {
            return PopulatorErrors::Other;
        }

    }
}
