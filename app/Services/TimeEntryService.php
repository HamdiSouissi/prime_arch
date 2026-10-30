<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\HourlyRateRepository;
use App\Models\ProjectRepository;
use DateTimeImmutable;

/** Validation des saisies de temps et de leur rattachement au projet. */
final class TimeEntryService
{
    public function __construct(
        private ?ProjectRepository $projects = null,
        private ?HourlyRateRepository $rates = null
    ) {
        $this->projects ??= new ProjectRepository();
        $this->rates ??= new HourlyRateRepository();
    }

    /** @param array<string,mixed> $input
     *  @return array{values:array<string,mixed>,errors:array<string,string>}
     */
    public function validate(array $input, int $userId): array
    {
        $billableInput = $input['is_billable'] ?? '1';
        $billableIsValid = in_array($billableInput, [0, 1, '0', '1', true, false, 'on'], true);
        $values = [
            'project_id' => self::string($input['project_id'] ?? ''),
            'phase_assignment_id' => self::string($input['phase_assignment_id'] ?? ''),
            'work_date' => trim(self::string($input['work_date'] ?? '')),
            'duration_hours' => trim(self::string($input['duration_hours'] ?? '')),
            'description' => trim(self::string($input['description'] ?? '')),
            'is_billable' => in_array($billableInput, [1, '1', true, 'on'], true),
        ];
        $errors = [];
        if (!$billableIsValid) {
            $errors['is_billable'] = __('time.invalid_billable');
        }
        $projectId = ctype_digit($values['project_id']) ? (int) $values['project_id'] : 0;
        $phaseId = ctype_digit($values['phase_assignment_id']) ? (int) $values['phase_assignment_id'] : 0;
        $project = $projectId > 0 ? $this->projects->findById($projectId) : null;
        if ($project === null) {
            $errors['project_id'] = __('time.invalid_project');
        }
        if ($projectId > 0 && $phaseId > 0
            && !$this->projects->phaseAssignmentBelongsTo($phaseId, $projectId)
        ) {
            $errors['phase_assignment_id'] = __('time.invalid_phase');
        } elseif ($phaseId < 1) {
            $errors['phase_assignment_id'] = __('time.invalid_phase');
        }

        $date = self::validDate($values['work_date']);
        if ($date === null || $date > new DateTimeImmutable('today')) {
            $errors['work_date'] = __('time.invalid_date');
        }
        $hours = self::normalizeDecimal($values['duration_hours'], 2, 4);
        if ($hours === null || !self::isQuarterHour($hours)) {
            $errors['duration_hours'] = __('time.invalid_duration');
        } else {
            $values['duration_hours'] = $hours;
        }
        if (mb_strlen($values['description'], 'UTF-8') < 3 || mb_strlen($values['description'], 'UTF-8') > 500) {
            $errors['description'] = __('time.invalid_description');
        }
        if ($date !== null && !isset($errors['work_date']) && $this->rates->rateForDate($userId, $values['work_date']) === null) {
            $errors['rate'] = __('time.missing_rate');
        }
        $values['project_id'] = $projectId > 0 ? $projectId : 0;
        $values['phase_assignment_id'] = $phaseId > 0 ? $phaseId : 0;
        return ['values' => $values, 'errors' => $errors];
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
        $pattern = '/^\d{1,' . $maxWholeDigits . '}(?:\.\d{1,' . $scale . '})?$/';
        if (!preg_match($pattern, $value)) {
            return null;
        }
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        $whole = ltrim($whole, '0');
        $whole = $whole === '' ? '0' : $whole;
        return $whole . '.' . str_pad($fraction, $scale, '0');
    }

    private static function isQuarterHour(string $hours): bool
    {
        [$whole, $fraction] = explode('.', $hours, 2);
        $hundredths = ((int) $whole * 100) + (int) $fraction;
        return $hundredths >= 25 && $hundredths <= 2400 && $hundredths % 25 === 0;
    }

    private static function string(mixed $value): string
    {
        return is_string($value) || is_int($value) ? trim((string) $value) : '';
    }
}
