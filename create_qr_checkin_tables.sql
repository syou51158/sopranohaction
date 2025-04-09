-- QRコードチェックイン機能のためのテーブル定義

-- ゲストテーブルにQRコードトークン列を追加
ALTER TABLE guests ADD COLUMN qr_code_token VARCHAR(255) COMMENT 'チェックイン用QRコードを生成するためのユニークトークン';
ALTER TABLE guests ADD COLUMN qr_code_generated DATETIME COMMENT 'QRコードが生成された日時';

-- チェックインテーブルを作成（ゲストの会場到着記録）
CREATE TABLE IF NOT EXISTS checkins (
  id INT AUTO_INCREMENT PRIMARY KEY,
  guest_id INT COMMENT 'ゲストID',
  checkin_time DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT 'チェックイン時間',
  checked_by VARCHAR(255) COMMENT 'チェックインを記録したスタッフ',
  notes TEXT COMMENT '備考',
  FOREIGN KEY (guest_id) REFERENCES guests(id) ON DELETE CASCADE
) COMMENT='ゲストのチェックイン記録';

-- QRコード生成・管理のための設定テーブル
CREATE TABLE IF NOT EXISTS qr_settings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  setting_key VARCHAR(255) NOT NULL UNIQUE COMMENT '設定キー',
  setting_value TEXT COMMENT '設定値',
  description TEXT COMMENT '説明'
) COMMENT='QRコード機能の設定';

-- 初期設定データの挿入
INSERT INTO qr_settings (setting_key, setting_value, description) VALUES
('qr_enabled', 'true', 'QRコードチェックイン機能の有効/無効'),
('qr_checkin_message', 'ようこそ、結婚式へ！チェックインありがとうございます。', 'チェックイン時に表示するメッセージ'),
('qr_design_color', '#4CAF50', 'QRコードのデザイン色'),
('qr_size', '200', 'QRコードのサイズ（ピクセル）'),
('qr_logo_enabled', 'true', 'QRコード中央にロゴを表示するかどうか');

-- 統計情報収集用のビュー作成
CREATE OR REPLACE VIEW checkin_stats AS
SELECT 
  DATE(c.checkin_time) AS checkin_date,
  HOUR(c.checkin_time) AS checkin_hour,
  COUNT(*) AS checkin_count,
  GROUP_CONCAT(g.group_name SEPARATOR ', ') AS groups_checked_in
FROM checkins c
JOIN guests g ON c.guest_id = g.id
GROUP BY DATE(c.checkin_time), HOUR(c.checkin_time)
ORDER BY checkin_date, checkin_hour; 