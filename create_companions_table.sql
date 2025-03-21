-- 同伴者情報テーブル
CREATE TABLE IF NOT EXISTS companions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  response_id INT NOT NULL,
  name VARCHAR(100) NOT NULL,
  age_group ENUM('adult', 'child', 'infant') DEFAULT 'adult',
  dietary TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (response_id),
  CONSTRAINT companions_ibfk_1 FOREIGN KEY (response_id) REFERENCES responses(id) ON DELETE CASCADE
); 