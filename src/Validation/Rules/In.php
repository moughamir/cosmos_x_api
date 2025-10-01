<?php

namespace App\Validation\Rules;

class In implements RuleInterface
{
    private array $allowedValues;

    public function __construct(array $allowedValues)
    {
        $this->allowedValues = $allowedValues;
    }

    public function validate($value, string $field, array $params = []): bool
    {
        if ($value === null || $value === '') {
            return true; // Use Required rule for empty checks
        }

        return in_array($value, $this->allowedValues, true);
    }

    public function getMessage(string $field, array $params = []): string
    {
        $allowed = implode(', ', array_map(function ($value) {
            return is_string($value) ? "'{$value}'" : $value;
        }, $this->allowedValues));

        return "The selected {$field} is invalid. Allowed values are: {$allowed}.";
    }
}
