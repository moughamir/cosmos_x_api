<?php

namespace App\Validation\Rules;

class Email implements RuleInterface
{
    public function validate($value, string $field, array $params = []): bool
    {
        if ($value === null || $value === '') {
            return true; // Use Required rule for empty checks
        }

        return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }

    public function getMessage(string $field, array $params = []): string
    {
        return "The {$field} must be a valid email address.";
    }
}
