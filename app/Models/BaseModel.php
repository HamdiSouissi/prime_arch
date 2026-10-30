<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

/**
 * Modèle de base (accès PDO). Les modèles métier (Client, Project, Invoice…) en héritent.
 *
 * TODO : find(), all(), create(), update(), delete(), where(), pagination, horodatages.
 */
abstract class BaseModel
{
    /** Nom de la table (à définir dans la classe fille). */
    protected string $table = '';

    /** Clé primaire. */
    protected string $primaryKey = 'id';

    protected function db(): PDO
    {
        return Database::connection();
    }
}
