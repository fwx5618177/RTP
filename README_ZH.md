<div align="center">
<h1 align="center">RTP</h1>

[![GitHub stars](https://img.shields.io/github/stars/fwx5618177/RTP.svg?style=social&label=Stars)](https://github.com/fwx5618177/RTP)
[![GitHub issues](https://img.shields.io/github/issues/fwx5618177/RTP.svg)](https://github.com/fwx5618177/RTP/issues)
[![GitHub license](https://img.shields.io/github/license/fwx5618177/RTP.svg)](https://github.com/fwx5618177/RTP/blob/main/LICENSE)
[![GitHub pull requests](https://img.shields.io/github/issues-pr/fwx5618177/RTP.svg)](https://github.com/fwx5618177/RTP/pulls)
[![GitHub contributors](https://img.shields.io/github/contributors/fwx5618177/RTP.svg)](https://github.com/fwx5618177/RTP/graphs/contributors)

[English](./README.md) | [简体中文](./README_ZH.md) | [日本語](./README_JP.md)

</div>

## 项目介绍

RTP 是一个高性能的实时传输协议桥接后端，基于 PHP 和 Swoole 构建，旨在提供稳定、高效的实时数据传输服务。

## 语言选择

本项目提供以下语言版本：

- [English](README.md)
- [简体中文](README.zh.md)
- [日本語](README.ja.md)

## 项目架构

本项目采用分层架构设计，主要流程如下：

1. Client -> Route: 客户端请求首先进入路由层
2. Route -> Middleware: 路由层根据请求路径匹配中间件
3. Middleware -> Http: 中间件处理请求预处理
4. Http -> Controller: HTTP 层解析请求并传递给控制器
5. Controller -> DTO: 控制器将请求数据转换为 DTO 对象
6. DTO -> Service: DTO 对象传递给业务服务层
7. Service -> Entity: 业务服务层操作实体对象
8. Entity -> Repository: 实体对象通过仓储层持久化
9. Repository -> DB: 最终数据存储到数据库

### 架构使用方法

1. **路由定义**

   - 在 src/Routes/ 目录下定义路由
   - 使用 Route 类注册路由
   - 支持 GET/POST/PUT/DELETE 等 HTTP 方法

2. **中间件使用**

   - 在 src/Middlewares/ 目录下创建中间件
   - 实现 MiddlewareInterface 接口
   - 在路由定义时通过 middleware() 方法添加

3. **控制器开发**

   - 在 src/Controllers/ 目录下创建控制器
   - 继承 BaseController
   - 通过 $request 对象获取请求数据
   - 返回 Response 对象

4. **DTO 转换**

   - 在 src/DTO/ 目录下定义 DTO 类
   - 使用 Validator 进行数据验证
   - 通过 toArray() 方法转换为数组

5. **服务层开发**

   - 在 src/Services/ 目录下创建服务类
   - 继承 BaseService
   - 通过依赖注入使用 Repository

6. **实体与仓储**
   - 在 src/Entity/ 定义实体类
   - 在 src/Repository/ 实现仓储接口
   - 使用 DatabaseServiceProvider 注册仓储

## TODO: 未来架构改进计划

### 架构迁移

- [ ] 从分层架构迁移到 DDD（领域驱动设计）架构
- [ ] 按业务领域划分模块
- [ ] 定义聚合根和值对象
- [ ] 实现领域服务
- [ ] 实现 CQRS 模式
- [ ] 添加事件驱动机制
- [ ] 实现领域事件

### 基础设施

- [ ] 添加消息队列支持（RabbitMQ/Kafka）
- [ ] 实现分布式缓存（Redis/Memcached）
- [ ] 添加监控和日志追踪（Prometheus + Grafana）
- [ ] 实现 API 网关
- [ ] 添加服务发现机制
- [ ] 实现自动扩展（Auto-scaling）
- [ ] 实现节点快照功能

### 测试改进

- [ ] 添加集成测试
- [ ] 实现契约测试
- [ ] 添加性能测试
- [ ] 实现混沌工程测试
- [ ] 添加安全测试

### 当前未实现功能

- [ ] 用户认证与授权（JWT/OAuth2）
- [ ] 文件上传与存储
- [ ] 数据分页与排序
- [ ] 数据导出功能（CSV/Excel）
- [ ] 定时任务调度
- [ ] 邮件通知服务
- [ ] 短信验证码服务
- [ ] 第三方登录集成
- [ ] API 文档自动生成
- [ ] 数据迁移工具

## 项目结构

```
.
├── config/               # 配置文件
│   ├── .env              # 环境变量
│   └── .env.sample       # 环境变量示例
├── database/             # 数据库相关文件
│   ├── migrate.php       # 数据库迁移脚本
│   └── migrations/       # 数据库迁移文件
├── docs/                 # 项目文档
├── logs/                 # 系统日志文件
├── scripts/              # 部署和维护脚本
├── src/                  # 核心代码
│   ├── Config/           # 配置类
│   │   ├── Config.php    # 配置管理
│   │   └── Routes.php    # 路由配置
│   ├── Controllers/      # 控制器
│   ├── DTO/              # 数据传输对象
│   ├── Entity/           # 实体类
│   ├── Exceptions/       # 自定义异常
│   ├── Http/             # HTTP相关组件
│   │   ├── Request.php   # HTTP请求处理
│   │   └── Response.php  # HTTP响应处理
│   ├── Interfaces/       # 接口定义
│   │   ├── MiddlewareInterface.php  # 中间件接口
│   │   └── ModelInterface.php       # 模型接口
│   ├── Logs/             # 日志处理
│   │   ├── Logger.php    # 日志记录
│   │   └── LogRotateService.php # 日志轮转服务
│   ├── Middlewares/      # 中间件
│   │   ├── MiddlewareStack.php      # 中间件栈
│   │   ├── TestConditionMiddleware.php # 测试条件中间件
│   │   └── TestFlowMiddleware.php   # 测试流程中间件
│   ├── Providers/        # 服务提供者
│   │   └── DatabaseServiceProvider.php # 数据库服务提供者
│   ├── Repository/       # 数据访问层
│   ├── Routes/           # 路由定义
│   │   ├── Route.php     # 路由类
│   │   └── Router.php    # 路由器
│   ├── Server/           # 服务器配置
│   │   └── ApiServer.php # API服务器
│   ├── Services/         # 业务逻辑
│   ├── Utils/            # 工具类
│   │   └── Container.php # 依赖注入容器
│   └── Validator/        # 数据验证
│       └── Validator.php # 验证器
├── tests/                # 测试代码
│   ├── http/             # HTTP API测试
│   │   ├── middleware-api.http # 中间件API测试
│   │   └── user-api.http       # 用户API测试
│   └── Validator/        # 验证器测试
│       └── ValidatorTest.php
└── README.md             # 项目说明
```

## 各目录用途

- **config/**: 存放项目配置文件，包含环境变量和配置类
- **docs/**: 项目相关文档
- **scripts/**: 部署脚本、维护脚本等
- **src/**: 项目核心代码
  - **DTO/**: 数据传输对象，用于层间数据传输
  - **Models/**: 数据模型，定义数据结构和业务实体
  - **Repositories/**: 数据访问层，负责与数据库交互
  - **Services/**: 业务逻辑层，处理核心业务
  - **Controllers/**: 控制器，处理HTTP请求
  - **Middlewares/**: 中间件，处理请求预处理和响应后处理
  - **Routes/**: 路由定义，映射URL到控制器
  - **Utils/**: 工具类，提供通用功能
  - **Logs/**: 日志处理
  - **Exceptions/**: 自定义异常处理
  - **Interfaces/**: 接口定义
  - **Http/**: HTTP相关组件，包含请求/响应处理、表单验证等
  - **Server/**: 服务器相关配置
- **tests/**: 单元测试和功能测试代码

## 代码检查与格式化

项目使用以下工具来保持代码质量和风格一致：

- **PHP_CodeSniffer**: 检查代码风格并检测常见错误
- **PHP-CS-Fixer**: 自动修复代码风格问题
- **PHPUnit**: 单元测试框架，用于功能测试和错误检测

注意：这些工具主要用于代码风格检查和格式化，虽然可以发现一些语法错误，但不能替代专业的静态代码分析工具。

### 工具安装

```bash
composer require --dev squizlabs/php_codesniffer friendsofphp/php-cs-fixer
```

### 使用

1. 检查代码风格：

```bash
./vendor/bin/phpcs
```

2. 自动修复代码风格：

```bash
./vendor/bin/phpcbf
```

3. 使用 PHP-CS-Fixer 格式化代码：

```bash
PHP_CS_FIXER_IGNORE_ENV=1 ./vendor/bin/php-cs-fixer fix
```

注意：当前 PHP 版本 (8.4.3) 高于 PHP-CS-Fixer 支持的最高版本 (8.3.\*)，需要设置 PHP_CS_FIXER_IGNORE_ENV 环境变量来忽略版本检查。

### 配置

- `phpcs.xml`: PHP_CodeSniffer 配置文件
- `.php-cs-fixer.php`: PHP-CS-Fixer 配置文件

## 贡献指南

我们欢迎任何形式的贡献！在开始贡献之前，请先阅读以下指南。

### 开发流程

1. Fork 项目仓库
2. 创建特性分支 (`git checkout -b feature/AmazingFeature`)
3. 提交更改 (`git commit -m 'Add some AmazingFeature'`)
4. 推送到分支 (`git push origin feature/AmazingFeature`)
5. 提交 Pull Request

### 代码风格

- 遵循 PSR-12 编码规范
- 使用 PHP_CodeSniffer 检查代码风格
- 使用 PHP-CS-Fixer 自动格式化代码
- 类名使用大驼峰式命名 (UpperCamelCase)
- 方法名使用小驼峰式命名 (lowerCamelCase)
- 常量名使用全大写加下划线 (UPPER_CASE)
- 变量名使用小驼峰式命名 (lowerCamelCase)

### 测试要求

- 所有新功能必须包含单元测试
- 修复 bug 时必须添加回归测试
- 测试覆盖率应保持在 80% 以上
- 使用 `.http` 文件进行 API 测试
- 推荐使用以下工具：
  - **REST Client** (VSCode 插件)
    - 安装：在 VSCode 扩展商店搜索 "REST Client" 并安装
    - 使用：直接打开 `.http` 文件，点击 "Send Request" 按钮即可运行测试
  - **Postman**
    - 导入 `.http` 文件进行测试
    - 支持更复杂的测试场景和自动化测试

### 运行测试

```bash
# 运行所有测试
./vendor/bin/phpunit

# 运行指定测试文件
./vendor/bin/phpunit tests/Validator/ValidatorTest.php
```

### Pull Request 规范

- 标题格式：[类型] 简短描述
  - 示例：[Feature] 添加用户认证功能
  - 类型包括：Feature, Bugfix, Refactor, Docs, Style, Test
- 描述部分需包含：
  - 解决的问题或实现的功能
  - 测试结果
  - 相关 issue 编号（如果有）
  - 重大变更说明（如果有）
- 确保所有测试通过
- 确保代码风格符合规范
- 确保文档及时更新

## 安装与配置

### 环境要求

- PHP 8.4.3 或更高版本
- Composer 2.0 或更高版本
- Swoole 6.0.0 或更高版本
- MySQL 8.0 或更高版本
- Redis 6.0 或更高版本（可选）

### 安装步骤

1. 克隆项目：

```bash
git clone https://github.com/fwx5618177/rtp.git
cd rtp
```

2. 安装依赖：

```bash
composer install
```

3. 安装 Swoole 扩展：

```bash
pecl install swoole
```

注意：Swoole 需要 PHP 启用线程安全（ZTS）模式编译。如果遇到安装问题，请确保：

- PHP 使用 --enable-zts 参数编译
- 检查 php -i | grep Thread 输出包含 "Thread Safety => enabled"
- 如果使用 brew 安装的 PHP，建议使用 brew install php --with-zts 重新安装

4. 启用 Swoole 扩展：

```bash
echo "extension=swoole.so" >> $(php -i | grep "Loaded Configuration File" | awk '{print $5}')
```

5. 配置环境变量：

```bash
cp config/.env.sample config/.env
```

编辑 config/.env 文件，配置以下内容：

```env
# 应用配置
APP_ENV=local
APP_DEBUG=true
APP_KEY=base64:your_app_key

# 数据库配置
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=rtp
DB_USERNAME=root
DB_PASSWORD=

# Redis 配置（可选）
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=null

# Swoole 配置
SWOOLE_HOST=0.0.0.0
SWOOLE_PORT=9501
SWOOLE_WORKER_NUM=4
SWOOLE_TASK_WORKER_NUM=2
```

6. 生成应用密钥：

```bash
php artisan key:generate
```

7. 运行数据库迁移：

```bash
php database/migrate.php
```

8. 启动开发服务器：

```bash
php src/index.php
```

### 默认配置

- 监听地址：0.0.0.0
- 监听端口：9501
- 访问地址：http://localhost:9501

## 社区指南

- [行为准则](CODE_OF_CONDUCT.md) - 我们的社区行为标准
- [安全策略](SECURITY.md) - 如何报告安全问题

## 许可证

本项目采用 [MIT 许可证](LICENSE) 发布。

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
