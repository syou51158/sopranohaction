-- 交通・宿泊情報テーブル
CREATE TABLE IF NOT EXISTS travel_info (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(100) NOT NULL,
  description TEXT,
  type ENUM('transportation', 'accommodation') NOT NULL,
  display_order INT DEFAULT 0,
  is_visible TINYINT(1) DEFAULT 1,
  image_filename VARCHAR(255),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- 宿泊予約管理テーブル
CREATE TABLE IF NOT EXISTS accommodation_bookings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  guest_id INT,
  accommodation_name VARCHAR(100) NOT NULL,
  room_type VARCHAR(50),
  check_in DATE,
  check_out DATE,
  number_of_rooms INT DEFAULT 1,
  number_of_guests INT DEFAULT 1,
  booking_status ENUM('予約済', '仮予約', 'キャンセル') DEFAULT '予約済',
  special_requests TEXT,
  notes TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (guest_id) REFERENCES guests(id) ON DELETE SET NULL
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- 宿泊部屋タイプテーブル
CREATE TABLE IF NOT EXISTS accommodation_room_types (
  id INT AUTO_INCREMENT PRIMARY KEY,
  accommodation_id INT,
  room_name VARCHAR(100) NOT NULL,
  capacity INT DEFAULT 2,
  description TEXT,
  image_filename VARCHAR(255),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (accommodation_id) REFERENCES travel_info(id) ON DELETE CASCADE
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; 