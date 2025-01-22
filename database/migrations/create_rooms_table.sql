DROP TABLE IF EXISTS rooms;
CREATE TABLE rooms (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `uuid` varchar(36) NOT NULL COMMENT 'UUID',
    `room_id` varchar(36) NOT NULL COMMENT '房间ID',
    `room_name` varchar(255) NOT NULL COMMENT '房间名称',
    `config` json DEFAULT NULL COMMENT '房间配置',
    `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` datetime DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_uuid` (`uuid`),
    UNIQUE KEY `uk_room_id` (`room_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci; 