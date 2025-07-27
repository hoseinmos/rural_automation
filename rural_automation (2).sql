-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Jul 21, 2025 at 05:53 PM
-- Server version: 5.7.40
-- PHP Version: 8.0.26

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `rural_automation`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

DROP TABLE IF EXISTS `activity_logs`;
CREATE TABLE IF NOT EXISTS `activity_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) COLLATE utf8mb4_persian_ci NOT NULL,
  `description` mediumtext COLLATE utf8mb4_persian_ci,
  `ip_address` varchar(45) COLLATE utf8mb4_persian_ci DEFAULT NULL,
  `user_agent` mediumtext COLLATE utf8mb4_persian_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_action` (`action`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

-- --------------------------------------------------------

--
-- Table structure for table `digital_signatures`
--

DROP TABLE IF EXISTS `digital_signatures`;
CREATE TABLE IF NOT EXISTS `digital_signatures` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `signature_name` varchar(255) COLLATE utf8mb4_persian_ci NOT NULL,
  `signature_image` varchar(255) COLLATE utf8mb4_persian_ci NOT NULL,
  `position_title` varchar(255) COLLATE utf8mb4_persian_ci DEFAULT NULL,
  `organization_name` varchar(255) COLLATE utf8mb4_persian_ci DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_active` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

--
-- Dumping data for table `digital_signatures`
--

INSERT INTO `digital_signatures` (`id`, `user_id`, `signature_name`, `signature_image`, `position_title`, `organization_name`, `is_active`, `created_at`, `updated_at`) VALUES
(2, 1, 'حسین مصطفائی فر', '1752771489_68792ba1d3ded_photo_2025-05-21_23-20-07.jpg', 'مدیر سیستم', 'حسین مصطفائی فر', 1, '2025-07-17 16:58:09', '2025-07-17 16:58:09'),
(3, 3, 'مسعود سلیمی', '1753094595_687e19c3a5d9e_images.png', 'دهیار', 'دهیاری روستای نمونه', 1, '2025-07-21 10:43:15', '2025-07-21 10:43:15');

-- --------------------------------------------------------

--
-- Table structure for table `letter_headers`
--

DROP TABLE IF EXISTS `letter_headers`;
CREATE TABLE IF NOT EXISTS `letter_headers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) COLLATE utf8mb4_persian_ci NOT NULL,
  `logo_image` varchar(255) COLLATE utf8mb4_persian_ci DEFAULT NULL,
  `organization_name` varchar(255) COLLATE utf8mb4_persian_ci NOT NULL,
  `address` mediumtext COLLATE utf8mb4_persian_ci,
  `phone` varchar(50) COLLATE utf8mb4_persian_ci DEFAULT NULL,
  `email` varchar(100) COLLATE utf8mb4_persian_ci DEFAULT NULL,
  `website` varchar(255) COLLATE utf8mb4_persian_ci DEFAULT NULL,
  `is_default` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

-- --------------------------------------------------------

--
-- Table structure for table `letter_templates`
--

DROP TABLE IF EXISTS `letter_templates`;
CREATE TABLE IF NOT EXISTS `letter_templates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_persian_ci NOT NULL DEFAULT 'قالب اصلی',
  `background_image` varchar(255) COLLATE utf8mb4_persian_ci NOT NULL,
  `page_size` varchar(10) COLLATE utf8mb4_persian_ci DEFAULT 'A5',
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

--
-- Dumping data for table `letter_templates`
--

INSERT INTO `letter_templates` (`id`, `name`, `background_image`, `page_size`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'قالب اصلی', 'default.jpg', 'A5', 1, '2025-07-21 04:53:06', '2025-07-21 10:30:15'),
(2, 'قالب اصلی', '1753094018_687e1782e0aac_default_bg.jpg', 'A5', 1, '2025-07-21 10:33:38', '2025-07-21 10:33:38'),
(3, 'قالب اصلی', '1753094388_687e18f4ce053_1753094018_687e1782e0aac_default_bg.jpg', 'A5', 1, '2025-07-21 10:39:48', '2025-07-21 10:39:48');

-- --------------------------------------------------------

--
-- Table structure for table `login_attempts`
--

DROP TABLE IF EXISTS `login_attempts`;
CREATE TABLE IF NOT EXISTS `login_attempts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) COLLATE utf8mb4_persian_ci NOT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_persian_ci NOT NULL,
  `attempt_time` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_username` (`username`),
  KEY `idx_ip_address` (`ip_address`),
  KEY `idx_attempt_time` (`attempt_time`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

--
-- Dumping data for table `login_attempts`
--

INSERT INTO `login_attempts` (`id`, `username`, `ip_address`, `attempt_time`) VALUES
(2, 'admin1', '::1', '2025-07-16 01:57:57');

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

DROP TABLE IF EXISTS `messages`;
CREATE TABLE IF NOT EXISTS `messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `subject` varchar(255) COLLATE utf8mb4_persian_ci NOT NULL,
  `message_number` varchar(50) COLLATE utf8mb4_persian_ci DEFAULT NULL,
  `content` mediumtext COLLATE utf8mb4_persian_ci NOT NULL,
  `sender_id` int(11) NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `attachment` varchar(255) COLLATE utf8mb4_persian_ci DEFAULT NULL,
  `status` enum('unread','read','replied','archived','deleted') COLLATE utf8mb4_persian_ci DEFAULT 'unread',
  `priority` enum('low','normal','high','urgent') COLLATE utf8mb4_persian_ci DEFAULT 'normal',
  `reply_to` int(11) DEFAULT NULL,
  `signed_by` int(11) DEFAULT NULL,
  `signed_at` timestamp NULL DEFAULT NULL,
  `signature_id` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_sender` (`sender_id`),
  KEY `idx_receiver` (`receiver_id`),
  KEY `idx_status` (`status`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_reply_to` (`reply_to`),
  KEY `signed_by` (`signed_by`),
  KEY `signature_id` (`signature_id`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

--
-- Dumping data for table `messages`
--

INSERT INTO `messages` (`id`, `subject`, `message_number`, `content`, `sender_id`, `receiver_id`, `attachment`, `status`, `priority`, `reply_to`, `signed_by`, `signed_at`, `signature_id`, `created_at`, `updated_at`) VALUES
(1, 'اسفالت جاده روستایی', '', 'سلام این جاده خراب است', 3, 1, NULL, 'archived', 'normal', NULL, NULL, NULL, NULL, '2025-07-16 02:02:43', '2025-07-16 03:40:36'),
(2, 'پاسخ: اسفالت جاده روستایی', '', 'در پاسخ به نامه شما:\r\nسلام این جاده خراب است\r\n\r\nدر دست اقدام می باشد', 1, 3, NULL, 'read', 'normal', 1, NULL, NULL, NULL, '2025-07-16 02:04:12', '2025-07-16 02:04:52'),
(3, 'تهران', '', 'مشکل دارم', 1, 3, '1752631626_6877094a5b2f5_photo_2025-05-21_23-20-07.jpg', 'archived', 'urgent', NULL, NULL, NULL, NULL, '2025-07-16 02:07:06', '2025-07-16 02:43:45'),
(4, 'تهران', '', 'مدیررررررررررررر', 1, 3, NULL, 'read', 'normal', NULL, NULL, NULL, NULL, '2025-07-16 02:23:57', '2025-07-16 02:36:32'),
(5, 'روستای چروش', '', 'سلام جاده احتیاج به اسفالت دارد', 3, 1, '1752634633_687715094b03c_photo_2025-07-09_16-24-11.jpg', 'archived', 'normal', NULL, NULL, NULL, NULL, '2025-07-16 02:57:13', '2025-07-16 03:40:36'),
(6, 'پاسخ: اسفالت جاده روستایی', '', 'H.mostafaeiH.mostafaei', 4, 1, NULL, 'archived', 'urgent', NULL, NULL, NULL, NULL, '2025-07-16 03:27:19', '2025-07-16 03:40:36'),
(7, 'پاسخ: پاسخ: اسفالت جاده روستایی', '', 'سلام ارجاع داده شد', 1, 4, NULL, 'replied', 'normal', 6, NULL, NULL, NULL, '2025-07-16 03:28:53', '2025-07-16 03:29:26'),
(8, 'پاسخ: پاسخ: پاسخ: اسفالت جاده روستایی', '', 'با تشکر', 4, 1, NULL, 'archived', 'normal', 7, NULL, NULL, NULL, '2025-07-16 03:29:26', '2025-07-16 03:40:36'),
(9, 'تهران', '1233321', 'db_backup_2025-07-16_07-03-56.sql', 1, 3, NULL, 'read', 'normal', NULL, NULL, NULL, NULL, '2025-07-16 03:39:03', '2025-07-16 19:32:09'),
(10, 'تهران', '', 'admin', 1, 4, NULL, 'unread', 'normal', NULL, NULL, NULL, NULL, '2025-07-16 19:54:51', '2025-07-16 19:54:51'),
(11, 'اسفالت جاده روستایی چشمه کبود', '123321', 'سلام خواهشمندم جاده را اسفالت کنید', 3, 1, NULL, 'archived', 'high', NULL, NULL, NULL, NULL, '2025-07-17 06:22:12', '2025-07-17 15:34:32'),
(12, 'درخواست خود را تا تاریخ 1404/04/30', '1', 'سلام خواهشمندم درخواست های خود را تا تاریخ مذکور بفرستید', 1, 3, NULL, 'replied', 'urgent', NULL, NULL, NULL, NULL, '2025-07-17 06:30:20', '2025-07-17 06:31:57'),
(13, 'پاسخ: درخواست خود را تا تاریخ 1404/04/30', '', 'در پاسخ به نامه شما:\r\nسلام خواهشمندم درخواست های خود را تا تاریخ مذکور بفرستید\r\n\r\n\r\nسلام جاده احتیاج به اسفالت دارد \r\nبا تشکر', 3, 1, NULL, 'archived', 'normal', 12, NULL, NULL, NULL, '2025-07-17 06:31:57', '2025-07-17 06:32:32'),
(14, 'xxxxxxxxxxxxxx', '', 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx', 1, 4, NULL, 'unread', 'normal', NULL, NULL, NULL, NULL, '2025-07-21 06:26:40', '2025-07-21 06:26:40'),
(15, 'xxxxxxxxxxxxxx', 'ccccccccc', 'cccccccccccccccc', 1, 3, NULL, 'read', 'normal', NULL, NULL, NULL, NULL, '2025-07-21 06:29:00', '2025-07-21 06:29:09'),
(16, 'سفالت جاده روستایی', '12', 'لورم ایپسوم متن ساختگی با تولید سادگی نامفهوم از صنعت چاپ و با استفاده از طراحان گرافیک است. چاپگرها و متون بلکه روزنامه و مجله در ستون و سطرآنچنان که لازم است و برای شرایط فعلی تکنولوژی مورد نیاز و کاربردهای متنوع با هدف بهبود ابزارهای کاربردی می باشد. کتابهای زیادی در شصت و سه درصد گذشته، حال و آینده شناخت فراوان جامعه و متخصصان را می طلبد تا با نرم افزارها شناخت بیشتری را برای طراحان رایانه ای علی الخصوص طراحان خلاقی و فرهنگ پیشرو در زبان فارسی ایجاد کرد. در این صورت می توان امید داشت که تمام و دشواری موجود در ارائه راهکارها و شرایط سخت تایپ به پایان رسد وزمان مورد نیاز شامل حروفچینی دستاوردهای اصلی و جوابگوی سوالات پیوسته اهل دنیای موجود طراحی اساسا مورد استفاده قرار گیرد.', 3, 1, NULL, 'read', 'normal', NULL, NULL, NULL, NULL, '2025-07-21 17:51:21', '2025-07-21 17:51:28');

-- --------------------------------------------------------

--
-- Table structure for table `remember_tokens`
--

DROP TABLE IF EXISTS `remember_tokens`;
CREATE TABLE IF NOT EXISTS `remember_tokens` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `token` varchar(191) COLLATE utf8mb4_persian_ci NOT NULL,
  `expires_at` timestamp NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `token` (`token`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_expires` (`expires_at`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

DROP TABLE IF EXISTS `system_settings`;
CREATE TABLE IF NOT EXISTS `system_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) COLLATE utf8mb4_persian_ci NOT NULL,
  `setting_value` mediumtext COLLATE utf8mb4_persian_ci,
  `description` varchar(255) COLLATE utf8mb4_persian_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`),
  KEY `idx_setting_key` (`setting_key`)
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

--
-- Dumping data for table `system_settings`
--

INSERT INTO `system_settings` (`id`, `setting_key`, `setting_value`, `description`, `created_at`, `updated_at`) VALUES
(1, 'site_title', 'سیستم اتوماسیون اداری دهیاری کرمانشاه', 'عنوان سایت', '2025-07-16 01:52:40', '2025-07-17 16:41:22'),
(2, 'max_file_size', '10485760', 'حداکثر اندازه فایل (بایت)', '2025-07-16 01:52:40', '2025-07-17 16:41:22'),
(3, 'allowed_extensions', 'pdf,jpg,jpeg,png,mp4,avi,doc,docx', 'پسوندهای مجاز فایل', '2025-07-16 01:52:40', '2025-07-16 01:52:40'),
(4, 'session_timeout', '3600', 'مدت زمان انقضای نشست (ثانیه)', '2025-07-16 01:52:40', '2025-07-17 16:41:22'),
(5, 'pagination_limit', '20', 'تعداد آیتم در هر صفحه', '2025-07-16 01:52:40', '2025-07-16 01:52:40'),
(6, 'system_version', '1.0.0', 'نسخه سیستم', '2025-07-16 01:52:40', '2025-07-16 01:52:40'),
(7, 'installation_date', '2025-07-16 01:52:40', 'تاریخ نصب', '2025-07-16 01:52:40', '2025-07-16 01:52:40'),
(17, 'items_per_page', '20', NULL, '2025-07-17 16:41:19', '2025-07-17 16:41:22');

-- --------------------------------------------------------

--
-- Table structure for table `template_fields`
--

DROP TABLE IF EXISTS `template_fields`;
CREATE TABLE IF NOT EXISTS `template_fields` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `template_id` int(11) NOT NULL,
  `field_name` varchar(50) COLLATE utf8mb4_persian_ci NOT NULL,
  `x_position` int(11) NOT NULL DEFAULT '0',
  `y_position` int(11) NOT NULL DEFAULT '0',
  `width` int(11) DEFAULT NULL,
  `height` int(11) DEFAULT NULL,
  `font_size` int(11) DEFAULT '14',
  `font_family` varchar(50) COLLATE utf8mb4_persian_ci DEFAULT 'B Nazanin',
  `text_align` varchar(20) COLLATE utf8mb4_persian_ci DEFAULT 'right',
  `text_color` varchar(7) COLLATE utf8mb4_persian_ci DEFAULT '#000000',
  PRIMARY KEY (`id`),
  KEY `idx_template_id` (`template_id`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

--
-- Dumping data for table `template_fields`
--

INSERT INTO `template_fields` (`id`, `template_id`, `field_name`, `x_position`, `y_position`, `width`, `height`, `font_size`, `font_family`, `text_align`, `text_color`) VALUES
(1, 1, 'message_number', 298, 98, 150, 30, 14, 'B Nazanin', 'right', '#000000'),
(2, 1, 'date', 295, 138, 150, 30, 14, 'B Nazanin', 'right', '#000000'),
(3, 1, 'subject', 378, 295, 400, 30, 16, 'B Nazanin', 'right', '#000000'),
(4, 1, 'receiver_name', 579, 181, 200, 30, 14, 'B Nazanin', 'right', '#000000'),
(5, 1, 'sender_name', 578, 222, 200, 30, 14, 'B Nazanin', 'right', '#000000'),
(6, 1, 'content', 299, 377, 568, 286, 12, 'B Nazanin', 'right', '#000000'),
(7, 1, 'signature', 361, 687, 100, 60, 0, 'B Nazanin', 'right', '#000000'),
(8, 1, 'stamp_place', 138, 374, 100, 100, 14, 'B Nazanin', 'right', '#000000'),
(9, 1, 'message_number', 298, 98, 150, 30, 14, 'B Nazanin', 'right', '#000000'),
(10, 1, 'date', 295, 138, 150, 30, 14, 'B Nazanin', 'right', '#000000'),
(11, 1, 'subject', 378, 295, 400, 30, 16, 'B Nazanin', 'right', '#000000'),
(12, 1, 'receiver_name', 579, 181, 200, 30, 14, 'B Nazanin', 'right', '#000000'),
(13, 1, 'sender_name', 578, 222, 200, 30, 14, 'B Nazanin', 'right', '#000000'),
(14, 1, 'content', 299, 377, 568, 286, 12, 'B Nazanin', 'right', '#000000'),
(15, 1, 'signature', 361, 687, 100, 60, 0, 'B Nazanin', 'right', '#000000'),
(16, 1, 'stamp_place', 138, 374, 100, 100, 14, 'B Nazanin', 'right', '#000000');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) COLLATE utf8mb4_persian_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_persian_ci NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_persian_ci NOT NULL,
  `email` varchar(100) COLLATE utf8mb4_persian_ci DEFAULT NULL,
  `phone` varchar(20) COLLATE utf8mb4_persian_ci DEFAULT NULL,
  `role` enum('admin','manager','supervisor','user') COLLATE utf8mb4_persian_ci DEFAULT 'user',
  `status` enum('active','inactive','suspended') COLLATE utf8mb4_persian_ci DEFAULT 'active',
  `last_login` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  KEY `idx_username` (`username`),
  KEY `idx_role` (`role`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `name`, `email`, `phone`, `role`, `status`, `last_login`, `created_at`, `updated_at`) VALUES
(1, 'admin', '$2y$10$Ko54hmvj8i6bjRfZHdCAFe3ualdbXlOor39gMmOvZzCo9smneLZF.', 'مدیر کل سیستم', 'admin@rural-automation.ir', NULL, 'admin', 'active', '2025-07-21 17:50:10', '2025-07-16 01:52:40', '2025-07-21 17:50:10'),
(3, 'salimi', '$2y$10$5zQ6gsej0er9qhQ90sUhjumAHqtvXPPxXS5Z.cQRUvLLQj7hpZRN2', 'مسعود سلیمی', 'tutyu@gmailc.om', '09363823556', 'user', 'active', '2025-07-21 17:50:34', '2025-07-16 02:01:26', '2025-07-21 17:50:34'),
(4, 'H.mostafaei', '$2y$10$dwD5fxYJsdCscNz7Sty6aOO9QD1NYmKn3NvsINMc2CdlQ3dcHz3Dy', 'سعید حاتمی', 'hoseinmos2008@gmail.com', NULL, 'user', 'active', '2025-07-16 03:32:25', '2025-07-16 03:26:05', '2025-07-16 03:32:25');

--
-- Constraints for dumped tables
--

--
-- Constraints for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD CONSTRAINT `fk_activity_logs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `digital_signatures`
--
ALTER TABLE `digital_signatures`
  ADD CONSTRAINT `digital_signatures_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `messages`
--
ALTER TABLE `messages`
  ADD CONSTRAINT `fk_messages_receiver` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_messages_reply` FOREIGN KEY (`reply_to`) REFERENCES `messages` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_messages_sender` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`signed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `messages_ibfk_2` FOREIGN KEY (`signature_id`) REFERENCES `digital_signatures` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `remember_tokens`
--
ALTER TABLE `remember_tokens`
  ADD CONSTRAINT `fk_remember_tokens_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `template_fields`
--
ALTER TABLE `template_fields`
  ADD CONSTRAINT `fk_template_fields` FOREIGN KEY (`template_id`) REFERENCES `letter_templates` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
