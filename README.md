# RTP Bridge Backend

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
