<?php

namespace App\Validation\Rules;

class Length implements RuleInterface
{
    private ?int $min;
    private ?int $max;

    public function __construct(?int $min = null, ?int $max = null)
    {
        $this->min = $min;
        $this->max = $max;
    }

    public function validate($value, string $field, array $params = []): bool
    {
        if ($value === null) {
            return true; // Use Required rule for null checks
        }

        $length = is_string($value) ? mb_strlen($value) : count((array) $value);
        
        if ($this->min !== null && $length < $this->min) {
            return false;
        }
        
        if ($this->max !== null && $length > $this->max) {
            return false;
        }
        
        return true;
    }

    public function getMessage(string $field, array $params = []): string
    {
        if ($this->min !== null && $this->max !== null) {
            return "The {$field} must be between {$this->min} and {$this->max} characters.";
        }
        
        if ($this->min !== null) {
            return "The {$field} must be at least {$this->min} characters.";
        }
        
        if ($this->max !== null) {
            return "The {$field} may not be greater than {$this->max} characters.";
        }
        
        return "The {$field} has an invalid length.";
    }
}
