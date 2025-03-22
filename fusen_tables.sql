-- 付箋マスターテーブル（付箋の種類を定義）
CREATE TABLE IF NOT EXISTS fusen_types (
  id INT AUTO_INCREMENT PRIMARY KEY,
  type_code VARCHAR(50) NOT NULL UNIQUE,
  type_name VARCHAR(100) NOT NULL,
  description TEXT,
  default_message TEXT,
  sort_order INT DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 各グループに割り当てられた付箋テーブル
CREATE TABLE IF NOT EXISTS guest_fusen (
  id INT AUTO_INCREMENT PRIMARY KEY,
  guest_id INT NOT NULL,
  fusen_type_id INT NOT NULL,
  custom_message TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (guest_id),
  INDEX (fusen_type_id),
  CONSTRAINT guest_fusen_ibfk_1 FOREIGN KEY (guest_id) REFERENCES guests(id) ON DELETE CASCADE,
  CONSTRAINT guest_fusen_ibfk_2 FOREIGN KEY (fusen_type_id) REFERENCES fusen_types(id) ON DELETE CASCADE
);

-- 初期データの挿入
INSERT INTO fusen_types (type_code, type_name, description, default_message, sort_order) VALUES
('ceremony', '挙式付箋', '挙式からご列席頂きたい方へ入れる付箋です', '挙式からご列席いただきますようお願い申し上げます。\n挙式の30分前（12:30）までにお集まりください。', 10),
('family_photo', '親族写真付箋', '集合写真に入って頂きたい親族さまへ入れる付箋です', '親族集合写真の撮影を行います。\n挙式の1時間前（12:00）までにお集まりください。', 20),
('speech', '祝辞付箋', '主賓挨拶をお願いする方へ入れる付箋です', '謹んで祝辞のご発声をお願い申し上げます。\n披露宴担当の者がご案内いたします。', 30),
('performance', '余興付箋', '余興をお願いする方へ入れる付箋です', '披露宴での余興のご協力をお願い申し上げます。\n披露宴担当の者がご案内いたします。', 40),
('toast', '乾杯付箋', '乾杯のご発声をお願いする方へ入れる付箋です', '謹んで乾杯のご発声をお願い申し上げます。\n披露宴担当の者がご案内いたします。', 50),
('reception', '受付付箋', '受付をお願いする方へ入れる付箋です', '受付のお手伝いをお願い申し上げます。\n当日12:00までに会場にお越しください。', 60); 