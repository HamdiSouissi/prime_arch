<?php
declare(strict_types=1);

namespace App\Services;

use RuntimeException;

/** Erreur de module dont le message peut être affiché à l'administrateur. */
final class ModuleException extends RuntimeException
{
}
