# RTP Audio Bridge System Usage Guide / RTP 音频桥接系统使用指南

## Framework Introduction / 框架介绍

This project uses a custom PHP framework developed by the author, featuring:

- MVC architecture with dependency injection
- Middleware support for request processing
- WebSocket server integration
- Database abstraction layer with connection pooling
- RESTful API routing system
- Custom validation system
- Logging and log rotation
- Configuration management

Refer to [architecture.md](docs/architecture.md) for detailed framework documentation.

## Environment Setup / 环境准备

### 1. Install Swoole Extension / 安装 Swoole 扩展

```bash
pecl install swoole
```

### 2. Install Node.js 20.10.0 / 安装 Node.js 20.10.0

Recommended to use nvm for Node.js version management:

```bash
nvm install 20.10.0
nvm use 20.10.0
```

### 3. Install pnpm / 安装 pnpm

```bash
npm install -g pnpm
```

## Service Startup / 启动服务

### 1. Start Backend Service / 启动后端服务

```bash
php src/index.php
```

### 2. Start Frontend Service / 启动前端服务

```bash
cd sample/janus-audio-bridge
pnpm install
pnpm dev
```

## Testing / 测试方法

1. Open two browser tabs and access frontend service (default: http://localhost:5173)
2. Enter different usernames in each tab and join the same room
3. Verify audio transmission:
   - Ensure microphone permission is granted
   - Check console logs for errors
   - Use developer tools to inspect WebRTC connection status

## Notes / 注意事项

1. Ensure Janus gateway service is running (default port: 8088)
2. Check CORS configuration if encountering cross-origin issues
3. Recommended to use Chrome browser for testing
4. Ensure system audio input/output devices are working properly
