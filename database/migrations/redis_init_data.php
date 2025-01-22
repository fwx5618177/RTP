<?php

/**
 * Redis 初始化数据
 * 
 * 这个文件定义了需要在 Redis 中初始化的数据结构
 * 支持不同类型的数据：字符串、哈希、列表等
 * 可以设置 TTL（过期时间，单位：秒）
 */
return [
    // 系统配置示例
    'config:system' => [
        'type' => 'hash',
        'value' => [
            'maintenance_mode' => false,
            'api_rate_limit' => 100,
            'cache_ttl' => 3600
        ]
    ],

    // 用户会话示例
    'session:template' => [
        'value' => [
            'user_id' => null,
            'login_time' => null,
            'ip_address' => null,
            'user_agent' => null
        ],
        'ttl' => 86400 // 24小时
    ],

    // API 限流计数器示例
    'rate_limit:template' => [
        'value' => 0,
        'ttl' => 60 // 1分钟
    ],

    // 缓存键前缀示例
    'cache:user:template' => [
        'value' => [
            'id' => null,
            'username' => null,
            'email' => null,
            'roles' => []
        ],
        'ttl' => 3600 // 1小时
    ],

    // 在线用户计数示例
    'stats:online_users' => [
        'value' => 0
    ],

    // 黑名单 IP 示例
    'blacklist:ips' => [
        'type' => 'set',
        'value' => [
            '192.168.1.1',
            '10.0.0.1'
        ]
    ],

    // 房间信息缓存模板
    'cache:room:template' => [
        'value' => [
            'id' => null,
            'room_id' => null,
            'room_name' => null,
            'config' => null,
            'is_active' => true
        ],
        'ttl' => 3600 // 1小时
    ],

    // 活跃房间集合
    'set:active_rooms' => [
        'type' => 'set',
        'value' => []
    ],

    // 房间在线用户计数
    'counter:room:users:template' => [
        'value' => 0,
        'ttl' => 86400 // 24小时
    ],

    // 房间消息队列模板
    'queue:room:messages:template' => [
        'type' => 'list',
        'value' => [],
        'ttl' => 86400 // 24小时
    ]
];
