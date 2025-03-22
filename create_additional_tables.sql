-- 席次表テーブル
CREATE TABLE IF NOT EXISTS seating (
  id INT AUTO_INCREMENT PRIMARY KEY,
  table_number INT NOT NULL,
  table_name VARCHAR(100),
  capacity INT NOT NULL DEFAULT 8,
  notes TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 席割り当てテーブル
CREATE TABLE IF NOT EXISTS seat_assignments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  response_id INT NOT NULL,
  table_id INT NOT NULL,
  seat_number INT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (response_id) REFERENCES responses(id) ON DELETE CASCADE,
  FOREIGN KEY (table_id) REFERENCES seating(id) ON DELETE CASCADE
);

-- スケジュールテーブル
CREATE TABLE IF NOT EXISTS schedule (
  id INT AUTO_INCREMENT PRIMARY KEY,
  event_time TIME NOT NULL,
  event_name VARCHAR(100) NOT NULL,
  event_description TEXT,
  for_group_type VARCHAR(50), -- 特定のグループタイプ向けのイベント
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- グループタイプテーブル (家族、友人、同僚など)
CREATE TABLE IF NOT EXISTS group_types (
  id INT AUTO_INCREMENT PRIMARY KEY,
  type_name VARCHAR(50) NOT NULL,
  description TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ゲスト情報拡張テーブル
CREATE TABLE IF NOT EXISTS guest_details (
  id INT AUTO_INCREMENT PRIMARY KEY,
  response_id INT NOT NULL,
  age_group ENUM('大人', '子供', '幼児'),
  dietary_restrictions TEXT,
  allergies TEXT,
  special_needs TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (response_id) REFERENCES responses(id) ON DELETE CASCADE
);

-- ギフト記録テーブル
CREATE TABLE IF NOT EXISTS gifts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  guest_id INT,
  gift_type ENUM('現金', 'プレゼント', 'その他'),
  amount DECIMAL(10,2), -- 金額（ご祝儀の場合）
  description TEXT,
  received_date DATE,
  thank_you_sent BOOLEAN DEFAULT FALSE,
  notes TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (guest_id) REFERENCES guests(id) ON DELETE SET NULL
);

-- 写真ギャラリーテーブル
CREATE TABLE IF NOT EXISTS photo_gallery (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(100) NOT NULL,
  description TEXT,
  filename VARCHAR(255) NOT NULL,
  uploaded_by VARCHAR(100),
  is_approved TINYINT(1) DEFAULT 0,
  upload_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Q&Aテーブル
CREATE TABLE IF NOT EXISTS faq (
  id INT AUTO_INCREMENT PRIMARY KEY,
  question TEXT NOT NULL,
  answer TEXT NOT NULL,
  category VARCHAR(50),
  display_order INT DEFAULT 0,
  is_visible TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 交通・宿泊情報テーブル
CREATE TABLE IF NOT EXISTS accommodations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  type ENUM('ホテル', '旅館', 'その他') NOT NULL,
  address TEXT,
  phone VARCHAR(20),
  website VARCHAR(255),
  price_range VARCHAR(50),
  distance_to_venue VARCHAR(50),
  description TEXT,
  is_recommended TINYINT(1) DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 交通手段テーブル
CREATE TABLE IF NOT EXISTS transportation (
  id INT AUTO_INCREMENT PRIMARY KEY,
  type ENUM('電車', 'バス', '車', 'タクシー', 'その他') NOT NULL,
  from_location VARCHAR(100) NOT NULL,
  to_location VARCHAR(100) NOT NULL,
  duration VARCHAR(50),
  cost VARCHAR(50),
  schedule TEXT,
  notes TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 通知設定テーブル
CREATE TABLE IF NOT EXISTS notification_settings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  event_type VARCHAR(50) NOT NULL,
  is_email_enabled TINYINT(1) DEFAULT 1,
  email_template TEXT,
  days_before INT, -- リマインダーの場合、何日前に送信するか
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ゲストに送信された通知の記録テーブル
CREATE TABLE IF NOT EXISTS notification_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  guest_id INT,
  notification_type VARCHAR(50) NOT NULL,
  sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  status VARCHAR(20) DEFAULT 'sent',
  error_message TEXT,
  FOREIGN KEY (guest_id) REFERENCES guests(id) ON DELETE SET NULL
);

-- 関連付けテーブル（ゲストグループとグループタイプの関連付け）
ALTER TABLE guests ADD COLUMN group_type_id INT;
ALTER TABLE guests ADD FOREIGN KEY (group_type_id) REFERENCES group_types(id) ON DELETE SET NULL; 