<?php

namespace Tests\Validator;

use App\Exceptions\ValidationException;
use App\Validator\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ValidatorTest extends TestCase
{
    private Validator $validator;

    protected function setUp(): void
    {
        $this->validator = new Validator();
    }

    #[DataProvider('validDataProvider')]
    public function testValidDataPasses(array $data, array $rules): void
    {
        $result = $this->validator->validate($data, $rules);
        $this->assertTrue($result);
    }

    #[DataProvider('invalidDataProvider')]
    public function testInvalidDataThrowsException(array $data, array $rules, string $expectedMessage): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage($expectedMessage);

        $this->validator->validate($data, $rules);
    }

    public function testCustomValidationRule(): void
    {
        $this->validator->addValidationRule(
            'phone',
            fn($value) => preg_match('/^1[3-9]\d{9}$/', $value),
            'The {field} must be a valid phone number'
        );

        $data = ['phone' => '13800138000'];
        $rules = ['phone' => 'required|phone'];

        $result = $this->validator->validate($data, $rules);
        $this->assertTrue($result);
    }

    public static function validDataProvider(): array
    {
        return [
            'basic required test' => [
                ['name' => 'John'],
                ['name' => 'required'],
            ],
            'multiple rules test' => [
                ['username' => 'john123', 'email' => 'john@example.com'],
                [
                    'username' => 'required|alphaNum|min:3',
                    'email' => 'required|email',
                ],
            ],
            'numeric value test' => [
                ['age' => '25'],
                ['age' => 'required|numeric'],
            ],
            'string with min length' => [
                ['password' => 'secret123'],
                ['password' => 'required|string|min:6'],
            ],
            'string with max length' => [
                ['title' => 'Hello'],
                ['title' => 'string|max:10'],
            ],
            'alpha only test' => [
                ['name' => 'John'],
                ['name' => 'required|alpha'],
            ],
            'alphaNum with min and max' => [
                ['username' => 'user123'],
                ['username' => 'required|alphaNum|min:3|max:10'],
            ],
            'email with optional field' => [
                ['email' => 'test@example.com', 'website' => ''],
                ['email' => 'required|email', 'website' => 'string'],
            ],
            'numeric range test' => [
                ['age' => 20, 'score' => 85],
                ['age' => 'required|numeric|min:18', 'score' => 'numeric|min:0|max:100'],
            ],
            'complex password validation' => [
                ['password' => 'Secret123!@#'],
                ['password' => 'required|string|min:8|max:32'],
            ],
            'multiple optional fields' => [
                ['name' => 'John', 'age' => '25'],
                ['name' => 'required|alpha', 'age' => 'numeric', 'bio' => 'string'],
            ],
        ];
    }

    public static function invalidDataProvider(): array
    {
        return [
            'missing required field' => [
                [],
                ['name' => 'required'],
                'The name field is required',
            ],
            'invalid email' => [
                ['email' => 'invalid-email'],
                ['email' => 'required|email'],
                'The email must be a valid email address',
            ],
            'string too short' => [
                ['password' => '123'],
                ['password' => 'required|min:6'],
                'The password must be at least 6',
            ],
            'string too long' => [
                ['title' => 'This is a very long title'],
                ['title' => 'max:10'],
                'The title must not exceed 10 characters',
            ],
            'non-numeric value' => [
                ['age' => 'abc'],
                ['age' => 'numeric'],
                'The age must be numeric',
            ],
            'numeric out of range' => [
                ['age' => '15'],
                ['age' => 'numeric|min:18'],
                'The age must be at least 18',
            ],
            'multiple validation errors' => [
                ['username' => '12', 'email' => 'invalid'],
                [
                    'username' => 'required|alphaNum|min:3',
                    'email' => 'required|email',
                ],
                'The username must be at least 3; The email must be a valid email address',
            ],
            'empty required fields' => [
                ['name' => '', 'email' => '  '],
                ['name' => 'required', 'email' => 'required'],
                'The name field is required',
            ],
        ];
    }

    public function testAddingAndUsingCustomRule(): void
    {
        // 添加自定义验证规则
        $this->validator->addValidationRule(
            'startsWith',
            fn($value, $params) => str_starts_with($value, $params[0]),
            'The {field} must start with {param}'
        );

        // 测试有效数据
        $validData = ['username' => 'admin_user'];
        $this->assertTrue(
            $this->validator->validate($validData, ['username' => 'startsWith:admin'])
        );

        // 测试无效数据
        $this->expectException(ValidationException::class);
        $invalidData = ['username' => 'user_admin'];
        $this->validator->validate($invalidData, ['username' => 'startsWith:admin']);
    }

    public function testMultipleCustomRules(): void
    {
        // 添加手机号验证规则
        $this->validator->addValidationRule(
            'phone',
            fn($value) => preg_match('/^1[3-9]\d{9}$/', $value),
            'The {field} must be a valid phone number'
        );

        // 添加邮编验证规则
        $this->validator->addValidationRule(
            'postcode',
            fn($value) => preg_match('/^\d{6}$/', $value),
            'The {field} must be a valid post code'
        );

        // 测试有效数据
        $validData = [
            'phone' => '13800138000',
            'postcode' => '100001',
        ];
        $rules = [
            'phone' => 'required|phone|min:3',
            'postcode' => 'required|postcode|min:6',
        ];

        $result = $this->validator->validate($validData, $rules);
        $this->assertTrue($result);

        // 测试无效数据
        $this->expectException(ValidationException::class);
        $invalidData = [
            'phone' => '12345678901',
            'postcode' => '1234',
        ];
        $this->validator->validate($invalidData, $rules);
    }

    public function testCombinedCustomAndStandardRules(): void
    {
        $this->validator->addValidationRule(
            'url',
            fn($value) => filter_var($value, FILTER_VALIDATE_URL),
            'The {field} must be a valid URL'
        );

        $data = [
            'website' => 'https://example.com',
            'description' => 'A valid website',
        ];
        $rules = [
            'website' => 'required|url|startsWith:https',
            'description' => 'required|string|min:5|max:50',
        ];

        $result = $this->validator->validate($data, $rules);
        $this->assertTrue($result);
    }
}
