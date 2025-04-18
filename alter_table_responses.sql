-- responsesテーブルに郵便番号と住所のカラムを追加
ALTER TABLE responses
ADD COLUMN postal_code VARCHAR(8) DEFAULT NULL AFTER email,
ADD COLUMN address TEXT DEFAULT NULL AFTER postal_code;

-- 同伴者テーブルの変更は必要ありません 