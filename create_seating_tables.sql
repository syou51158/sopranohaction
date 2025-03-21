-- テーブル情報を管理するテーブル
CREATE TABLE IF NOT EXISTS `seating_tables` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `table_name` varchar(50) NOT NULL,
  `capacity` int(11) NOT NULL DEFAULT 8,
  `position_x` int(11) DEFAULT NULL,
  `position_y` int(11) DEFAULT NULL,
  `table_type` ENUM('regular', 'special', 'bridal') DEFAULT 'regular',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `table_name` (`table_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 座席割り当てを管理するテーブル
CREATE TABLE IF NOT EXISTS `seat_assignments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `table_id` int(11) NOT NULL,
  `seat_number` int(11) NOT NULL,
  `response_id` int(11) DEFAULT NULL,
  `companion_id` int(11) DEFAULT NULL,
  `is_companion` tinyint(1) NOT NULL DEFAULT 0,
  `layer_text` varchar(50) DEFAULT NULL COMMENT '層書き',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `table_seat` (`table_id`,`seat_number`),
  KEY `response_id` (`response_id`),
  KEY `companion_id` (`companion_id`),
  CONSTRAINT `seat_assignments_ibfk_1` FOREIGN KEY (`table_id`) REFERENCES `seating_tables` (`id`) ON DELETE CASCADE,
  CONSTRAINT `seat_assignments_ibfk_2` FOREIGN KEY (`response_id`) REFERENCES `responses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `seat_assignments_ibfk_3` FOREIGN KEY (`companion_id`) REFERENCES `companions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 初期データの挿入（テーブル情報）
INSERT INTO `seating_tables` (`table_name`, `capacity`, `table_type`) VALUES
('新郎', 8, 'special'),
('新婦', 8, 'special'),
('A', 8, 'regular'),
('B', 8, 'regular'),
('C', 8, 'regular'),
('D', 8, 'regular'),
('F', 8, 'regular'),
('G', 8, 'regular'),
('H', 8, 'regular'),
('I', 8, 'regular'),
('J', 10, 'regular'),
('K', 10, 'regular'),
('L', 10, 'regular'); 