-- ゲスト情報テーブル
CREATE TABLE IF NOT EXISTS guests (
  id INT AUTO_INCREMENT PRIMARY KEY,
  group_id VARCHAR(50) UNIQUE,
  group_name VARCHAR(100) NOT NULL,
  email VARCHAR(255),
  arrival_time VARCHAR(100) NOT NULL,
  custom_message TEXT,
  max_companions INT DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 出欠回答テーブル
CREATE TABLE IF NOT EXISTS responses (
  id INT AUTO_INCREMENT PRIMARY KEY,
  guest_id INT NULL,
  name VARCHAR(100) NOT NULL,
  attending BOOLEAN NOT NULL,
  companions INT DEFAULT 0,
  message TEXT,
  dietary TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (guest_id),
  CONSTRAINT responses_ibfk_1 FOREIGN KEY (guest_id) REFERENCES guests(id) ON DELETE SET NULL
);

-- サンプルゲストデータ（必要に応じて変更してください）
INSERT INTO guests (group_id, group_name, email, arrival_time, max_companions, custom_message) 
VALUES 
('family001', '村岡家', 'family@example.com', '12:30', 4, 'ご両親と一緒にお待ちしております。'),
('friends001', '大学の友人', 'friend@example.com', '12:45', 2, '懐かしい顔ぶれに会えるのを楽しみにしています。'),
('work001', '会社の同僚', 'work@example.com', '13:00', 1, '当日はよろしくお願いします。'); 