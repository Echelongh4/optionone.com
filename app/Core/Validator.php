<?php

declare(strict_types=1);

namespace App\Core;

class Validator
{
    public static function validate(array $data, array $rules): array
    {
        $errors = [];

        foreach ($rules as $field => $ruleSet) {
            $value = $data[$field] ?? null;
            $rulesList = explode('|', $ruleSet);
            $nullable = in_array('nullable', $rulesList, true);

            if ($nullable && ($value === null || $value === '')) {
                continue;
            }

            foreach ($rulesList as $rule) {
                [$name, $parameter] = array_pad(explode(':', $rule, 2), 2, null);

                $failed = match ($name) {
                    'required' => $value === null || $value === '',
                    'email' => $value !== null && filter_var($value, FILTER_VALIDATE_EMAIL) === false,
                    'numeric' => $value !== null && !is_numeric($value),
                    'integer' => $value !== null && filter_var($value, FILTER_VALIDATE_INT) === false,
                    'min' => $value !== null && mb_strlen((string) $value) < (int) $parameter,
                    'max' => $value !== null && mb_strlen((string) $value) > (int) $parameter,
                    'in' => $value !== null && !in_array((string) $value, array_map('trim', explode(',', (string) $parameter)), true),
                    default => false,
                };

                if ($failed) {
                    $errors[$field][] = match ($name) {
                        'required' => 'This field is required.',
                        'email' => 'Enter a valid email address.',
                        'numeric' => 'This value must be numeric.',
                        'integer' => 'This value must be an integer.',
                        'min' => 'This value is too short.',
                        'max' => 'This value is too long.',
                        'in' => 'The selected option is invalid.',
                        default => 'Invalid value.',
                    };
                }
            }
        }

        return $errors;
    }
}