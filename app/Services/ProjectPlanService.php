<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\ProjectRepository;

/** Validation du budget d'heures et de la pondération des phases. */
final class ProjectPlanService
{
    public function __construct(private ?ProjectRepository $projects = null)
    {
        $this->projects ??= new ProjectRepository();
    }

    /** @param array<int|string,mixed> $submitted
     *  @return array{values:array{budget_hours:?string,phases:array<int,array{weight_percent:string,budget_hours:?string}>},errors:array<string,string>}
     */
    public function validate(int $projectId, mixed $budgetHours, mixed $submitted): array
    {
        $errors = [];
        $normalizedBudget = self::optionalDecimal($budgetHours, 2, 7);
        if (self::string($budgetHours) !== '' && $normalizedBudget === null) {
            $errors['budget_hours'] = __('projects.invalid_budget');
        }

        $current = $this->projects->phaseAssignments($projectId);
        if (!is_array($submitted)) {
            $submitted = [];
        }
        $submittedById = [];
        foreach ($submitted as $key => $phaseInput) {
            $id = ctype_digit((string) $key) ? (int) $key : 0;
            if ($id < 1 || !is_array($phaseInput)) {
                $errors['plan'] = __('projects.invalid_plan');
                continue;
            }
            $submittedById[$id] = $phaseInput;
        }
        $knownIds = array_column($current, 'id');
        foreach (array_keys($submittedById) as $id) {
            if (!in_array($id, $knownIds, true)) {
                $errors['plan'] = __('projects.invalid_plan');
            }
        }

        $phases = [];
        $weightTotal = 0;
        foreach ($current as $phase) {
            $phaseInput = $submittedById[$phase['id']] ?? [];
            $rawWeight = self::string($phaseInput['weight_percent'] ?? $phase['weight_percent'] ?? '0');
            $weightUnits = self::toMinorUnits($rawWeight, 2, 3);
            $phaseBudgetInput = $phaseInput['budget_hours'] ?? $phase['budget_hours'] ?? '';
            $phaseBudget = self::optionalDecimal($phaseBudgetInput, 2, 7);
            if ($weightUnits === null || $weightUnits > 10000) {
                $errors['weight_percent'] = __('projects.invalid_weights');
                $weightUnits = 0;
            }
            if (self::string($phaseBudgetInput) !== '' && $phaseBudget === null) {
                $errors['phase_budget_hours'] = __('projects.invalid_budget');
            }
            $weightTotal += $weightUnits;
            $phases[$phase['id']] = [
                'weight_percent' => self::fromMinorUnits($weightUnits, 2),
                'budget_hours' => $phaseBudget,
            ];
        }
        if ($current !== [] && $weightTotal !== 10000) {
            $errors['weight_percent'] = __('projects.invalid_weights');
        }
        if ($current === [] && $submitted !== []) {
            $errors['plan'] = __('projects.invalid_plan');
        }

        return [
            'values' => ['budget_hours' => $normalizedBudget, 'phases' => $phases],
            'errors' => $errors,
        ];
    }

    public function normalizeProgress(mixed $value): ?string
    {
        $units = self::toMinorUnits(self::string($value), 2, 3);
        return $units === null || $units > 10000 ? null : self::fromMinorUnits($units, 2);
    }

    private static function optionalDecimal(mixed $value, int $scale, int $maxWholeDigits): ?string
    {
        $text = self::string($value);
        if ($text === '') {
            return null;
        }
        $units = self::toMinorUnits($text, $scale, $maxWholeDigits);
        return $units === null ? null : self::fromMinorUnits($units, $scale);
    }

    private static function toMinorUnits(string $value, int $scale, int $maxWholeDigits): ?int
    {
        $value = str_replace(',', '.', trim($value));
        $pattern = '/^\d{1,' . $maxWholeDigits . '}(?:\.\d{1,' . $scale . '})?$/';
        if (!preg_match($pattern, $value)) {
            return null;
        }
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        $fraction = str_pad($fraction, $scale, '0');
        $whole = ltrim($whole, '0');
        $whole = $whole === '' ? '0' : $whole;
        return ((int) $whole * (10 ** $scale)) + (int) $fraction;
    }

    private static function fromMinorUnits(int $units, int $scale): string
    {
        $factor = 10 ** $scale;
        $whole = intdiv($units, $factor);
        $fraction = $units % $factor;
        return $whole . '.' . str_pad((string) $fraction, $scale, '0', STR_PAD_LEFT);
    }

    private static function string(mixed $value): string
    {
        return is_string($value) || is_int($value) ? trim((string) $value) : '';
    }
}
