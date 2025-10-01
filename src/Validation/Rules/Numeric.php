<?php

namespace App\Validation\Rules;

class Numeric implements RuleInterface
{
    private ?float $min;
    private ?float $max;

    public function __construct(?float $min = null, ?float $max = null)
    {
        $this->min = $min;
        $this->max = $max;
    }

    public function validate($value, string $field, array $params = []): bool
    {
        if ($value === null || $value === '') {
            return true; // Use Required rule for empty checks
        }

        if (!is_numeric($value)) {
            return false;
        }

        $value = (float) $value;

        if ($this->min !== null && $value < $this->min) {
            return false;
        }

        if ($this->max !== null && $value > $this->max) {
            return false;
        }

        return true;
    }

    public function getMessage(string $field, array $params = []): string
    {
        if ($this->min !== null && $this->max !== null) {
            return "The {$field} must be between {$this->min} and {$this->max}.";
        }

        if ($this->min !== null) {
            return "The {$field} must be at least {$this->min}.";
        }

        if ($this->max !== null) {
            return "The {$field} may not be greater than {$this->max}.";
        }

        return "The {$field} must be a number.";
    }
}
