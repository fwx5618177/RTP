DROP TABLE IF EXISTS rooms;
CREATE TABLE rooms (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `uuid` varchar(36) NOT NULL COMMENT 'UUID',
    `room_id` varchar(36) NOT NULL COMMENT '房间ID',
    `room_name` varchar(255) NOT NULL COMMENT '房间名称',
    `creator_id` varchar(36) NOT NULL COMMENT '创建者ID',
    `max_participants` int(11) NOT NULL DEFAULT 10 COMMENT '最大参与人数',
    `janus_session_id` varchar(36) NOT NULL COMMENT 'Janus会话ID',
    `janus_handle_id` varchar(36) NOT NULL COMMENT 'Janus句柄ID',
    `config` JSON NULL COMMENT '房间配置',
    `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` datetime DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_uuid` (`uuid`),
    UNIQUE KEY `uk_room_id` (`room_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci; 