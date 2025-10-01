<?php

namespace App\Validation\Rules;

interface RuleInterface
{
    /**
     * Validate the given value
     *
     * @param mixed $value The value to validate
     * @param string $field The field name being validated
     * @param array $params Additional parameters for the rule
     * @return bool True if validation passes, false otherwise
     */
    public function validate($value, string $field, array $params = []): bool;

    /**
     * Get the validation error message
     *
     * @param string $field The field name
     * @param array $params Additional parameters for the message
     * @return string The error message
     */
    public function getMessage(string $field, array $params = []): string;
}
