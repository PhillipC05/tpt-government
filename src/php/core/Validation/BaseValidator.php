<?php
/**
 * TPT Government Platform - Base Validator
 *
 * Abstract base class for all validators providing common validation functionality
 */

namespace Core\Validation;

use Exception;

abstract class BaseValidator
{
    protected array $data = [];
    protected array $rules = [];
    protected array $errors = [];
    protected array $messages = [];
    protected array $customMessages = [];
    protected bool $stopOnFirstFailure = false;

    /**
     * Set validation data
     */
    public function setData(array $data): self
    {
        $this->data = $data;
        return $this;
    }

    /**
     * Set validation rules
     */
    public function setRules(array $rules): self
    {
        $this->rules = $rules;
        return $this;
    }

    /**
     * Set custom error messages
     */
    public function setMessages(array $messages): self
    {
        $this->customMessages = $messages;
        return $this;
    }

    /**
     * Set stop on first failure flag
     */
    public function stopOnFirstFailure(bool $stop = true): self
    {
        $this->stopOnFirstFailure = $stop;
        return $this;
    }

    /**
     * Validate data against rules
     */
    public function validate(array $data = null, array $rules = null): bool
    {
        if ($data !== null) {
            $this->setData($data);
        }

        if ($rules !== null) {
            $this->setRules($rules);
        }

        $this->errors = [];
        $this->messages = [];

        foreach ($this->rules as $field => $fieldRules) {
            $this->validateField($field, $fieldRules);
        }

        return empty($this->errors);
    }

    /**
     * Validate a single field
     */
    protected function validateField(string $field, string|array $rules): void
    {
        $value = $this->getFieldValue($field);
        $rulesArray = is_string($rules) ? explode('|', $rules) : $rules;

        foreach ($rulesArray as $rule) {
            if (!$this->validateRule($field, $value, $rule)) {
                if ($this->stopOnFirstFailure) {
                    break;
                }
            }
        }
    }

    /**
     * Validate a single rule
     */
    protected function validateRule(string $field, mixed $value, string $rule): bool
    {
        // Parse rule parameters (e.g., "min:5" -> "min", ["5"])
        $ruleParts = explode(':', $rule, 2);
        $ruleName = $ruleParts[0];
        $parameters = isset($ruleParts[1]) ? explode(',', $ruleParts[1]) : [];

        // Check if rule should be skipped
        if ($this->shouldSkipRule($ruleName, $value)) {
            return true;
        }

        $methodName = 'validate' . ucfirst($ruleName);

        if (!method_exists($this, $methodName)) {
            throw new Exception("Validation rule '{$ruleName}' does not exist");
        }

        $isValid = $this->$methodName($field, $value, $parameters);

        if (!$isValid) {
            $this->addError($field, $ruleName, $parameters);
        }

        return $isValid;
    }

    /**
     * Check if rule should be skipped
     */
    protected function shouldSkipRule(string $rule, mixed $value): bool
    {
        // Skip validation if value is empty and rule is not 'required'
        if ($rule !== 'required' && $this->isEmpty($value)) {
            return true;
        }

        return false;
    }

    /**
     * Get field value from data
     */
    protected function getFieldValue(string $field): mixed
    {
        return $this->data[$field] ?? null;
    }

    /**
     * Check if value is empty
     */
    protected function isEmpty(mixed $value): bool
    {
        return $value === null || $value === '' || (is_array($value) && empty($value));
    }

    /**
     * Add validation error
     */
    protected function addError(string $field, string $rule, array $parameters = []): void
    {
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = [];
        }

        $this->errors[$field][] = $rule;

        $message = $this->getErrorMessage($field, $rule, $parameters);
        $this->messages[] = $message;
    }

    /**
     * Get error message for rule
     */
    protected function getErrorMessage(string $field, string $rule, array $parameters = []): string
    {
        // Check for custom message
        $customKey = $field . '.' . $rule;
        if (isset($this->customMessages[$customKey])) {
            return $this->customMessages[$customKey];
        }

        // Use default message
        $message = $this->getDefaultMessage($rule);

        // Replace placeholders
        $message = str_replace(':field', $field, $message);
        $message = str_replace(':attribute', $field, $message);

        foreach ($parameters as $index => $parameter) {
            $placeholder = ':param' . ($index + 1);
            $message = str_replace($placeholder, $parameter, $message);
        }

        return $message;
    }

    /**
     * Get default error message for rule
     */
    protected function getDefaultMessage(string $rule): string
    {
        $messages = [
            'required' => 'The :field field is required',
            'email' => 'The :field must be a valid email address',
            'min' => 'The :field must be at least :param1 characters',
            'max' => 'The :field may not be greater than :param1 characters',
            'between' => 'The :field must be between :param1 and :param2',
            'numeric' => 'The :field must be a number',
            'integer' => 'The :field must be an integer',
            'string' => 'The :field must be a string',
            'array' => 'The :field must be an array',
            'boolean' => 'The :field must be true or false',
            'date' => 'The :field is not a valid date',
            'before' => 'The :field must be a date before :param1',
            'after' => 'The :field must be a date after :param1',
            'in' => 'The selected :field is invalid',
            'not_in' => 'The selected :field is invalid',
            'regex' => 'The :field format is invalid',
            'unique' => 'The :field has already been taken',
            'exists' => 'The selected :field is invalid',
        ];

        return $messages[$rule] ?? "The :field field is invalid";
    }

    // Validation Rules

    protected function validateRequired(string $field, mixed $value, array $parameters): bool
    {
        return !$this->isEmpty($value);
    }

    protected function validateEmail(string $field, mixed $value, array $parameters): bool
    {
        return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }

    protected function validateMin(string $field, mixed $value, array $parameters): bool
    {
        $min = (int)$parameters[0];

        if (is_string($value)) {
            return strlen($value) >= $min;
        }

        if (is_numeric($value)) {
            return $value >= $min;
        }

        if (is_array($value)) {
            return count($value) >= $min;
        }

        return false;
    }

    protected function validateMax(string $field, mixed $value, array $parameters): bool
    {
        $max = (int)$parameters[0];

        if (is_string($value)) {
            return strlen($value) <= $max;
        }

        if (is_numeric($value)) {
            return $value <= $max;
        }

        if (is_array($value)) {
            return count($value) <= $max;
        }

        return false;
    }

    protected function validateBetween(string $field, mixed $value, array $parameters): bool
    {
        $min = (int)$parameters[0];
        $max = (int)$parameters[1];

        if (is_string($value)) {
            $length = strlen($value);
            return $length >= $min && $length <= $max;
        }

        if (is_numeric($value)) {
            return $value >= $min && $value <= $max;
        }

        return false;
    }

    protected function validateNumeric(string $field, mixed $value, array $parameters): bool
    {
        return is_numeric($value);
    }

    protected function validateInteger(string $field, mixed $value, array $parameters): bool
    {
        return filter_var($value, FILTER_VALIDATE_INT) !== false;
    }

    protected function validateString(string $field, mixed $value, array $parameters): bool
    {
        return is_string($value);
    }

    protected function validateArray(string $field, mixed $value, array $parameters): bool
    {
        return is_array($value);
    }

    protected function validateBoolean(string $field, mixed $value, array $parameters): bool
    {
        return is_bool($value) || in_array($value, [0, 1, '0', '1', 'true', 'false'], true);
    }

    protected function validateDate(string $field, mixed $value, array $parameters): bool
    {
        if (!is_string($value)) {
            return false;
        }

        $date = date_create($value);
        return $date !== false;
    }

    protected function validateBefore(string $field, mixed $value, array $parameters): bool
    {
        if (!is_string($value) || empty($parameters)) {
            return false;
        }

        $date = date_create($value);
        $compareDate = date_create($parameters[0]);

        return $date && $compareDate && $date < $compareDate;
    }

    protected function validateAfter(string $field, mixed $value, array $parameters): bool
    {
        if (!is_string($value) || empty($parameters)) {
            return false;
        }

        $date = date_create($value);
        $compareDate = date_create($parameters[0]);

        return $date && $compareDate && $date > $compareDate;
    }

    protected function validateIn(string $field, mixed $value, array $parameters): bool
    {
        return in_array($value, $parameters, true);
    }

    protected function validateNotIn(string $field, mixed $value, array $parameters): bool
    {
        return !in_array($value, $parameters, true);
    }

    protected function validateRegex(string $field, mixed $value, array $parameters): bool
    {
        if (!is_string($value) || empty($parameters)) {
            return false;
        }

        return preg_match($parameters[0], $value) === 1;
    }

    /**
     * Get validation errors
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Get validation error messages
     */
    public function getMessages(): array
    {
        return $this->messages;
    }

    /**
     * Get first error message
     */
    public function getFirstError(): ?string
    {
        return $this->messages[0] ?? null;
    }

    /**
     * Check if field has errors
     */
    public function hasErrors(string $field = null): bool
    {
        if ($field === null) {
            return !empty($this->errors);
        }

        return isset($this->errors[$field]);
    }

    /**
     * Get errors for specific field
     */
    public function getFieldErrors(string $field): array
    {
        return $this->errors[$field] ?? [];
    }

    /**
     * Reset validator state
     */
    public function reset(): self
    {
        $this->data = [];
        $this->rules = [];
        $this->errors = [];
        $this->messages = [];
        $this->customMessages = [];
        $this->stopOnFirstFailure = false;

        return $this;
    }

    /**
     * Create validator instance with fluent interface
     */
    public static function make(array $data = [], array $rules = []): static
    {
        return (new static())->setData($data)->setRules($rules);
    }
}
