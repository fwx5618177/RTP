# RTP Bridge Backend

## 项目结构

```
.
├── config/               # 配置文件
│   ├── .env              # 环境变量
│   └── .env.sample       # 环境变量示例
├── docs/                 # 项目文档
├── migrations/           # 数据库迁移文件
├── scripts/              # 部署和维护脚本
├── src/                  # 核心代码
│   ├── Config/           # 配置类
│   ├── Controllers/      # 控制器
│   ├── DTO/              # 数据传输对象
│   ├── Exceptions/       # 自定义异常
│   ├── Interfaces/       # 接口定义
│   ├── Logs/             # 日志处理
│   ├── Middlewares/      # 中间件
│   ├── Models/           # 数据模型
│   ├── Repositories/     # 数据访问层
│   ├── Routes/           # 路由定义
│   ├── Http/             # HTTP相关组件
│   ├── Server/           # 服务器配置
│   ├── Services/         # 业务逻辑
│   └── Utils/            # 工具类
├── tests/                # 测试代码
└── README.md             # 项目说明
```

## 各目录用途

- **config/**: 存放项目配置文件，包含环境变量和配置类
- **docs/**: 项目相关文档
- **migrations/**: 数据库迁移文件，用于管理数据库结构变更
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
