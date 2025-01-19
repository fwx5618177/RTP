<div align="center">
<h1 align="center">RTP</h1>

[![GitHub stars](https://img.shields.io/github/stars/fwx5618177/RTP.svg?style=social&label=Stars)](https://github.com/fwx5618177/RTP)
[![GitHub issues](https://img.shields.io/github/issues/fwx5618177/RTP.svg)](https://github.com/fwx5618177/RTP/issues)
[![GitHub license](https://img.shields.io/github/license/fwx5618177/RTP.svg)](https://github.com/fwx5618177/RTP/blob/main/LICENSE)
[![GitHub pull requests](https://img.shields.io/github/issues-pr/fwx5618177/RTP.svg)](https://github.com/fwx5618177/RTP/pulls)
[![GitHub contributors](https://img.shields.io/github/contributors/fwx5618177/RTP.svg)](https://github.com/fwx5618177/RTP/graphs/contributors)

[English](./README.md) | [简体中文](./README_ZH.md) | [日本語](./README_JP.md)

</div>

## Project Introduction

RTP is a high-performance real-time transport protocol bridge backend built on PHP and Swoole, designed to provide stable and efficient real-time data transmission services.

## Language Selection

This project is available in the following languages:

- [English](README.md)
- [简体中文](README.zh.md)
- [日本語](README.ja.md)

## Project Architecture

This project adopts a layered architecture design with the following main workflow:

1. Client -> Route: Client requests first enter the routing layer
2. Route -> Middleware: Routing layer matches middleware based on request path
3. Middleware -> Http: Middleware handles request preprocessing
4. Http -> Controller: HTTP layer parses requests and passes to controllers
5. Controller -> DTO: Controllers convert request data to DTO objects
6. DTO -> Service: DTO objects are passed to business service layer
7. Service -> Entity: Business service layer operates on entity objects
8. Entity -> Repository: Entity objects are persisted through repository layer
9. Repository -> DB: Final data storage in database

### Architecture Usage

1. **Route Definition**

   - Define routes in src/Routes/ directory
   - Use Route class to register routes
   - Support HTTP methods like GET/POST/PUT/DELETE

2. **Middleware Usage**

   - Create middleware in src/Middlewares/ directory
   - Implement MiddlewareInterface
   - Add through middleware() method in route definition

3. **Controller Development**

   - Create controllers in src/Controllers/ directory
   - Extend BaseController
   - Get request data through $request object
   - Return Response object

4. **DTO Conversion**

   - Define DTO classes in src/DTO/ directory
   - Use Validator for data validation
   - Convert to array through toArray() method

5. **Service Layer Development**

   - Create service classes in src/Services/ directory
   - Extend BaseService
   - Use Repository through dependency injection

6. **Entity and Repository**
   - Define entity classes in src/Entity/
   - Implement repository interfaces in src/Repository/
   - Register repositories using DatabaseServiceProvider

## TODO: Future Architecture Improvements

### Architecture Migration

- [ ] Migrate from layered architecture to DDD (Domain-Driven Design)
- [ ] Divide modules by business domain
- [ ] Define aggregate roots and value objects
- [ ] Implement domain services
- [ ] Implement CQRS pattern
- [ ] Add event-driven mechanism
- [ ] Implement domain events

### Infrastructure

- [ ] Add message queue support (RabbitMQ/Kafka)
- [ ] Implement distributed caching (Redis/Memcached)
- [ ] Add monitoring and log tracing (Prometheus + Grafana)
- [ ] Implement API gateway
- [ ] Add service discovery mechanism
- [ ] Implement auto-scaling
- [ ] Implement node snapshot functionality

### Testing Improvements

- [ ] Add integration tests
- [ ] Implement contract tests
- [ ] Add performance tests
- [ ] Implement chaos engineering tests
- [ ] Add security tests

### Current Unimplemented Features

- [ ] User authentication and authorization (JWT/OAuth2)
- [ ] File upload and storage
- [ ] Data pagination and sorting
- [ ] Data export functionality (CSV/Excel)
- [ ] Scheduled task scheduling
- [ ] Email notification service
- [ ] SMS verification service
- [ ] Third-party login integration
- [ ] API documentation auto-generation
- [ ] Data migration tools

## Project Structure

```
.
├── config/               # Configuration files
│   ├── .env              # Environment variables
│   └── .env.sample       # Environment variables example
├── database/             # Database related files
│   ├── migrate.php       # Database migration script
│   └── migrations/       # Database migration files
├── docs/                 # Project documentation
├── logs/                 # System log files
├── scripts/              # Deployment and maintenance scripts
├── src/                  # Core code
│   ├── Config/           # Configuration classes
│   │   ├── Config.php    # Configuration management
│   │   └── Routes.php    # Route configuration
│   ├── Controllers/      # Controllers
│   ├── DTO/              # Data Transfer Objects
│   ├── Entity/           # Entity classes
│   ├── Exceptions/       # Custom exceptions
│   ├── Http/             # HTTP related components
│   │   ├── Request.php   # HTTP request handling
│   │   └── Response.php  # HTTP response handling
│   ├── Interfaces/       # Interface definitions
│   │   ├── MiddlewareInterface.php  # Middleware interface
│   │   └── ModelInterface.php       # Model interface
│   ├── Logs/             # Log handling
│   │   ├── Logger.php    # Log recording
│   │   └── LogRotateService.php # Log rotation service
│   ├── Middlewares/      # Middleware
│   │   ├── MiddlewareStack.php      # Middleware stack
│   │   ├── TestConditionMiddleware.php # Test condition middleware
│   │   └── TestFlowMiddleware.php   # Test flow middleware
│   ├── Providers/        # Service providers
│   │   └── DatabaseServiceProvider.php # Database service provider
│   ├── Repository/       # Data access layer
│   ├── Routes/           # Route definitions
│   │   ├── Route.php     # Route class
│   │   └── Router.php    # Router
│   ├── Server/           # Server configuration
│   │   └── ApiServer.php # API server
│   ├── Services/         # Business logic
│   ├── Utils/            # Utility classes
│   │   └── Container.php # Dependency injection container
│   └── Validator/        # Data validation
│       └── Validator.php # Validator
├── tests/                # Test code
│   ├── http/             # HTTP API tests
│   │   ├── middleware-api.http # Middleware API test
│   │   └── user-api.http       # User API test
│   └── Validator/        # Validator tests
│       └── ValidatorTest.php
└── README.md             # Project documentation
```

## Directory Purposes

- **config/**: Store project configuration files, including environment variables and configuration classes
- **docs/**: Project related documentation
- **scripts/**: Deployment scripts, maintenance scripts, etc.
- **src/**: Project core code
  - **DTO/**: Data Transfer Objects for inter-layer data transfer
  - **Models/**: Data models, defining data structures and business entities
  - **Repositories/**: Data access layer, responsible for database interaction
  - **Services/**: Business logic layer, handling core business
  - **Controllers/**: Controllers, handling HTTP requests
  - **Middlewares/**: Middleware, handling request pre-processing and response post-processing
  - **Routes/**: Route definitions, mapping URLs to controllers
  - **Utils/**: Utility classes, providing common functionality
  - **Logs/**: Log handling
  - **Exceptions/**: Custom exception handling
  - **Interfaces/**: Interface definitions
  - **Http/**: HTTP related components, including request/response handling, form validation, etc.
  - **Server/**: Server related configuration
- **tests/**: Unit tests and functional test code

## Code Check and Formatting

The project uses the following tools to maintain code quality and consistent style:

- **PHP_CodeSniffer**: Check code style and detect common errors
- **PHP-CS-Fixer**: Automatically fix code style issues
- **PHPUnit**: Unit testing framework for functional testing and error detection

Note: These tools are mainly used for code style checking and formatting. While they can detect some syntax errors, they cannot replace professional static code analysis tools.

### Tool Installation

```bash
composer require --dev squizlabs/php_codesniffer friendsofphp/php-cs-fixer
```

### Usage

1. Check code style:

```bash
./vendor/bin/phpcs
```

2. Automatically fix code style:

```bash
./vendor/bin/phpcbf
```

3. Format code using PHP-CS-Fixer:

```bash
PHP_CS_FIXER_IGNORE_ENV=1 ./vendor/bin/php-cs-fixer fix
```

Note: Current PHP version (8.4.3) is higher than the maximum version supported by PHP-CS-Fixer (8.3.\*), need to set PHP_CS_FIXER_IGNORE_ENV environment variable to ignore version check.

### Configuration

- `phpcs.xml`: PHP_CodeSniffer configuration file
- `.php-cs-fixer.php`: PHP-CS-Fixer configuration file

## Contribution Guidelines

We welcome any form of contribution! Before starting to contribute, please read the following guidelines.

### Development Process

1. Fork project repository
2. Create feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to branch (`git push origin feature/AmazingFeature`)
5. Submit Pull Request

### Code Style

- Follow PSR-12 coding standard
- Use PHP_CodeSniffer to check code style
- Use PHP-CS-Fixer to automatically format code
- Use UpperCamelCase for class names
- Use lowerCamelCase for method names
- Use UPPER_CASE for constants
- Use lowerCamelCase for variable names

### Testing Requirements

- All new features must include unit tests
- Bug fixes must include regression tests
- Test coverage should remain above 80%
- Use `.http` files for API testing
- Recommended tools:
  - **REST Client** (VSCode plugin)
    - Installation: Search for "REST Client" in VSCode extension marketplace and install
    - Usage: Open `.http` file directly, click "Send Request" button to run test
  - **Postman**
    - Import `.http` files for testing
    - Support more complex test scenarios and automated testing

### Running Tests

```bash
# Run all tests
./vendor/bin/phpunit

# Run specific test file
./vendor/bin/phpunit tests/Validator/ValidatorTest.php
```

### Pull Request Guidelines

- Title format: [Type] Brief description
  - Example: [Feature] Add user authentication
  - Types include: Feature, Bugfix, Refactor, Docs, Style, Test
- Description should include:
  - Problem solved or feature implemented
  - Test results
  - Related issue number (if any)
  - Breaking changes description (if any)
- Ensure all tests pass
- Ensure code style compliance
- Ensure documentation is updated

## Installation and Configuration

### Requirements

- PHP 8.4.3 or higher
- Composer 2.0 or higher
- Swoole 6.0.0 or higher
- MySQL 8.0 or higher
- Redis 6.0 or higher (optional)

### Installation Steps

1. Clone project:

```bash
git clone https://github.com/fwx5618177/rtp.git
cd rtp
```

2. Install dependencies:

```bash
composer install
```

3. Install Swoole extension:

```bash
pecl install swoole
```

Note: Swoole requires PHP to be compiled with ZTS (Zend Thread Safety) enabled. If you encounter installation issues, ensure:

- PHP was compiled with --enable-zts parameter
- Check php -i | grep Thread output includes "Thread Safety => enabled"
- If using brew-installed PHP, recommend reinstalling with brew install php --with-zts

4. Enable Swoole extension:

```bash
echo "extension=swoole.so" >> $(php -i | grep "Loaded Configuration File" | awk '{print $5}')
```

5. Configure environment variables:

```bash
cp config/.env.sample config/.env
```

Edit config/.env file, configure the following:

```env
# Application configuration
APP_ENV=local
APP_DEBUG=true
APP_KEY=base64:your_app_key

# Database configuration
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=rtp
DB_USERNAME=root
DB_PASSWORD=

# Redis configuration (optional)
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=null

# Swoole configuration
SWOOLE_HOST=0.0.0.0
SWOOLE_PORT=9501
SWOOLE_WORKER_NUM=4
SWOOLE_TASK_WORKER_NUM=2
```

6. Generate application key:

```bash
php artisan key:generate
```

7. Run database migrations:

```bash
php database/migrate.php
```

8. Start development server:

```bash
php src/index.php
```

### Default Configuration

- Listen address: 0.0.0.0
- Listen port: 9501
- Access address: http://localhost:9501

## Community Guidelines

- [Code of Conduct](CODE_OF_CONDUCT.md) - Our community behavior standards
- [Security Policy](SECURITY.md) - How to report security issues

## License

This project is released under the [MIT License](LICENSE).

```text
MIT License

Copyright (c) 2023 fwx5618177 <fwx5618177@gmail.com>

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
```
