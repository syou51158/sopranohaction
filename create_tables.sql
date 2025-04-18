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
  email VARCHAR(255) NOT NULL,
  postal_code VARCHAR(10),
  address TEXT,
  attending BOOLEAN NOT NULL,
  companions INT DEFAULT 0,
  message TEXT,
  dietary TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (guest_id),
  CONSTRAINT responses_ibfk_1 FOREIGN KEY (guest_id) REFERENCES guests(id) ON DELETE SET NULL
); 