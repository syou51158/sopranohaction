-- MariaDB dump 10.19  Distrib 10.4.28-MariaDB, for osx10.10 (x86_64)
--
-- Host: 127.0.0.1    Database: wedding
-- ------------------------------------------------------
-- Server version	10.4.28-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `accommodation_bookings`
--

DROP TABLE IF EXISTS `accommodation_bookings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
  KEY `guest_id` (`guest_id`),
  CONSTRAINT `accommodation_bookings_ibfk_1` FOREIGN KEY (`guest_id`) REFERENCES `guests` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `accommodation_bookings`
--

LOCK TABLES `accommodation_bookings` WRITE;
/*!40000 ALTER TABLE `accommodation_bookings` DISABLE KEYS */;
/*!40000 ALTER TABLE `accommodation_bookings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `accommodation_room_types`
--

DROP TABLE IF EXISTS `accommodation_room_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `accommodation_room_types` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `accommodation_id` int(11) DEFAULT NULL,
  `room_name` varchar(100) NOT NULL,
  `capacity` int(11) DEFAULT 2,
  `description` text DEFAULT NULL,
  `image_filename` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `accommodation_id` (`accommodation_id`),
  CONSTRAINT `accommodation_room_types_ibfk_1` FOREIGN KEY (`accommodation_id`) REFERENCES `travel_info` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `accommodation_room_types`
--

LOCK TABLES `accommodation_room_types` WRITE;
/*!40000 ALTER TABLE `accommodation_room_types` DISABLE KEYS */;
/*!40000 ALTER TABLE `accommodation_room_types` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `accommodations`
--

DROP TABLE IF EXISTS `accommodations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `accommodations`
--

LOCK TABLES `accommodations` WRITE;
/*!40000 ALTER TABLE `accommodations` DISABLE KEYS */;
/*!40000 ALTER TABLE `accommodations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `admin_users`
--

DROP TABLE IF EXISTS `admin_users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `admin_users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `is_active` tinyint(1) DEFAULT 0,
  `verification_token` varchar(100) DEFAULT NULL,
  `token_expiry` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_login` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admin_users`
--

LOCK TABLES `admin_users` WRITE;
/*!40000 ALTER TABLE `admin_users` DISABLE KEYS */;
INSERT INTO `admin_users` VALUES (3,'村岡翔','syo.t.company@gmail.com','$2y$10$Qh0e4dfQpel1CSPtTIIZyeAuoBVzwW.yX0VqWA1tbInN8RBqEX7mC',1,NULL,NULL,'2025-03-15 22:35:36','2025-03-20 06:17:08');
/*!40000 ALTER TABLE `admin_users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `companions`
--

DROP TABLE IF EXISTS `companions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `companions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `response_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `age_group` enum('adult','child','infant') DEFAULT 'adult',
  `dietary` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `response_id` (`response_id`),
  CONSTRAINT `companions_ibfk_1` FOREIGN KEY (`response_id`) REFERENCES `responses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `companions`
--

LOCK TABLES `companions` WRITE;
/*!40000 ALTER TABLE `companions` DISABLE KEYS */;
INSERT INTO `companions` VALUES (1,21,'りょうくん','adult','卵','2025-03-21 04:02:30'),(2,21,'奈緒くん','infant','えびかに','2025-03-21 04:02:30'),(3,21,'あかね','infant','生卵','2025-03-21 04:02:30');
/*!40000 ALTER TABLE `companions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `faq`
--

DROP TABLE IF EXISTS `faq`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `faq` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `question` text NOT NULL,
  `answer` text NOT NULL,
  `category` varchar(50) DEFAULT NULL,
  `display_order` int(11) DEFAULT 0,
  `is_visible` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `faq`
--

LOCK TABLES `faq` WRITE;
/*!40000 ALTER TABLE `faq` DISABLE KEYS */;
INSERT INTO `faq` VALUES (1,'ドレスコードはありますか？','カジュアルスマートが基本ですが、特に厳しい規定はありません。ご自身が動きやすく、お祝いの気持ちが伝わる服装でお越しください。','general',1,1,'2025-03-15 11:41:28'),(2,'子供を連れて行っても大丈夫ですか？','はい、もちろん大丈夫です。お子様用の席や食事も用意いたします。事前にご連絡いただけると助かります。','general',2,1,'2025-03-15 11:41:28'),(3,'アレルギーがある場合はどうすればいいですか？','お申し込みフォームの「アレルギー・食事制限等」欄にご記入ください。可能な限り対応いたします。','food',3,1,'2025-03-15 11:41:28'),(4,'交通アクセスについて詳しく教えてください','最寄り駅からの送迎バスを用意する予定です。また、車でお越しの方は会場に駐車場がございます。詳細は「交通・宿泊情報」ページをご確認ください。','location',4,1,'2025-03-15 11:41:28'),(5,'ご祝儀の相場はいくらぐらいですか？','ご祝儀の金額に決まりはございません。お気持ちで結構です。一般的には3万円から5万円程度といわれていますが、ご無理のない範囲でお願いいたします。','gift',5,1,'2025-03-15 11:41:28'),(6,'迷ったらどうしたらいいですか？','杉乃井ホテルの受付、ブライダルサロンまでご連絡お願いいたします。担当は田島です。090-1234-5678','location',1,1,'2025-03-16 10:39:13');
/*!40000 ALTER TABLE `faq` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `fusen_types`
--

DROP TABLE IF EXISTS `fusen_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `fusen_types` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `type_code` varchar(50) NOT NULL,
  `type_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `default_message` text DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `type_code` (`type_code`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `fusen_types`
--

LOCK TABLES `fusen_types` WRITE;
/*!40000 ALTER TABLE `fusen_types` DISABLE KEYS */;
INSERT INTO `fusen_types` VALUES (1,'ceremony','挙式付箋','挙式からご列席頂きたい方へ入れる付箋です','挙式からご列席いただきますようお願い申し上げます。\n挙式の30分前（12:30）までにお集まりください。',10,'2025-03-19 01:37:52'),(2,'family_photo','親族写真付箋','集合写真に入って頂きたい親族さまへ入れる付箋です','親族集合写真の撮影を行います。\n挙式の1時間前（12:00）までにお集まりください。',20,'2025-03-19 01:37:52'),(3,'speech','祝辞付箋','主賓挨拶をお願いする方へ入れる付箋です','謹んで祝辞のご発声をお願い申し上げます。\n披露宴担当の者がご案内いたします。',30,'2025-03-19 01:37:52'),(4,'performance','余興付箋','余興をお願いする方へ入れる付箋です','披露宴での余興のご協力をお願い申し上げます。\n披露宴担当の者がご案内いたします。',40,'2025-03-19 01:37:52'),(5,'toast','乾杯付箋','乾杯のご発声をお願いする方へ入れる付箋です','謹んで乾杯のご発声をお願い申し上げます。\n披露宴担当の者がご案内いたします。',50,'2025-03-19 01:37:52'),(6,'reception','受付付箋','受付をお願いする方へ入れる付箋です','受付のお手伝いをお願い申し上げます。\n当日12:00までに会場にお越しください。',60,'2025-03-19 01:37:52');
/*!40000 ALTER TABLE `fusen_types` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `gifts`
--

DROP TABLE IF EXISTS `gifts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `gifts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `guest_id` int(11) DEFAULT NULL,
  `gift_type` enum('cash','present','other') DEFAULT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `received_date` date DEFAULT NULL,
  `thank_you_sent` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `guest_id` (`guest_id`),
  CONSTRAINT `gifts_ibfk_1` FOREIGN KEY (`guest_id`) REFERENCES `guests` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `gifts`
--

LOCK TABLES `gifts` WRITE;
/*!40000 ALTER TABLE `gifts` DISABLE KEYS */;
/*!40000 ALTER TABLE `gifts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `group_types`
--

DROP TABLE IF EXISTS `group_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `group_types` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `type_name` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `group_types`
--

LOCK TABLES `group_types` WRITE;
/*!40000 ALTER TABLE `group_types` DISABLE KEYS */;
INSERT INTO `group_types` VALUES (1,'新郎家族','新郎の家族','2025-03-16 00:41:22'),(2,'新郎側友人','新郎の友人たち','2025-03-16 06:50:30'),(3,'新婦側友人','新婦の友人たち','2025-03-16 06:50:30'),(4,'新郎側親族','新郎の家族や親族','2025-03-16 06:50:30'),(5,'新婦側親族','新婦の家族や親族','2025-03-16 06:50:30');
/*!40000 ALTER TABLE `group_types` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `guest_details`
--

DROP TABLE IF EXISTS `guest_details`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `guest_details` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `response_id` int(11) NOT NULL,
  `age_group` enum('大人','子供','幼児') DEFAULT NULL,
  `dietary_restrictions` text DEFAULT NULL,
  `allergies` text DEFAULT NULL,
  `special_needs` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `response_id` (`response_id`),
  CONSTRAINT `guest_details_ibfk_1` FOREIGN KEY (`response_id`) REFERENCES `responses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `guest_details`
--

LOCK TABLES `guest_details` WRITE;
/*!40000 ALTER TABLE `guest_details` DISABLE KEYS */;
/*!40000 ALTER TABLE `guest_details` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `guest_fusen`
--

DROP TABLE IF EXISTS `guest_fusen`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `guest_fusen` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `guest_id` int(11) NOT NULL,
  `fusen_type_id` int(11) NOT NULL,
  `custom_message` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `guest_id` (`guest_id`),
  KEY `fusen_type_id` (`fusen_type_id`),
  CONSTRAINT `guest_fusen_ibfk_1` FOREIGN KEY (`guest_id`) REFERENCES `guests` (`id`) ON DELETE CASCADE,
  CONSTRAINT `guest_fusen_ibfk_2` FOREIGN KEY (`fusen_type_id`) REFERENCES `fusen_types` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `guest_fusen`
--

LOCK TABLES `guest_fusen` WRITE;
/*!40000 ALTER TABLE `guest_fusen` DISABLE KEYS */;
INSERT INTO `guest_fusen` VALUES (1,8,5,'','2025-03-19 01:52:00'),(2,8,2,'','2025-03-19 01:52:23'),(3,8,1,'','2025-03-19 04:20:31'),(4,8,3,'','2025-03-19 04:20:37'),(5,8,4,'','2025-03-19 04:20:43'),(6,8,6,'','2025-03-19 04:20:50'),(7,9,5,'','2025-03-19 10:59:26');
/*!40000 ALTER TABLE `guest_fusen` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `guestbook`
--

DROP TABLE IF EXISTS `guestbook`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `guestbook` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `email` varchar(200) NOT NULL,
  `group_name` varchar(200) DEFAULT NULL,
  `message` text NOT NULL,
  `approved` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `guestbook`
--

LOCK TABLES `guestbook` WRITE;
/*!40000 ALTER TABLE `guestbook` DISABLE KEYS */;
INSERT INTO `guestbook` VALUES (1,'SHO MURAOKA','syo.t.company@gmail.com',NULL,'がんばれ　招待状作成頑張って',1,'2025-03-15 11:19:08');
/*!40000 ALTER TABLE `guestbook` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `guests`
--

DROP TABLE IF EXISTS `guests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `guests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `group_id` varchar(50) DEFAULT NULL,
  `group_name` varchar(100) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `arrival_time` varchar(100) NOT NULL,
  `custom_message` text DEFAULT NULL,
  `max_companions` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `group_type_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `group_id` (`group_id`),
  KEY `group_type_id` (`group_type_id`),
  CONSTRAINT `guests_ibfk_1` FOREIGN KEY (`group_type_id`) REFERENCES `group_types` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `guests`
--

LOCK TABLES `guests` WRITE;
/*!40000 ALTER TABLE `guests` DISABLE KEYS */;
INSERT INTO `guests` VALUES (2,'wedding2025','結婚式テストゲスト','test@example.com','12:30','この度はおめでとうございます。当日をお楽しみにしております。',3,'2025-03-15 07:51:37',NULL),(4,'sfamily','翔家族',NULL,'11:11','ありがとう',10,'2025-03-15 10:52:23',NULL),(5,'tashima','田嶋',NULL,'10:00','いつもありがとうございます',5,'2025-03-15 13:52:48',NULL),(6,'muraoka','村岡様',NULL,'10:00','',10,'2025-03-16 00:32:12',NULL),(7,'shin.k','真ちゃん',NULL,'11:00','ありがとうね無理せず',5,'2025-03-16 08:34:59',2),(8,'akane_isono','あかねちゃん',NULL,'9:00','ありがとね',10,'2025-03-16 10:33:48',3),(9,'someya','そめや',NULL,'11:30','遠いのにはるばる来てくれてありがとう　\r\n絶対後悔させない結婚式にします',2,'2025-03-16 10:36:38',3);
/*!40000 ALTER TABLE `guests` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `layout_settings`
--

DROP TABLE IF EXISTS `layout_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `layout_settings` (
  `id` int(11) NOT NULL,
  `zoom` float DEFAULT 1,
  `translate_x` float DEFAULT 0,
  `translate_y` float DEFAULT 0,
  `layout_width` int(11) DEFAULT 1500,
  `layout_height` int(11) DEFAULT 1000,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `layout_settings`
--

LOCK TABLES `layout_settings` WRITE;
/*!40000 ALTER TABLE `layout_settings` DISABLE KEYS */;
INSERT INTO `layout_settings` VALUES (1,0.593023,0,0,1500,860,'2025-03-16 22:26:51','2025-03-18 14:58:30');
/*!40000 ALTER TABLE `layout_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notification_logs`
--

DROP TABLE IF EXISTS `notification_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notification_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `guest_id` int(11) DEFAULT NULL,
  `notification_type` varchar(50) NOT NULL,
  `sent_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` varchar(20) DEFAULT 'sent',
  `error_message` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `guest_id` (`guest_id`),
  CONSTRAINT `notification_logs_ibfk_1` FOREIGN KEY (`guest_id`) REFERENCES `guests` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notification_logs`
--

LOCK TABLES `notification_logs` WRITE;
/*!40000 ALTER TABLE `notification_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `notification_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notification_settings`
--

DROP TABLE IF EXISTS `notification_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notification_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `type` varchar(50) NOT NULL,
  `is_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `subject` varchar(255) NOT NULL,
  `template` text NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notification_settings`
--

LOCK TABLES `notification_settings` WRITE;
/*!40000 ALTER TABLE `notification_settings` DISABLE KEYS */;
INSERT INTO `notification_settings` VALUES (1,'guest_registration',1,'【結婚式】ゲスト情報登録のお知らせ','お世話になっております。\n\n結婚式の招待状サイトに新しいゲストが登録されました。\n\nゲスト名: {guest_name}\nグループ: {group_name}\nメールアドレス: {email}\n\n管理画面からご確認ください。\n{admin_url}\n\n----\nこのメールは自動送信されています。','2025-03-15 11:13:07'),(2,'rsvp_received',1,'【結婚式】出欠回答のお知らせ','お世話になっております。\n\n結婚式の招待状サイトに新しい出欠回答が届きました。\n\nゲスト名: {guest_name}\nグループ: {group_name}\n出欠: {attendance_status}\n人数: {guest_count}名\nメッセージ: {message}\n\n管理画面からご確認ください。\n{admin_url}\n\n----\nこのメールは自動送信されています。','2025-03-15 11:13:07'),(3,'new_guestbook',1,'【結婚式】ゲストブックに新しいメッセージ','お世話になっております。\n\n結婚式の招待状サイトのゲストブックに新しいメッセージが投稿されました。\n\n投稿者名: {name}\nメッセージ: {message}\n投稿日時: {date}\n\n管理画面から承認・確認してください。\n{admin_url}\n\n----\nこのメールは自動送信されています。','2025-03-15 11:13:07'),(4,'guest_confirmation',1,'【結婚式】ゲスト情報受付完了のお知らせ','{guest_name} 様\n\nこの度は、私たちの結婚式にご出席いただけるとのこと、誠にありがとうございます。\n\n以下の内容で受付いたしました。\n\nお名前: {guest_name}\n出欠: {attendance_status}\n参加人数: {guest_count}名\n\n何かご不明な点がございましたら、お気軽にお問い合わせください。\n\n当日お会いできることを楽しみにしております。\n\n----\n新郎新婦: {bride_name} & {groom_name}\nウェディングサイト: {website_url}','2025-03-15 11:13:07'),(5,'event_reminder',1,'【結婚式】まもなく結婚式のご案内','{guest_name} 様\n\nいよいよ私たちの結婚式が近づいてまいりました。\n\n◆ 日時: {wedding_date} {wedding_time}\n◆ 場所: {venue_name}\n◆ 住所: {venue_address}\n\nご出席の皆様に改めてご案内申し上げます。\n当日の詳細やタイムスケジュールはウェディングサイトでご確認いただけます。\n{website_url}\n\n皆様とお会いできることを心より楽しみにしております。\n\n----\n新郎新婦: {bride_name} & {groom_name}','2025-03-15 11:13:07');
/*!40000 ALTER TABLE `notification_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `photo_gallery`
--

DROP TABLE IF EXISTS `photo_gallery`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `photo_gallery`
--

LOCK TABLES `photo_gallery` WRITE;
/*!40000 ALTER TABLE `photo_gallery` DISABLE KEYS */;
INSERT INTO `photo_gallery` VALUES (1,'ああ','ああ','67db3dc2df539_IMG6799.jpg','IMG_6799.jpg',2779312,'image/jpeg','村岡翔',1,0,'2025-03-19 21:57:22'),(2,'い','い','67db3e185f304_IMG3477.jpg','IMG_3477.jpg',368828,'image/jpeg','村岡翔',1,0,'2025-03-19 21:58:48'),(4,'う','う','67db3e3b54dfd_IMG5046.jpg','IMG_5046.jpg',265711,'image/jpeg','村岡翔',1,0,'2025-03-19 21:59:23');
/*!40000 ALTER TABLE `photo_gallery` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `remarks`
--

DROP TABLE IF EXISTS `remarks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `remarks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `content` text NOT NULL,
  `display_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `remarks`
--

LOCK TABLES `remarks` WRITE;
/*!40000 ALTER TABLE `remarks` DISABLE KEYS */;
INSERT INTO `remarks` VALUES (1,'{group_name_case}受付は{arrival_time}より開始いたしますので、{arrival_time_minus10}を目安にお越しください。',1,'2025-03-20 08:55:23','2025-03-20 11:03:03'),(2,'駐車場に限りがありますので、公共交通機関のご利用にご協力をお願いいたします。',2,'2025-03-20 08:55:23','2025-03-20 08:55:23'),(3,'{group_name_case}ご欠席の場合やお問い合わせは、新郎新婦までご連絡をお願いいたします。',3,'2025-03-20 08:55:23','2025-03-20 11:03:27'),(4,'皆さまにお会いできますことを心より楽しみにしております。',4,'2025-03-20 08:55:23','2025-03-20 08:55:23');
/*!40000 ALTER TABLE `remarks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `responses`
--

DROP TABLE IF EXISTS `responses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `responses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `guest_id` int(11) DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `attending` tinyint(1) NOT NULL,
  `companions` int(11) DEFAULT 0,
  `message` text DEFAULT NULL,
  `dietary` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `guest_id` (`guest_id`),
  CONSTRAINT `responses_ibfk_1` FOREIGN KEY (`guest_id`) REFERENCES `guests` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `responses`
--

LOCK TABLES `responses` WRITE;
/*!40000 ALTER TABLE `responses` DISABLE KEYS */;
INSERT INTO `responses` VALUES (1,NULL,'Test User',NULL,1,0,'Test Message','None','2025-03-15 07:46:11'),(2,NULL,'テスト太郎','test@example.com',1,0,'これはテストです','','2025-03-15 08:16:25'),(3,NULL,'テスト太郎','test@example.com',1,0,'これはテストです','','2025-03-15 08:16:47'),(4,NULL,'直接テスト挿入','direct@test.com',1,0,'データベースに直接挿入テスト','','2025-03-15 08:18:04'),(5,NULL,'TestUser','test@example.com',1,0,'Test','None','2025-03-15 08:35:13'),(6,NULL,'村岡翔','syo.t.company@gmail.com',1,1,'q','q','2025-03-15 08:36:31'),(7,NULL,'RidePulseDB','syo.t.company@gmail.com',1,2,'q','q','2025-03-15 08:37:37'),(9,NULL,'村岡翔','syou51158@gmail.com',1,3,'がんばれれれれっれ','えび　かに','2025-03-15 11:33:41'),(10,5,'田嶋','tashima@sopranohaction.fun',1,2,'頑張れれれれれれ','チョコレート','2025-03-15 13:54:31'),(11,5,'tashima','tashima@tashima.com',1,5,'テスト','マンゴー','2025-03-15 14:03:03'),(12,5,'田島さえか','tashima@tashima.jp',0,0,'ごめんね','マンゴー','2025-03-15 14:08:17'),(13,NULL,'村岡翔','syou51158ym@gmail.com',1,3,'がんば','なし','2025-03-16 00:29:13'),(14,6,'村岡翔','syo.t.cook@gmail.com',1,10,'おめでとう','なし','2025-03-16 00:33:18'),(15,7,'小山真','shin.k@example.com',1,2,'おおおおお！！！','うめ','2025-03-16 08:36:29'),(16,7,'小山しん','shin@example2.com',1,1,'あ','あ','2025-03-16 08:52:14'),(17,7,'小山しん','shin@example3.com',1,1,'い','p','2025-03-16 08:57:23'),(18,7,'小山しん','shin@example4.com',1,5,'w','w','2025-03-16 10:21:41'),(19,9,'そめやあやな','someya@example.com',1,2,'あちこがんばれ','メンヘラ','2025-03-16 10:41:18'),(20,8,'akanetest1','syou51158ym@gmail.com',1,8,'おめでとう','','2025-03-21 03:40:41'),(21,8,'あかね２','syou51158@gmail.com',1,3,'おめでとう','本人は　えび','2025-03-21 04:02:30');
/*!40000 ALTER TABLE `responses` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `schedule`
--

DROP TABLE IF EXISTS `schedule`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `schedule` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `event_time` time NOT NULL,
  `event_name` varchar(100) NOT NULL,
  `event_description` text DEFAULT NULL,
  `for_group_type` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `schedule`
--

LOCK TABLES `schedule` WRITE;
/*!40000 ALTER TABLE `schedule` DISABLE KEYS */;
INSERT INTO `schedule` VALUES (14,'11:30:00','受付開始','受付後、順次ご案内いたします',NULL),(15,'12:00:00','挙式','チャペルにて（キリスト教式）',NULL),(16,'12:30:00','フラワーシャワー・記念撮影','屋外（雨天時は屋内）',NULL),(17,'13:00:00','披露宴 開宴','お食事・各種イベントをお楽しみください',NULL),(18,'15:30:00','お開き（披露宴終了）','お見送り',NULL);
/*!40000 ALTER TABLE `schedule` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `seat_assignments`
--

DROP TABLE IF EXISTS `seat_assignments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `seat_assignments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `response_id` int(11) NOT NULL,
  `table_id` int(11) NOT NULL,
  `seat_number` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_companion` tinyint(1) DEFAULT 0,
  `companion_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `response_id` (`response_id`),
  KEY `seat_assignments_ibfk_2` (`table_id`),
  CONSTRAINT `seat_assignments_ibfk_1` FOREIGN KEY (`response_id`) REFERENCES `responses` (`id`),
  CONSTRAINT `seat_assignments_ibfk_2` FOREIGN KEY (`table_id`) REFERENCES `seating_tables` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `seat_assignments`
--

LOCK TABLES `seat_assignments` WRITE;
/*!40000 ALTER TABLE `seat_assignments` DISABLE KEYS */;
/*!40000 ALTER TABLE `seat_assignments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `seating_settings`
--

DROP TABLE IF EXISTS `seating_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `seating_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_name` varchar(255) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `seating_settings`
--

LOCK TABLES `seating_settings` WRITE;
/*!40000 ALTER TABLE `seating_settings` DISABLE KEYS */;
INSERT INTO `seating_settings` VALUES (1,'print_boundary_left','403','2025-03-21 01:44:35','2025-03-21 01:44:53'),(2,'print_boundary_top','33','2025-03-21 01:44:35','2025-03-21 01:44:41'),(3,'print_boundary_width','655','2025-03-21 01:44:35','2025-03-21 01:44:53'),(4,'print_boundary_height','500','2025-03-21 01:44:35','2025-03-21 01:44:43');
/*!40000 ALTER TABLE `seating_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `seating_tables`
--

DROP TABLE IF EXISTS `seating_tables`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `seating_tables` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `table_name` varchar(50) NOT NULL,
  `capacity` int(11) NOT NULL DEFAULT 8,
  `position_x` int(11) DEFAULT 0,
  `position_y` int(11) DEFAULT 0,
  `table_type` enum('regular','special','bridal','rectangle') DEFAULT 'regular',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `seating_tables`
--

LOCK TABLES `seating_tables` WRITE;
/*!40000 ALTER TABLE `seating_tables` DISABLE KEYS */;
INSERT INTO `seating_tables` VALUES (1,'A',8,0,0,'regular','2025-03-21 04:29:32','2025-03-21 05:01:20'),(2,'B',8,0,0,'regular','2025-03-21 04:29:32','2025-03-21 05:01:20'),(3,'C',8,0,0,'regular','2025-03-21 04:29:32','2025-03-21 05:01:20'),(4,'D',8,0,0,'regular','2025-03-21 04:29:32','2025-03-21 05:01:20'),(5,'F',8,0,0,'regular','2025-03-21 04:29:32','2025-03-21 05:01:20'),(6,'G',8,0,0,'regular','2025-03-21 04:29:32','2025-03-21 05:01:20'),(7,'H',8,0,0,'regular','2025-03-21 04:29:32','2025-03-21 05:01:20'),(8,'I',8,0,0,'regular','2025-03-21 04:29:32','2025-03-21 05:01:20'),(9,'J',8,0,0,'regular','2025-03-21 04:29:32','2025-03-21 05:01:20'),(10,'K',8,0,0,'regular','2025-03-21 04:29:32','2025-03-21 05:01:20'),(11,'L',8,0,0,'regular','2025-03-21 04:29:32','2025-03-21 05:01:20'),(12,'新郎',4,0,0,'special','2025-03-21 04:29:32','2025-03-21 05:01:20'),(13,'新婦',4,0,0,'special','2025-03-21 04:29:32','2025-03-21 05:01:20');
/*!40000 ALTER TABLE `seating_tables` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `system_settings`
--

DROP TABLE IF EXISTS `system_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `system_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(50) NOT NULL,
  `setting_value` text NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `system_settings`
--

LOCK TABLES `system_settings` WRITE;
/*!40000 ALTER TABLE `system_settings` DISABLE KEYS */;
INSERT INTO `system_settings` VALUES (1,'admin_email','syou51158@gmail.com','2025-03-16 08:48:23');
/*!40000 ALTER TABLE `system_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `transportation`
--

DROP TABLE IF EXISTS `transportation`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `transportation` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `type` enum('電車','バス','車','タクシー','その他') NOT NULL,
  `from_location` varchar(100) NOT NULL,
  `to_location` varchar(100) NOT NULL,
  `duration` varchar(50) DEFAULT NULL,
  `cost` varchar(50) DEFAULT NULL,
  `schedule` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `transportation`
--

LOCK TABLES `transportation` WRITE;
/*!40000 ALTER TABLE `transportation` DISABLE KEYS */;
/*!40000 ALTER TABLE `transportation` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `travel_info`
--

DROP TABLE IF EXISTS `travel_info`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `travel_info`
--

LOCK TABLES `travel_info` WRITE;
/*!40000 ALTER TABLE `travel_info` DISABLE KEYS */;
INSERT INTO `travel_info` VALUES (1,'🌸 ブライダル宿泊特別プランのご案内 🌸','お部屋タイプの特徴 🏨\r\n	•	虹館\r\n	•	シャワールームのみ（バスタブ無し）\r\n	•	展望露天風呂「棚湯」利用可能\r\n	•	レストラン『シーダパレス』利用\r\n	•	星館\r\n	•	シャワールームのみ（バスタブ無し）\r\n	•	展望露天風呂「棚湯」利用可能\r\n	•	レストラン『和ダイニング星』利用（先着順）\r\n	•	宙館\r\n	•	デラックス海以外はシャワールームのみ（バスタブ無し）\r\n	•	展望露天風呂「棚湯」利用可能\r\n	•	宿泊者専用「宙湯」利用可能\r\n	•	レストラン『TERACE&DINING SORA』または『シーダパレス』利用（先着順）\r\n\r\n⸻\r\n\r\n🌟特別サービス🌟\r\n	•	新郎新婦様には宙館デラックスルーム宿泊（1泊2食付き）をプレゼント！（※披露宴大人10名以上対象）\r\n\r\n⸻\r\n\r\nご宿泊に関するよくある質問 💬\r\n	•	チェックイン 15:00〜／チェックアウト 翌日11:00\r\n	•	Wi-Fi ロビー・客室で無料利用可\r\n	•	食料持ち込み 客室への持ち込みOK\r\n	•	キャンセル料 宿泊日の7日前から発生（14名までの場合）\r\n\r\n⸻\r\n\r\nぜひご旅行や結婚式へのご参加にご活用くださいませ🎉✨\r\nご予約はお早めにお願いいたします！','accommodation',1,1,'67db52d5399ec_1742426837.png','2025-03-19 23:27:17','2025-03-20 03:23:16'),(2,'🌊 お部屋タイプ変更による追加料金について 🌊','各館で『海側のお部屋』など他のタイプへご変更をご希望の場合、下記の通り追加料金がかかります。（1名様あたり／1泊朝食付・税込）\r\n\r\n■ 虹館（基準：カジュアルダブルorツイン）\r\n	•	カジュアルロフト／スキップ（定員4名）\r\n1室3名利用の場合、表示価格より 1,000円引き\r\n1室4名利用の場合、表示価格より 2,000円引き\r\n\r\n■ 星館（基準：スタンダード洋室 山側）\r\n	•	スタンダード洋室 海側へ変更\r\n	•	2名利用の場合、表示価格に ＋3,000円\r\n	•	3名利用の場合、表示価格に ＋2,000円\r\n	•	4名利用の場合、表示価格に ＋1,000円\r\n	•	シングル利用の場合\r\n	•	山側スタンダード洋室を1名利用：表示価格の 1.5倍\r\n\r\n■ 宙館（基準：スタンダードツイン 山側）\r\n	•	プレミアムスタンダード海側へ変更\r\n	•	2名利用の場合、表示価格に ＋7,000円\r\n	•	3名利用の場合、表示価格に ＋6,000円\r\n	•	4名利用の場合、表示価格に ＋5,000円\r\n	•	デラックス海側へ変更\r\n	•	2名利用の場合、表示価格に ＋9,000円\r\n	•	3名利用の場合、表示価格に ＋5,000円\r\n	•	4名利用の場合、表示価格に ＋4,000円\r\n	•	5名利用の場合、表示価格に ＋1,000円\r\n	•	シングル利用の場合\r\n	•	山側スタンダードツインを1名利用：表示価格の 1.5倍\r\n\r\n\r\n\r\n⸻\r\n\r\nご予約の際はご希望のお部屋タイプをお知らせくださいませ✨','accommodation',2,1,'67db5392448f3_1742427026.png','2025-03-19 23:30:26','2025-03-20 03:23:08');
/*!40000 ALTER TABLE `travel_info` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `video_gallery`
--

DROP TABLE IF EXISTS `video_gallery`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `video_gallery`
--

LOCK TABLES `video_gallery` WRITE;
/*!40000 ALTER TABLE `video_gallery` DISABLE KEYS */;
INSERT INTO `video_gallery` VALUES (1,'え','え','1742421862_409f21ca82a24bef9fe10ccc742165d1.MOV','409f21ca82a24bef9fe10ccc742165d1.MOV',19552269,'video/quicktime','2025-03-20 07:04:22',1,0,'thumb_1742421862_2025-03-1919.27.17.png'),(2,'い','い','1742421908_409f21ca82a24bef9fe10ccc742165d1.MOV','409f21ca82a24bef9fe10ccc742165d1.MOV',19552269,'video/quicktime','2025-03-20 07:05:08',1,0,NULL),(3,'サムネ','サムネ','1742448933_00846E89-DE57-4EE5-909E-15708A19B6BC.mov','00846E89-DE57-4EE5-909E-15708A19B6BC.mov',34509710,'video/quicktime','2025-03-20 14:35:33',1,1,'thumb_1742448933_2025-03-1316.29.30.png');
/*!40000 ALTER TABLE `video_gallery` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `wedding_settings`
--

DROP TABLE IF EXISTS `wedding_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `wedding_settings`
--

LOCK TABLES `wedding_settings` WRITE;
/*!40000 ALTER TABLE `wedding_settings` DISABLE KEYS */;
INSERT INTO `wedding_settings` VALUES (1,'bride_name','あかね','新婦の名前','新婦の名前を入力してください。','2025-03-15 23:34:46'),(2,'groom_name','村岡 翔','新郎の名前','新郎の名前を入力してください。','2025-03-15 23:34:46'),(3,'wedding_date','2024年4月30日','結婚式の日付','結婚式の日付を入力してください。例: 2024年5月25日','2025-03-15 23:36:39'),(4,'wedding_time','12:00','結婚式の開始時間','結婚式の開始時間を入力してください。例: 13:00','2025-03-15 23:36:39'),(5,'venue_name','別府温泉　杉乃井ホテル 杉乃井パレス内　ウェディングホール１階 ザ・スイートテラス','会場名','結婚式の会場名を入力してください。','2025-03-16 00:12:54'),(6,'venue_address','〒874-0823 大分県別府市観海寺１−２２７２','会場住所','会場の住所を入力してください。','2025-03-16 00:12:54'),(7,'venue_map_url','<iframe src=\"https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d375.2281292569163!2d131.47269832960316!3d33.284478272277504!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x3546a65ce9501f11%3A0x89e5e19c6f202901!2z5p2J5LmD5LqV44OR44Os44K5!5e0!3m2!1sja!2sjp!4v1742082382882!5m2!1sja!2sjp\" width=\"600\" height=\"450\" style=\"border:0;\" allowfullscreen=\"\" loading=\"lazy\" referrerpolicy=\"no-referrer-when-downgrade\"></iframe>','会場のGoogleマップURL','GoogleマップのURLを入力してください。共有URLまたは埋め込みiframeのsrcに指定するURL。','2025-03-15 23:46:59'),(8,'venue_map_link','https://maps.app.goo.gl/r6h7ZfrSyDLamj7R9','会場のGoogleマップリンク','スマートフォンなどで開くためのGoogleマップリンクを入力してください。','2025-03-15 23:46:59');
/*!40000 ALTER TABLE `wedding_settings` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2025-03-21 14:06:08
