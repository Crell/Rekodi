<?php

declare(strict_types=1);

namespace Crell\Rekodi;

/**
 * @todo Convert this to an enum.
 */
class PopulatorErrors
{
    public static array $errors = [];

    public function __construct(public string $message) {}

    public static function ClassNotFound(): static
    {
        return static::$errors[__FUNCTION__] ??= new static('Class not found');
    }
    public static function TooManyArguments(): static
    {
        return static::$errors[__FUNCTION__] ??= new static('Too many arguments');
    }
    public static function WrongArgumentCount(): static
    {
        return static::$errors[__FUNCTION__] ??= new static('Wrong argument count');
    }
    public static function Other(): static
    {
        return static::$errors[__FUNCTION__] ??= new static('Other error');
    }
}
