<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\HourlyRateRepository;
use App\Models\UserRepository;
use DateTimeImmutable;

/** Validation des nouveaux taux internes sans modification de l'historique existant. */
final class HourlyRateService
{
    public function __construct(
        private ?HourlyRateRepository $rates = null,
        private ?UserRepository $users = null
    ) {
        $this->rates ??= new HourlyRateRepository();
        $this->users ??= new UserRepository();
    }

    /** @param array<string,mixed> $input
     *  @return array{values:array{user_id:int,effective_from:string,cost_rate_per_hour:string,note:?string},errors:array<string,string>}
     */
    public function validate(array $input): array
    {
        $userInput = self::string($input['user_id'] ?? '');
        $date = trim(self::string($input['effective_from'] ?? ''));
        $rateInput = trim(self::string($input['cost_rate_per_hour'] ?? ''));
        $note = trim(self::string($input['note'] ?? ''));
        $errors = [];

        $userId = ctype_digit($userInput) ? (int) $userInput : 0;
        $user = $userId > 0 ? $this->users->findById($userId) : null;
        if ($user === null || empty($user['is_active'])) {
            $errors['user_id'] = __('finance.invalid_user');
        }
        if (self::validDate($date) === null) {
            $errors['effective_from'] = __('finance.invalid_date');
        }
        $rate = self::normalizeDecimal($rateInput, 3, 9);
        if ($rate === null) {
            $errors['cost_rate_per_hour'] = __('finance.invalid_rate');
        }
        if (mb_strlen($note, 'UTF-8') > 255) {
            $errors['note'] = __('finance.invalid_rate');
        }
        if ($user !== null && empty($errors['effective_from']) && $this->rates->existsForDate($userId, $date)) {
            $errors['effective_from'] = __('finance.rate_date_exists');
        }

        return [
            'values' => [
                'user_id' => $userId,
                'effective_from' => $date,
                'cost_rate_per_hour' => $rate ?? '',
                'note' => $note !== '' ? $note : null,
            ],
            'errors' => $errors,
        ];
    }

    private static function validDate(string $value): ?DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errors = DateTimeImmutable::getLastErrors();
        if ($date === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
            || $date->format('Y-m-d') !== $value
        ) {
            return null;
        }
        return $date;
    }

    private static function normalizeDecimal(string $value, int $scale, int $maxWholeDigits): ?string
    {
        $value = str_replace(',', '.', trim($value));
        if (!preg_match('/^\d{1,' . $maxWholeDigits . '}(?:\.\d{1,' . $scale . '})?$/', $value)) {
            return null;
        }
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        $whole = ltrim($whole, '0');
        return ($whole === '' ? '0' : $whole) . '.' . str_pad($fraction, $scale, '0');
    }

    private static function string(mixed $value): string
    {
        return is_string($value) || is_int($value) ? trim((string) $value) : '';
    }
}
