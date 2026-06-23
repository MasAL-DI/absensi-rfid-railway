<?php

/**
 * Validator Helper — Reusable input validation
 * Gunakan: Validator::validate($data, $rules)
 */
class Validator {
    
    private static $errors = [];

    /**
     * Validate data against rules
     * @param array $data Data to validate
     * @param array $rules Validation rules
     * @return bool True if valid, false otherwise
     */
    public static function validate($data, $rules) {
        self::$errors = [];

        foreach ($rules as $field => $rule) {
            self::validateField($field, $data[$field] ?? null, $rule);
        }

        return empty(self::$errors);
    }

    /**
     * Get validation errors
     * @return array
     */
    public static function errors() {
        return self::$errors;
    }

    /**
     * Validate single field
     */
    private static function validateField($field, $value, $rules) {
        $value = trim($value ?? '');

        // Required
        if (isset($rules['required']) && $rules['required'] && empty($value)) {
            self::$errors[$field] = ucfirst(str_replace('_', ' ', $field)) . ' wajib diisi';
            return;
        }

        if (empty($value)) return;

        // Min length
        if (isset($rules['min']) && strlen($value) < $rules['min']) {
            self::$errors[$field] = ucfirst(str_replace('_', ' ', $field)) . ' minimal ' . $rules['min'] . ' karakter';
        }

        // Max length
        if (isset($rules['max']) && strlen($value) > $rules['max']) {
            self::$errors[$field] = ucfirst(str_replace('_', ' ', $field)) . ' maksimal ' . $rules['max'] . ' karakter';
        }

        // Regex pattern
        if (isset($rules['regex']) && !preg_match($rules['regex'], $value)) {
            self::$errors[$field] = ucfirst(str_replace('_', ' ', $field)) . ' format tidak valid';
        }

        // Email
        if (isset($rules['email']) && $rules['email'] && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            self::$errors[$field] = 'Email tidak valid';
        }

        // Numeric
        if (isset($rules['numeric']) && $rules['numeric'] && !is_numeric($value)) {
            self::$errors[$field] = ucfirst(str_replace('_', ' ', $field)) . ' harus berupa angka';
        }

        // In array (enum-like validation)
        if (isset($rules['in']) && !in_array($value, $rules['in'])) {
            self::$errors[$field] = ucfirst(str_replace('_', ' ', $field)) . ' hanya boleh: ' . implode(', ', $rules['in']);
        }

        // Custom validator (callback)
        if (isset($rules['custom']) && is_callable($rules['custom'])) {
            $error = $rules['custom']($value);
            if ($error !== true) {
                self::$errors[$field] = $error;
            }
        }
    }

    /**
     * Shorthand: validate and respond with error if invalid
     */
    public static function validateOrFail($data, $rules) {
        if (!self::validate($data, $rules)) {
            Response::validationError(self::errors());
        }
    }

    /**
     * Sanitize input (remove dangerous chars)
     */
    public static function sanitize($value, $type = 'string') {
        if ($type === 'email') {
            return filter_var($value, FILTER_SANITIZE_EMAIL);
        }
        if ($type === 'int') {
            return filter_var($value, FILTER_SANITIZE_NUMBER_INT);
        }
        // Default: basic sanitization
        return trim(htmlspecialchars($value, ENT_QUOTES, 'UTF-8'));
    }
}
