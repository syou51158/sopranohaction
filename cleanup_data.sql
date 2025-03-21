-- wedding_settingsテーブルは保持しつつ、他のシステム設定を削除
TRUNCATE TABLE system_settings;
TRUNCATE TABLE notification_settings;
TRUNCATE TABLE layout_settings;
TRUNCATE TABLE seating_settings;

-- ゲスト関連のデータを削除
TRUNCATE TABLE notification_logs;
DELETE FROM seat_assignments;
DELETE FROM guest_fusen;
DELETE FROM guest_details;
DELETE FROM companions;
DELETE FROM gifts;
DELETE FROM guestbook;
DELETE FROM responses;
DELETE FROM guests;

-- その他のコンテンツを削除
TRUNCATE TABLE fusen_types;
TRUNCATE TABLE faq;
TRUNCATE TABLE photo_gallery;
TRUNCATE TABLE video_gallery;
TRUNCATE TABLE schedule;
TRUNCATE TABLE seating_tables;
TRUNCATE TABLE travel_info;
TRUNCATE TABLE transportation;
TRUNCATE TABLE accommodation_bookings;
TRUNCATE TABLE accommodation_room_types;
TRUNCATE TABLE accommodations;
TRUNCATE TABLE remarks;

-- グループタイプを維持しつつデータを削除
-- DELETE FROM group_types;
