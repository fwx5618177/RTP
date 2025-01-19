<?php

namespace App\Validator;

use App\Exceptions\ValidationException;

class Validator
{
    private array $errors = [];
    private array $data = [];
    private array $validationStates = [];

    public function __construct()
    {
        $this->validationStates = [
            'required' => [
                'validate' => fn ($value) => isset($value) && ! empty($value),
                'message' => 'The {field} field is required',
            ],
            'string' => [
                'validate' => fn ($value) => ! isset($value) || is_string($value),
                'message' => 'The {field} must be a string',
            ],
            'email' => [
                'validate' => fn ($value) => ! isset($value) || filter_var($value, FILTER_VALIDATE_EMAIL),
                'message' => 'The {field} must be a valid email address',
            ],
            'min' => [
                'validate' => fn ($value, $params) => ! isset($value) || (
                    is_string($value) ?
                    strlen($value) >= (int)$params[0] : (is_numeric($value) ? $value >= (int)$params[0] : false)
                ),
                'message' => fn ($value) => is_numeric($value) ?
                    'The {field} must be at least {param}' :
                    'The {field} must be at least {param} characters',
            ],
            'max' => [
                'validate' => fn ($value, $params) => ! isset($value) || (
                    is_string($value) ?
                    strlen($value) <= (int)$params[0] : (is_numeric($value) ? $value <= (int)$params[0] : false)
                ),
                'message' => fn ($value) => is_numeric($value) ?
                    'The {field} must not exceed {param}' :
                    'The {field} must not exceed {param} characters',
            ],
            'numeric' => [
                'validate' => fn ($value) => is_numeric($value),
                'message' => 'The {field} must be numeric',
            ],
            'alpha' => [
                'validate' => fn ($value) => ctype_alpha($value),
                'message' => 'The {field} must only contain letters',
            ],
            'alphaNum' => [
                'validate' => fn ($value) => ctype_alnum($value),
                'message' => 'The {field} must only contain letters and numbers',
            ],
            'startsWith' => [
                'validate' => fn ($value, $params) => str_starts_with($value, $params[0]),
                'message' => 'The {field} must start with {param}',
            ],
        ];
    }

    public function validate(array $data, array $rules): bool
    {
        $this->data = $data;
        $this->errors = [];

        foreach ($rules as $field => $ruleString) {
            $fieldRules = explode('|', $ruleString);
            $value = $this->data[$field] ?? null;

            // 如果字段不是必填且值为空，跳过其他验证
            if (! in_array('required', $fieldRules) && empty($value)) {
                continue;
            }

            foreach ($fieldRules as $ruleExpression) {
                $params = [];
                $rule = $ruleExpression;

                // 检查规则是否包含参数
                if (str_contains($ruleExpression, ':')) {
                    [$rule, $param] = explode(':', $ruleExpression);
                    $params = explode(',', $param);
                }

                // 检查规则是否存在
                if (! isset($this->validationStates[$rule])) {
                    continue;
                }

                $state = $this->validationStates[$rule];
                if (! $state['validate']($value, $params)) {
                    $message = is_callable($state['message']) ?
                        $state['message']($value) :
                        $state['message'];

                    $message = str_replace(
                        ['{field}', '{param}'],
                        [$field, $params[0] ?? ''],
                        $message
                    );
                    $this->errors[$field][] = $message;
                }
            }
        }

        if (! empty($this->errors)) {
            throw new ValidationException($this->getErrorMessage());
        }

        return true;
    }

    private function getErrorMessage(): string
    {
        $messages = [];
        foreach ($this->errors as $fieldErrors) {
            foreach ($fieldErrors as $error) {
                $messages[] = $error;
            }
        }

        return implode('; ', $messages);
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    // 允许动态添加新的验证规则
    public function addValidationRule(string $name, callable $validator, string $message): void
    {
        $this->validationStates[$name] = [
            'validate' => $validator,
            'message' => $message,
        ];
    }
}
