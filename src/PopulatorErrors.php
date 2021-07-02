<?php

declare(strict_types=1);

namespace Crell\Rekodi;

enum PopulatorErrors
{
    case ClassNotFound;
    case WrongArgumentCount;
    case Other;
}
