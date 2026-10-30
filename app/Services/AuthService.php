<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;
use App\Models\UserRepository;
use InvalidArgumentException;
use PDO;
use RuntimeException;

/**
 * Authentification par mot de passe, avec limitation des essais par courriel et IP.
 */
final class AuthService
{
    private const MAX_EMAIL_FAILURES = 5;
    private const MAX_IP_FAILURES = 30;
    private const WINDOW_MINUTES = 15;
    private const DUMMY_PASSWORD_HASH = '$argon2id$v=19$m=65536,t=3,p=1$HcwEny8I1gew9swKyB/xKw$bYSfhw+LrwZ5cem6QJddxCO4PpCmJ4q8UAMl2/Rdvlk';

    private PDO $pdo;
    private UserRepository $users;

    public function __construct(?PDO $pdo = null, ?UserRepository $users = null)
    {
        $this->pdo = $pdo ?? Database::connection();
        $this->users = $users ?? new UserRepository($this->pdo);
    }

    /**
     * Retourne success, invalid ou throttled sans révéler l'existence d'un compte.
     */
    public function authenticate(string $email, string $password, string $ipAddress): string
    {
        $email = mb_strtolower(trim($email), 'UTF-8');
        $pepper = (string) config('app.key', '');
        $isProduction = strtolower((string) config('app.env', 'production')) === 'production';
        if ($pepper === '' && $isProduction) {
            throw new RuntimeException('APP_KEY doit être défini en production pour protéger les empreintes des tentatives.');
        }
        if ($pepper === '') {
            $pepper = 'prime-arch-development-only';
        } elseif ($isProduction && strlen($pepper) < 32) {
            throw new RuntimeException('APP_KEY doit contenir au moins 32 octets en production.');
        }
        $emailFingerprint = hash_hmac('sha256', "email\0" . $email, $pepper);
        $ipFingerprint = hash_hmac('sha256', "ip\0" . $ipAddress, $pepper);

        $this->pdo->exec('DELETE FROM auth_attempts WHERE attempted_at < DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 1 DAY)');
        $this->pdo->exec(
            'DELETE FROM auth_throttle_markers WHERE attempted_at < DATE_SUB(CURRENT_TIMESTAMP, INTERVAL '
            . self::WINDOW_MINUTES . ' MINUTE)'
        );
        $count = $this->pdo->prepare(
            'SELECT fingerprint, COUNT(*) AS attempts FROM auth_attempts
             WHERE fingerprint IN (:email_fingerprint, :ip_fingerprint)
               AND attempted_at >= DATE_SUB(CURRENT_TIMESTAMP, INTERVAL ' . self::WINDOW_MINUTES . ' MINUTE)
             GROUP BY fingerprint'
        );
        $count->execute([
            'email_fingerprint' => $emailFingerprint,
            'ip_fingerprint' => $ipFingerprint,
        ]);
        $attemptCounts = [];
        foreach ($count->fetchAll() as $row) {
            $attemptCounts[(string) $row['fingerprint']] = (int) $row['attempts'];
        }
        $emailAttempts = $attemptCounts[$emailFingerprint] ?? 0;
        $ipAttempts = $attemptCounts[$ipFingerprint] ?? 0;
        if ($emailAttempts >= self::MAX_EMAIL_FAILURES || $ipAttempts >= self::MAX_IP_FAILURES) {
            $blockedFingerprints = [];
            if ($emailAttempts >= self::MAX_EMAIL_FAILURES) {
                $blockedFingerprints[] = $emailFingerprint;
            }
            if ($ipAttempts >= self::MAX_IP_FAILURES) {
                $blockedFingerprints[] = $ipFingerprint;
            }
            // La clé unique supprime les doublons, y compris lorsque plusieurs requêtes se bloquent ensemble.
            $mark = $this->pdo->prepare(
                'INSERT IGNORE INTO auth_throttle_markers (fingerprint) VALUES (:fingerprint)'
            );
            $newThrottleMarker = false;
            foreach ($blockedFingerprints as $fingerprint) {
                $mark->execute(['fingerprint' => $fingerprint]);
                $newThrottleMarker = $newThrottleMarker || $mark->rowCount() === 1;
            }
            if ($newThrottleMarker) {
                (new AuditLogger($this->pdo))->record(
                    'auth.login.throttled',
                    'authentication',
                    null,
                    ['result' => 'throttled']
                );
            }
            return 'throttled';
        }

        $user = $this->users->findForAuthentication($email);
        $hash = is_array($user) ? (string) ($user['password_hash'] ?? '') : self::DUMMY_PASSWORD_HASH;
        $passwordValid = password_verify($password, $hash);
        if ($user === null || !$passwordValid || empty($user['is_active'])) {
            $insert = $this->pdo->prepare('INSERT INTO auth_attempts (fingerprint) VALUES (:fingerprint)');
            $insert->execute(['fingerprint' => $emailFingerprint]);
            $insert->execute(['fingerprint' => $ipFingerprint]);
            (new AuditLogger($this->pdo))->record('auth.login.failed', 'authentication', null, ['result' => 'invalid']);
            return 'invalid';
        }

        $passwordHash = (string) $user['password_hash'];
        if (password_needs_rehash($passwordHash, self::algorithm(), self::options())) {
            $this->users->updatePasswordHash((int) $user['id'], self::hashPassword($password));
        }

        $this->users->updateLastLogin((int) $user['id']);
        $delete = $this->pdo->prepare('DELETE FROM auth_attempts WHERE fingerprint = :fingerprint');
        $delete->execute(['fingerprint' => $emailFingerprint]);
        Auth::login((int) $user['id']);
        (new AuditLogger($this->pdo))->record(
            'auth.login.succeeded',
            'user',
            (int) $user['id'],
            [],
            (int) $user['id']
        );
        return 'success';
    }

    /** Crée une empreinte Argon2id après contrôle de la disponibilité de l'algorithme. */
    public static function hashPassword(string $password): string
    {
        if (mb_strlen($password, 'UTF-8') < 12 || strlen($password) > 1024) {
            throw new InvalidArgumentException('Le mot de passe doit contenir au moins 12 caractères et au plus 1 024 octets.');
        }
        if (!in_array('argon2id', password_algos(), true)) {
            throw new RuntimeException('Argon2id n’est pas disponible sur ce serveur PHP.');
        }

        $hash = password_hash($password, self::algorithm(), self::options());
        if (!is_string($hash)) {
            throw new RuntimeException('Le hachage du mot de passe a échoué.');
        }
        return $hash;
    }

    private static function algorithm(): string|int
    {
        return PASSWORD_ARGON2ID;
    }

    /** @return array{memory_cost:int,time_cost:int,threads:int} */
    private static function options(): array
    {
        return [
            'memory_cost' => 65536,
            'time_cost' => 3,
            'threads' => 1,
        ];
    }
}
