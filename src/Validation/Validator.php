<?php

namespace App\Validation;

use App\Validation\Rules\RuleInterface;
use InvalidArgumentException;

class Validator
{
    private array $errors = [];
    private array $rules = [];
    private array $customMessages = [];

    /**
     * Add a validation rule for a field
     *
     * @param string $field The field name
     * @param RuleInterface|array $rules A single rule or an array of rules
     * @return self
     */
    public function addRule(string $field, $rules): self
    {
        if (!is_array($rules)) {
            $rules = [$rules];
        }

        foreach ($rules as $rule) {
            if (!$rule instanceof RuleInterface) {
                throw new InvalidArgumentException('Rule must implement RuleInterface');
            }
            $this->rules[$field][] = $rule;
        }

        return $this;
    }

    /**
     * Validate the given data against the rules
     *
     * @param array $data The data to validate
     * @return bool True if validation passes, false otherwise
     */
    public function validate(array $data): bool
    {
        $this->errors = [];
        $isValid = true;

        foreach ($this->rules as $field => $rules) {
            $value = $data[$field] ?? null;

            foreach ($rules as $rule) {
                if (!$rule->validate($value, $field, $data)) {
                    $this->addError($field, $rule->getMessage($field));
                    $isValid = false;
                    break; // Stop validating this field after first error
                }
            }
        }

        return $isValid;
    }

    /**
     * Add an error message for a field
     *
     * @param string $field The field name
     * @param string $message The error message
     * @return void
     */
    public function addError(string $field, string $message): void
    {
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = [];
        }

        $this->errors[$field][] = $message;
    }

    /**
     * Get all validation errors
     *
     * @return array An array of error messages grouped by field
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Check if validation has errors
     *
     * @return bool True if there are validation errors, false otherwise
     */
    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }

    /**
     * Get the first error message for a field
     *
     * @param string $field The field name
     * @return string|null The first error message or null if no errors
     */
    public function getFirstError(string $field): ?string
    {
        return $this->errors[$field][0] ?? null;
    }
}
