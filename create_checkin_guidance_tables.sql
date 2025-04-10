-- 席次案内テーブル作成
CREATE TABLE IF NOT EXISTS seating_guidance (
  id INT AUTO_INCREMENT PRIMARY KEY,
  guest_id INT NOT NULL,
  table_id INT,
  seat_number INT,
  custom_message TEXT,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (guest_id) REFERENCES guests(id) ON DELETE CASCADE,
  FOREIGN KEY (table_id) REFERENCES seating_tables(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- イベント案内テーブル作成
CREATE TABLE IF NOT EXISTS event_notices (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(100) NOT NULL,
  content TEXT NOT NULL,
  priority INT DEFAULT 0,
  active TINYINT(1) DEFAULT 1,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 会場マップテーブル作成
CREATE TABLE IF NOT EXISTS venue_maps (
  id INT AUTO_INCREMENT PRIMARY KEY, 
  name VARCHAR(100) NOT NULL,
  description TEXT,
  image_path VARCHAR(255),
  is_default TINYINT(1) DEFAULT 0,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- イベントスケジュールテーブル作成
CREATE TABLE IF NOT EXISTS event_schedule (
  id INT AUTO_INCREMENT PRIMARY KEY,
  event_name VARCHAR(100) NOT NULL,
  event_time DATETIME NOT NULL,
  description TEXT,
  location VARCHAR(100),
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- サンプルデータの挿入
INSERT INTO event_notices (title, content, priority, active) VALUES
('重要なご案内', '結婚式会場には駐車場のご用意がございません。公共交通機関をご利用ください。', 100, 1),
('写真撮影について', '挙式中の写真撮影はご遠慮ください。専属カメラマンが撮影しております。', 50, 1),
('お子様連れの方へ', 'お子様用の食事やアメニティをご用意しております。受付にてお申し付けください。', 30, 1);

-- サンプルスケジュール
INSERT INTO event_schedule (event_name, event_time, description, location) VALUES
('受付開始', '2024-04-20 12:00:00', 'ご到着されましたらまず受付をお済ませください', '1階エントランス'),
('挙式', '2024-04-20 13:00:00', '13時00分より挙式を執り行います', 'チャペル'),
('写真撮影', '2024-04-20 14:00:00', '集合写真の撮影を行います', 'ガーデン'),
('披露宴', '2024-04-20 14:30:00', '披露宴の開始時間です', '2階宴会場'); 