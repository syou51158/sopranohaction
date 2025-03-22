-- ホテルに関連するギャラリー情報
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