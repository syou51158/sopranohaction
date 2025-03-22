-- ホテルに関する情報を含むテーブル
-- =========================================

-- 旅行情報テーブル
DROP TABLE IF EXISTS `travel_info`;
CREATE TABLE `travel_info` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `type` enum('transportation','accommodation') NOT NULL,
  `display_order` int(11) DEFAULT 0,
  `is_visible` tinyint(1) DEFAULT 1,
  `image_filename` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 旅行情報のデータ
LOCK TABLES `travel_info` WRITE;
INSERT INTO `travel_info` VALUES 
(1,'🌸 ブライダル宿泊特別プランのご案内 🌸','お部屋タイプの特徴 🏨\r\n	•	虹館\r\n	•	シャワールームのみ（バスタブ無し）\r\n	•	展望露天風呂「棚湯」利用可能\r\n	•	レストラン『シーダパレス』利用\r\n	•	星館\r\n	•	シャワールームのみ（バスタブ無し）\r\n	•	展望露天風呂「棚湯」利用可能\r\n	•	レストラン『和ダイニング星』利用（先着順）\r\n	•	宙館\r\n	•	デラックス海以外はシャワールームのみ（バスタブ無し）\r\n	•	展望露天風呂「棚湯」利用可能\r\n	•	宿泊者専用「宙湯」利用可能\r\n	•	レストラン『TERACE&DINING SORA』または『シーダパレス』利用（先着順）\r\n\r\n⸻\r\n\r\n🌟特別サービス🌟\r\n	•	新郎新婦様には宙館デラックスルーム宿泊（1泊2食付き）をプレゼント！（※披露宴大人10名以上対象）\r\n\r\n⸻\r\n\r\nご宿泊に関するよくある質問 💬\r\n	•	チェックイン 15:00〜／チェックアウト 翌日11:00\r\n	•	Wi-Fi ロビー・客室で無料利用可\r\n	•	食料持ち込み 客室への持ち込みOK\r\n	•	キャンセル料 宿泊日の7日前から発生（14名までの場合）\r\n\r\n⸻\r\n\r\nぜひご旅行や結婚式へのご参加にご活用くださいませ🎉✨\r\nご予約はお早めにお願いいたします！','accommodation',1,1,'67db52d5399ec_1742426837.png','2025-03-19 23:27:17','2025-03-20 03:23:16'),
(2,'🌊 お部屋タイプ変更による追加料金について 🌊','各館で『海側のお部屋』など他のタイプへご変更をご希望の場合、下記の通り追加料金がかかります。（1名様あたり／1泊朝食付・税込）\r\n\r\n■ 虹館（基準：カジュアルダブルorツイン）\r\n	•	カジュアルロフト／スキップ（定員4名）\r\n1室3名利用の場合、表示価格より 1,000円引き\r\n1室4名利用の場合、表示価格より 2,000円引き\r\n\r\n■ 星館（基準：スタンダード洋室 山側）\r\n	•	スタンダード洋室 海側へ変更\r\n	•	2名利用の場合、表示価格に ＋3,000円\r\n	•	3名利用の場合、表示価格に ＋2,000円\r\n	•	4名利用の場合、表示価格に ＋1,000円\r\n	•	シングル利用の場合\r\n	•	山側スタンダード洋室を1名利用：表示価格の 1.5倍\r\n\r\n■ 宙館（基準：スタンダードツイン 山側）\r\n	•	プレミアムスタンダード海側へ変更\r\n	•	2名利用の場合、表示価格に ＋7,000円\r\n	•	3名利用の場合、表示価格に ＋6,000円\r\n	•	4名利用の場合、表示価格に ＋5,000円\r\n	•	デラックス海側へ変更\r\n	•	2名利用の場合、表示価格に ＋9,000円\r\n	•	3名利用の場合、表示価格に ＋5,000円\r\n	•	4名利用の場合、表示価格に ＋4,000円\r\n	•	5名利用の場合、表示価格に ＋1,000円\r\n	•	シングル利用の場合\r\n	•	山側スタンダードツインを1名利用：表示価格の 1.5倍\r\n\r\n\r\n\r\n⸻\r\n\r\nご予約の際はご希望のお部屋タイプをお知らせくださいませ✨','accommodation',2,1,'67db5392448f3_1742427026.png','2025-03-19 23:30:26','2025-03-20 03:23:08');
UNLOCK TABLES;

-- 宿泊施設テーブル
DROP TABLE IF EXISTS `accommodations`;
CREATE TABLE `accommodations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `type` enum('ホテル','旅館','その他') NOT NULL,
  `address` text DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `website` varchar(255) DEFAULT NULL,
  `price_range` varchar(50) DEFAULT NULL,
  `distance_to_venue` varchar(50) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `is_recommended` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 宿泊予約テーブル
DROP TABLE IF EXISTS `accommodation_bookings`;
CREATE TABLE `accommodation_bookings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `guest_id` int(11) DEFAULT NULL,
  `accommodation_name` varchar(100) NOT NULL,
  `room_type` varchar(50) DEFAULT NULL,
  `check_in` date DEFAULT NULL,
  `check_out` date DEFAULT NULL,
  `number_of_rooms` int(11) DEFAULT 1,
  `number_of_guests` int(11) DEFAULT 1,
  `booking_status` enum('予約済','仮予約','キャンセル') DEFAULT '予約済',
  `special_requests` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `guest_id` (`guest_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 部屋タイプテーブル
DROP TABLE IF EXISTS `accommodation_room_types`;
CREATE TABLE `accommodation_room_types` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `accommodation_id` int(11) DEFAULT NULL,
  `room_name` varchar(100) NOT NULL,
  `capacity` int(11) DEFAULT 2,
  `description` text DEFAULT NULL,
  `image_filename` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `accommodation_id` (`accommodation_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 会場情報（杉乃井ホテル）
DROP TABLE IF EXISTS `wedding_settings`;
CREATE TABLE `wedding_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(50) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `display_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

LOCK TABLES `wedding_settings` WRITE;
INSERT INTO `wedding_settings` VALUES 
(5,'venue_name','別府温泉　杉乃井ホテル 杉乃井パレス内　ウェディングホール１階 ザ・スイートテラス','会場名','結婚式の会場名を入力してください。','2025-03-16 00:12:54'),
(6,'venue_address','〒874-0823 大分県別府市観海寺１−２２７２','会場住所','会場の住所を入力してください。','2025-03-16 00:12:54'),
(7,'venue_map_url','<iframe src=\"https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d375.2281292569163!2d131.47269832960316!3d33.284478272277504!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x3546a65ce9501f11%3A0x89e5e19c6f202901!2z5p2J5LmD5LqV44OR44Os44K5!5e0!3m2!1sja!2sjp!4v1742082382882!5m2!1sja!2sjp\" width=\"600\" height=\"450\" style=\"border:0;\" allowfullscreen=\"\" loading=\"lazy\" referrerpolicy=\"no-referrer-when-downgrade\"></iframe>','会場のGoogleマップURL','GoogleマップのURLを入力してください。共有URLまたは埋め込みiframeのsrcに指定するURL。','2025-03-15 23:46:59'),
(8,'venue_map_link','https://maps.app.goo.gl/r6h7ZfrSyDLamj7R9','会場のGoogleマップリンク','スマートフォンなどで開くためのGoogleマップリンクを入力してください。','2025-03-15 23:46:59');
UNLOCK TABLES; -- ホテルに関連するギャラリー情報
-- =========================================

-- 写真ギャラリーテーブル
DROP TABLE IF EXISTS `photo_gallery`;
CREATE TABLE `photo_gallery` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `filename` varchar(255) NOT NULL,
  `original_filename` varchar(255) NOT NULL,
  `file_size` int(11) NOT NULL,
  `file_type` varchar(100) NOT NULL,
  `uploaded_by` varchar(100) DEFAULT NULL,
  `is_approved` tinyint(1) DEFAULT 0,
  `display_order` int(11) NOT NULL DEFAULT 0,
  `upload_date` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 写真ギャラリーのデータ
LOCK TABLES `photo_gallery` WRITE;
INSERT INTO `photo_gallery` VALUES 
(1,'ああ','ああ','67db3dc2df539_IMG6799.jpg','IMG_6799.jpg',2779312,'image/jpeg','村岡翔',1,0,'2025-03-19 21:57:22'),
(2,'い','い','67db3e185f304_IMG3477.jpg','IMG_3477.jpg',368828,'image/jpeg','村岡翔',1,0,'2025-03-19 21:58:48'),
(4,'う','う','67db3e3b54dfd_IMG5046.jpg','IMG_5046.jpg',265711,'image/jpeg','村岡翔',1,0,'2025-03-19 21:59:23');
UNLOCK TABLES;

-- 動画ギャラリーテーブル
DROP TABLE IF EXISTS `video_gallery`;
CREATE TABLE `video_gallery` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `filename` varchar(255) NOT NULL,
  `original_filename` varchar(255) NOT NULL,
  `file_size` int(11) NOT NULL,
  `file_type` varchar(100) NOT NULL,
  `upload_date` datetime NOT NULL DEFAULT current_timestamp(),
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `is_main_video` tinyint(1) NOT NULL DEFAULT 0,
  `thumbnail` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 動画ギャラリーのデータ
LOCK TABLES `video_gallery` WRITE;
INSERT INTO `video_gallery` VALUES 
(1,'え','え','1742421862_409f21ca82a24bef9fe10ccc742165d1.MOV','409f21ca82a24bef9fe10ccc742165d1.MOV',19552269,'video/quicktime','2025-03-20 07:04:22',1,0,'thumb_1742421862_2025-03-1919.27.17.png'),
(2,'い','い','1742421908_409f21ca82a24bef9fe10ccc742165d1.MOV','409f21ca82a24bef9fe10ccc742165d1.MOV',19552269,'video/quicktime','2025-03-20 07:05:08',1,0,NULL),
(3,'サムネ','サムネ','1742448933_00846E89-DE57-4EE5-909E-15708A19B6BC.mov','00846E89-DE57-4EE5-909E-15708A19B6BC.mov',34509710,'video/quicktime','2025-03-20 14:35:33',1,1,'thumb_1742448933_2025-03-1316.29.30.png');
UNLOCK TABLES; 