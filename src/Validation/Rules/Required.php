<?php

namespace App\Validation\Rules;

class Required implements RuleInterface
{
    public function validate($value, string $field, array $params = []): bool
    {
        if (is_string($value)) {
            $value = trim($value);
        }

        return !in_array($value, [null, '', []], true);
    }

    public function getMessage(string $field, array $params = []): string
    {
        return "The {$field} field is required.";
    }
}
