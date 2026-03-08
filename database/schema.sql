-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Anamakine: 127.0.0.1
-- Üretim Zamanı: 07 Mar 2026, 00:57:48
-- Sunucu sürümü: 10.4.32-MariaDB
-- PHP Sürümü: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Veritabanı: `webey_local`
--

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `admin_users`
--

CREATE TABLE `admin_users` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `onboarding_completed` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Tablo döküm verisi `admin_users`
--

INSERT INTO `admin_users` (`id`, `user_id`, `onboarding_completed`, `created_at`) VALUES
(1, 104, 1, '2026-03-05 02:32:23'),
(2, 106, 1, '2026-03-05 02:39:19'),
(3, 107, 1, '2026-03-05 04:45:07');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `api_rate_limits`
--

CREATE TABLE `api_rate_limits` (
  `cache_key` varchar(200) NOT NULL,
  `hits` smallint(5) UNSIGNED NOT NULL DEFAULT 1,
  `expires_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='IP tabanlı rate limiting. Cron ile temizlenir.';

--
-- Tablo döküm verisi `api_rate_limits`
--

INSERT INTO `api_rate_limits` (`cache_key`, `hits`, `expires_at`) VALUES
('book:837ec5754f503cfaaee0929fd48974e7', 1, '2026-03-07 02:49:20'),
('lock:837ec5754f503cfaaee0929fd48974e7', 1, '2026-03-07 02:49:17');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `appointments`
--

CREATE TABLE `appointments` (
  `id` int(10) UNSIGNED NOT NULL,
  `business_id` int(10) UNSIGNED NOT NULL,
  `staff_id` int(10) UNSIGNED DEFAULT NULL,
  `service_id` int(10) UNSIGNED DEFAULT NULL,
  `customer_name` varchar(100) NOT NULL,
  `customer_phone` varchar(20) DEFAULT NULL,
  `customer_email` varchar(191) DEFAULT NULL,
  `customer_user_id` int(10) UNSIGNED DEFAULT NULL,
  `start_at` datetime NOT NULL,
  `end_at` datetime NOT NULL,
  `status` enum('pending','approved','cancelled','no_show','completed','rejected','declined','cancellation_requested') NOT NULL DEFAULT 'pending',
  `attended` tinyint(1) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `booking_source` enum('web','app','admin','phone','api') NOT NULL DEFAULT 'web' COMMENT 'Randevunun oluşturulduğu kanal',
  `reminder_24h_sent` tinyint(1) NOT NULL DEFAULT 0,
  `reminder_1h_sent` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Tablo döküm verisi `appointments`
--

INSERT INTO `appointments` (`id`, `business_id`, `staff_id`, `service_id`, `customer_name`, `customer_phone`, `customer_email`, `customer_user_id`, `start_at`, `end_at`, `status`, `attended`, `notes`, `booking_source`, `reminder_24h_sent`, `reminder_1h_sent`, `created_at`, `updated_at`) VALUES
(1, 44, 81, NULL, 'sdsda sdsad', '5454345443', NULL, NULL, '2026-03-05 14:30:00', '2026-03-05 15:00:00', 'cancelled', NULL, NULL, 'web', 0, 0, '2026-03-05 04:51:33', '2026-03-05 12:07:59'),
(2, 44, 82, NULL, 'abdullah arat', '5462924044', NULL, NULL, '2026-03-05 14:30:00', '2026-03-05 15:00:00', 'approved', NULL, NULL, 'web', 0, 0, '2026-03-05 05:16:01', '2026-03-05 07:16:38'),
(3, 44, 83, NULL, 'Abdullah sadasd', '5645454543', NULL, NULL, '2026-03-05 15:30:00', '2026-03-05 16:00:00', 'cancelled', NULL, NULL, 'web', 0, 0, '2026-03-05 06:17:39', '2026-03-05 12:07:56'),
(4, 44, 83, NULL, 'abdullah abd', '5454534435', NULL, NULL, '2026-03-11 15:45:00', '2026-03-11 16:15:00', 'approved', NULL, NULL, 'web', 0, 0, '2026-03-05 06:23:50', '2026-03-05 08:24:59'),
(5, 44, 80, NULL, 'Ensar sad', '5445343544', NULL, NULL, '2026-03-05 15:30:00', '2026-03-05 16:00:00', 'approved', NULL, NULL, 'web', 0, 0, '2026-03-05 10:03:08', '2026-03-05 12:03:47'),
(6, 44, 80, NULL, 'Abdullah sda', '5544534343', NULL, NULL, '2026-03-05 16:30:00', '2026-03-05 17:00:00', 'approved', NULL, NULL, 'web', 0, 0, '2026-03-05 10:06:51', '2026-03-05 12:32:04'),
(7, 44, 80, NULL, 'Abdullah sda', '5544534343', NULL, NULL, '2026-03-05 18:15:00', '2026-03-05 18:45:00', 'approved', NULL, NULL, 'web', 0, 0, '2026-03-05 10:10:35', '2026-03-05 12:10:46'),
(8, 44, 81, NULL, 'Abdullah sda', '5544534343', NULL, 114, '2026-03-05 18:15:00', '2026-03-05 18:45:00', 'approved', NULL, NULL, 'web', 0, 0, '2026-03-05 10:17:19', '2026-03-06 15:51:30'),
(9, 44, 83, NULL, 'Abdullah sda', '5544534343', NULL, 114, '2026-03-09 13:30:00', '2026-03-09 14:00:00', 'cancelled', NULL, NULL, 'web', 0, 0, '2026-03-05 10:18:12', '2026-03-05 12:18:50'),
(10, 44, 81, NULL, 'Abdullah sda', '5544534343', NULL, 114, '2026-03-09 15:45:00', '2026-03-09 16:15:00', 'cancelled', NULL, NULL, 'web', 0, 0, '2026-03-05 10:32:23', '2026-03-05 12:32:39'),
(11, 44, NULL, NULL, 'deneme arat', '5555555555', NULL, 103, '2026-03-11 13:15:00', '2026-03-11 13:45:00', 'approved', NULL, NULL, 'web', 0, 0, '2026-03-06 01:54:53', '2026-03-06 15:51:28'),
(12, 44, 80, NULL, 'Abdullah Beşir Arat', '5434543543', NULL, 115, '2026-03-06 15:30:00', '2026-03-06 16:00:00', 'no_show', 0, NULL, 'web', 0, 0, '2026-03-06 13:02:06', '2026-03-06 17:40:26'),
(13, 44, 81, NULL, 'Abdullah Beşir Arat', '5434543543', NULL, 115, '2026-03-06 15:15:00', '2026-03-06 15:45:00', 'approved', 1, NULL, 'web', 0, 0, '2026-03-06 13:02:17', '2026-03-06 17:40:24'),
(14, 44, 80, NULL, 'Abdullah Beşir Arat', '5434543543', NULL, 115, '2026-03-06 18:15:00', '2026-03-06 18:45:00', 'approved', NULL, NULL, 'web', 0, 0, '2026-03-06 13:50:13', '2026-03-06 15:51:11'),
(15, 44, 80, 245, 'dsaasdas asddasdsa', '5525434454', NULL, 116, '2026-03-09 14:30:00', '2026-03-09 15:00:00', 'approved', NULL, NULL, 'web', 0, 0, '2026-03-06 14:47:34', '2026-03-06 16:47:48'),
(16, 44, 82, 245, 'dsaasdas asddasdsa', '5525434454', NULL, 116, '2026-03-09 15:30:00', '2026-03-09 16:00:00', 'approved', NULL, NULL, 'web', 0, 0, '2026-03-06 14:47:56', '2026-03-06 16:50:31'),
(17, 44, 82, 248, 'dsaasdas asddasdsa', '5525434454', NULL, 116, '2026-03-10 12:15:00', '2026-03-10 12:55:00', 'approved', NULL, NULL, 'web', 0, 0, '2026-03-06 14:50:18', '2026-03-06 16:50:29'),
(18, 44, NULL, 245, 'dsaasdas asddasdsa', '5525434454', NULL, 116, '2026-03-10 13:30:00', '2026-03-10 14:00:00', 'approved', NULL, NULL, 'web', 0, 0, '2026-03-06 15:01:36', '2026-03-06 17:04:02'),
(19, 44, 81, 246, 'dsaasdas asddasdsa', '5525434454', NULL, 116, '2026-03-10 13:15:00', '2026-03-10 14:00:00', 'approved', NULL, NULL, 'web', 0, 0, '2026-03-06 15:01:52', '2026-03-06 17:04:01'),
(20, 44, 81, 247, 'dsaasdas asddasdsa', '5525434454', NULL, 116, '2026-03-10 11:15:00', '2026-03-10 11:40:00', 'approved', NULL, NULL, 'web', 0, 0, '2026-03-06 15:02:48', '2026-03-06 17:03:59'),
(21, 44, 81, 245, 'dsaasdas asddasdsa', '5525434454', NULL, 116, '2026-03-07 10:00:00', '2026-03-07 10:30:00', 'approved', NULL, NULL, 'web', 0, 0, '2026-03-06 15:07:00', '2026-03-06 17:08:03'),
(22, 44, 80, 245, 'dsaasdas asddasdsa', '5525434454', NULL, 116, '2026-03-07 13:30:00', '2026-03-07 14:00:00', 'pending', NULL, NULL, 'web', 0, 0, '2026-03-06 15:22:32', '2026-03-06 17:22:32'),
(23, 44, 80, 245, 'dsaasdas asddasdsa', '5525434454', NULL, 116, '2026-03-12 14:30:00', '2026-03-12 15:00:00', 'approved', NULL, NULL, 'web', 0, 0, '2026-03-06 15:24:25', '2026-03-06 23:43:48'),
(24, 44, 80, 245, 'dsaasdas asddasdsa', '5525434454', NULL, 116, '2026-03-12 13:30:00', '2026-03-12 14:00:00', 'approved', NULL, NULL, 'web', 0, 0, '2026-03-06 15:28:17', '2026-03-06 17:31:10'),
(25, 44, 82, 247, 'dsaasdas asddasdsa', '5525434454', NULL, 116, '2026-03-12 12:15:00', '2026-03-12 12:40:00', 'cancelled', NULL, NULL, 'web', 0, 0, '2026-03-06 15:31:31', '2026-03-06 17:31:40'),
(26, 44, 82, 245, 'dsaasdas asddasdsa', '5525434454', NULL, 116, '2026-03-12 11:15:00', '2026-03-12 12:55:00', 'cancelled', NULL, 'Çoklu hizmet: Saç-Sakal kesimi, Saç + Sakal, Çocuk Kesimi', 'web', 0, 0, '2026-03-06 15:32:09', '2026-03-06 17:32:40'),
(27, 44, 83, 246, 'dsaasdas asddasdsa', '5525434454', NULL, 116, '2026-03-13 14:30:00', '2026-03-13 15:15:00', 'approved', NULL, NULL, 'web', 0, 0, '2026-03-06 15:35:05', '2026-03-06 17:36:02');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `appointment_logs`
--

CREATE TABLE `appointment_logs` (
  `id` int(10) UNSIGNED NOT NULL,
  `appointment_id` int(10) UNSIGNED NOT NULL,
  `action` varchar(50) NOT NULL,
  `prev_status` varchar(50) DEFAULT NULL,
  `new_status` varchar(50) DEFAULT NULL,
  `actor_user_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'İşlemi yapan user (null = müşteri)',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Tablo döküm verisi `appointment_logs`
--

INSERT INTO `appointment_logs` (`id`, `appointment_id`, `action`, `prev_status`, `new_status`, `actor_user_id`, `created_at`) VALUES
(1, 6, 'cancellation_requested', 'approved', 'cancellation_requested', NULL, '2026-03-05 12:31:51'),
(2, 13, 'cancellation_requested', NULL, 'cancellation_requested', NULL, '2026-03-06 15:14:30');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `appointment_reminders`
--

CREATE TABLE `appointment_reminders` (
  `id` int(10) UNSIGNED NOT NULL,
  `appointment_id` int(10) UNSIGNED NOT NULL,
  `channel` enum('email','sms') NOT NULL DEFAULT 'email',
  `remind_before` tinyint(3) UNSIGNED NOT NULL DEFAULT 24 COMMENT 'Kaç saat önce (24 veya 1)',
  `sent_at` datetime DEFAULT NULL,
  `status` enum('pending','sent','failed') NOT NULL DEFAULT 'pending',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `businesses`
--

CREATE TABLE `businesses` (
  `id` int(10) UNSIGNED NOT NULL,
  `owner_id` int(10) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL DEFAULT '',
  `slug` varchar(120) DEFAULT NULL,
  `owner_name` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `type` varchar(50) NOT NULL DEFAULT 'kuafor',
  `status` enum('draft','pending','active','rejected','suspended') NOT NULL DEFAULT 'draft',
  `city` varchar(80) DEFAULT NULL,
  `district` varchar(80) DEFAULT NULL,
  `address_line` varchar(300) DEFAULT NULL,
  `about` text DEFAULT NULL,
  `min_price` smallint(5) UNSIGNED DEFAULT NULL,
  `max_price` smallint(5) UNSIGNED DEFAULT NULL,
  `map_url` varchar(500) DEFAULT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `building_no` varchar(20) DEFAULT NULL,
  `neighborhood` varchar(100) DEFAULT NULL,
  `images_json` mediumtext DEFAULT NULL,
  `staff_hours` tinyint(1) DEFAULT 0,
  `onboarding_step` tinyint(4) NOT NULL DEFAULT 1,
  `onboarding_completed` tinyint(1) NOT NULL DEFAULT 0,
  `rejected_at` datetime DEFAULT NULL,
  `reject_reason` text DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `draft_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`draft_data`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Tablo döküm verisi `businesses`
--

INSERT INTO `businesses` (`id`, `owner_id`, `name`, `slug`, `owner_name`, `phone`, `type`, `status`, `city`, `district`, `address_line`, `about`, `min_price`, `max_price`, `map_url`, `latitude`, `longitude`, `building_no`, `neighborhood`, `images_json`, `staff_hours`, `onboarding_step`, `onboarding_completed`, `rejected_at`, `reject_reason`, `approved_at`, `created_at`, `updated_at`, `draft_data`) VALUES
(42, 104, 'abcd', NULL, 'abcd', '5544344353', 'kuafor', 'active', 'Afyonkarahisar', 'Çay', 'Ali Kaleli Mah. dasds No: 12, Çay/Afyonkarahisar', NULL, NULL, NULL, NULL, NULL, NULL, '12', 'Ali Kaleli', NULL, 0, 7, 1, NULL, NULL, NULL, '2026-03-05 02:32:33', '2026-03-05 02:32:56', NULL),
(43, 106, 'arat', NULL, 'arat', '5455434345', 'kuafor', 'active', 'Ağrı', 'Eleşkirt', 'Değirmengeçidi Mah. sddsaasd No: 12, Eleşkirt/Ağrı', NULL, NULL, NULL, NULL, NULL, NULL, '12', 'Değirmengeçidi', NULL, 0, 7, 1, NULL, NULL, NULL, '2026-03-05 02:39:44', '2026-03-05 02:40:03', NULL),
(44, 107, 'berber', NULL, 'berber', '5344444444', 'kuafor', 'active', 'Afyonkarahisar', 'Bolvadin', 'Bağlarüstü No:12, Bolvadin/Afyonkarahisar', 'asdasdasdsdaasd', NULL, NULL, '', NULL, NULL, '12', 'Bağlarüstü', '{\"cover\":[\"uploads\\/biz\\/44\\/cover_69aaced252c9a.jpg\"],\"cover_opt\":[\"uploads\\/biz\\/44\\/opt_cover_69aaced252c9a.webp\"],\"salon\":[\"uploads\\/biz\\/44\\/salon_69aaced985da4.jpg\"],\"salon_opt\":[\"uploads\\/biz\\/44\\/opt_salon_69aaced985da4.webp\"],\"model\":[\"uploads\\/biz\\/44\\/model_69aacee0632f8.jpg\"],\"model_opt\":[\"uploads\\/biz\\/44\\/opt_model_69aacee0632f8.webp\"]}', 0, 7, 1, NULL, NULL, NULL, '2026-03-05 04:45:19', '2026-03-06 15:56:21', NULL);

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `business_hours`
--

CREATE TABLE `business_hours` (
  `id` int(10) UNSIGNED NOT NULL,
  `business_id` int(10) UNSIGNED NOT NULL,
  `day` enum('mon','tue','wed','thu','fri','sat','sun') NOT NULL,
  `is_open` tinyint(1) NOT NULL DEFAULT 1,
  `open_time` time DEFAULT NULL,
  `close_time` time DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Tablo döküm verisi `business_hours`
--

INSERT INTO `business_hours` (`id`, `business_id`, `day`, `is_open`, `open_time`, `close_time`) VALUES
(617, 42, 'mon', 1, '10:00:00', '19:00:00'),
(618, 42, 'tue', 1, '10:00:00', '19:00:00'),
(619, 42, 'wed', 1, '10:00:00', '19:00:00'),
(620, 42, 'thu', 1, '10:00:00', '19:00:00'),
(621, 42, 'fri', 1, '10:00:00', '19:00:00'),
(622, 42, 'sat', 0, NULL, NULL),
(623, 42, 'sun', 0, NULL, NULL),
(624, 43, 'mon', 1, '10:00:00', '19:00:00'),
(625, 43, 'tue', 1, '10:00:00', '19:00:00'),
(626, 43, 'wed', 1, '10:00:00', '19:00:00'),
(627, 43, 'thu', 1, '10:00:00', '19:00:00'),
(628, 43, 'fri', 1, '10:00:00', '19:00:00'),
(629, 43, 'sat', 0, NULL, NULL),
(630, 43, 'sun', 0, NULL, NULL),
(645, 44, 'mon', 1, '10:00:00', '19:00:00'),
(646, 44, 'tue', 1, '10:00:00', '19:00:00'),
(647, 44, 'wed', 1, '10:00:00', '19:00:00'),
(648, 44, 'thu', 1, '10:00:00', '19:00:00'),
(649, 44, 'fri', 1, '10:00:00', '19:00:00'),
(650, 44, 'sat', 1, '10:00:00', '19:00:00'),
(651, 44, 'sun', 0, NULL, NULL);

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `csrf_tokens`
--

CREATE TABLE `csrf_tokens` (
  `id` int(10) UNSIGNED NOT NULL,
  `session_id` varchar(128) NOT NULL,
  `token` varchar(64) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `expires_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Her oturum için CSRF token. 2 saat ömürlü, cron ile temizlenir.';

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `customers`
--

CREATE TABLE `customers` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `first_name` varchar(80) DEFAULT NULL,
  `last_name` varchar(80) DEFAULT NULL,
  `phone` varchar(15) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `birthday` date DEFAULT NULL,
  `city` varchar(80) DEFAULT NULL,
  `district` varchar(80) DEFAULT NULL,
  `neighborhood` varchar(100) DEFAULT NULL,
  `sms_ok` tinyint(1) NOT NULL DEFAULT 1,
  `email_ok` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Tablo döküm verisi `customers`
--

INSERT INTO `customers` (`id`, `user_id`, `first_name`, `last_name`, `phone`, `email`, `birthday`, `city`, `district`, `neighborhood`, `sms_ok`, `email_ok`, `created_at`, `updated_at`) VALUES
(48, 101, 'abdullah', 'arat', '5462924044', NULL, NULL, 'Bartın', 'Amasra', 'Fatih', 1, 0, '2026-03-04 19:00:37', '2026-03-04 19:00:37'),
(49, 102, 'sdasda', 'sdsd', '5454544563', NULL, NULL, 'Batman', 'Hasankeyf', 'Eyyubi', 1, 0, '2026-03-04 20:01:39', '2026-03-04 20:01:39'),
(50, 103, 'deneme', 'arat', '5555555555', 'reckless5677@gmail.com', '2003-12-22', 'Artvin', 'Borçka', 'Gündoğdu', 1, 0, '2026-03-04 20:04:21', '2026-03-04 20:04:55'),
(52, 108, 'sdsda', 'sdsad', '5454345443', NULL, '2011-03-05', 'Batman', 'Gercüş', 'Pınarbaşı', 1, 0, '2026-03-05 06:51:32', '2026-03-05 06:51:32'),
(53, 109, 'Abdullah', 'sadasd', '5645454543', NULL, '1984-11-13', 'Balıkesir', 'Karesi', 'Bakacak', 1, 0, '2026-03-05 08:17:03', '2026-03-05 08:17:03'),
(54, 110, 'abdullah', 'abd', '5454534435', NULL, '1992-12-24', 'Bilecik', 'İnhisar', 'Akkum', 1, 0, '2026-03-05 08:22:33', '2026-03-05 08:22:33'),
(55, 111, 'abdullah', 'dsdsa', '5454534454', NULL, '2004-09-29', 'Bayburt', 'Demirözü', 'Esentepe', 1, 0, '2026-03-05 08:24:50', '2026-03-05 08:24:50'),
(56, 112, 'abdullah', 'arat', '5454345444', NULL, '1991-12-12', 'Balıkesir', 'Manyas', 'Değirmenboğazı', 1, 0, '2026-03-05 08:26:45', '2026-03-05 08:26:45'),
(57, 113, 'Ensar', 'sad', '5445343544', NULL, '2011-10-05', 'Batman', 'Gercüş', 'Pınarbaşı', 1, 0, '2026-03-05 12:02:42', '2026-03-05 12:02:42'),
(58, 114, 'Abdullah', 'sda', '5544534343', NULL, '2009-03-05', 'Batman', 'Gercüş', 'Çukurçeşme', 1, 0, '2026-03-05 12:05:49', '2026-03-05 12:05:49'),
(59, 115, 'Abdullah Beşir', 'Arat', '5434543543', NULL, '2003-03-06', 'Ankara', 'Çamlıdere', 'Buğralar', 1, 0, '2026-03-06 15:02:05', '2026-03-06 15:02:05'),
(60, 116, 'dsaasdas', 'asddasdsa', '5525434454', NULL, '2009-03-06', 'Aydın', 'Koçarlı', 'Büyükdere', 1, 0, '2026-03-06 16:44:35', '2026-03-06 16:44:35'),
(61, 117, 'Abdullah', 'Beşir', '5333333333', NULL, '2012-12-26', 'Artvin', 'Hopa', 'Cumhuriyet', 1, 0, '2026-03-06 20:26:24', '2026-03-06 20:26:24'),
(62, 118, 'sasdsad', 'sdasda', '5545434343', NULL, '2009-03-06', 'Artvin', 'Borçka', 'Küçük Köy', 1, 0, '2026-03-06 20:52:05', '2026-03-06 20:52:05');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `email_queue`
--

CREATE TABLE `email_queue` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `to_email` varchar(255) NOT NULL,
  `to_name` varchar(100) DEFAULT NULL,
  `subject` varchar(300) NOT NULL,
  `body_html` mediumtext NOT NULL,
  `status` enum('pending','sent','failed') NOT NULL DEFAULT 'pending',
  `attempts` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `last_error` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `scheduled_at` datetime DEFAULT NULL COMMENT 'NULL = hemen gönder',
  `sent_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Async email kuyruğu. cron_send_emails.php tarafından işlenir.';

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `favorites`
--

CREATE TABLE `favorites` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `business_id` int(10) UNSIGNED NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Tablo döküm verisi `favorites`
--

INSERT INTO `favorites` (`id`, `user_id`, `business_id`, `created_at`) VALUES
(12, 101, 44, '2026-03-05 07:16:18');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `invoices`
--

CREATE TABLE `invoices` (
  `id` int(10) UNSIGNED NOT NULL,
  `subscription_id` int(10) UNSIGNED DEFAULT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `plan_label` varchar(60) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `status` enum('pending','paid','failed','refunded') NOT NULL DEFAULT 'pending',
  `iyzico_payment_id` varchar(100) DEFAULT NULL,
  `pdf_url` varchar(300) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `paid_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Tablo döküm verisi `invoices`
--

INSERT INTO `invoices` (`id`, `subscription_id`, `user_id`, `plan_label`, `amount`, `status`, `iyzico_payment_id`, `pdf_url`, `created_at`, `paid_at`) VALUES
(2, 2, 107, '3 Aylık Plan', 0.00, 'paid', NULL, NULL, '2026-03-05 05:02:19', NULL),
(3, 3, 107, '6 Aylık Plan', 0.00, 'paid', NULL, NULL, '2026-03-05 05:30:10', NULL),
(4, 4, 107, '1 Yıllık Plan', 0.00, 'paid', NULL, NULL, '2026-03-05 05:31:25', NULL),
(5, 5, 107, '2 Yıllık Plan', 0.00, 'paid', NULL, NULL, '2026-03-05 05:50:00', NULL),
(6, 6, 107, '1 Aylık Plan', 0.00, 'paid', NULL, NULL, '2026-03-05 07:13:30', NULL),
(7, 7, 107, '1 Yıllık Plan', 0.00, 'paid', NULL, NULL, '2026-03-05 07:38:19', NULL);

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `login_attempts`
--

CREATE TABLE `login_attempts` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `ip` varchar(45) NOT NULL,
  `attempted_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `notifications`
--

CREATE TABLE `notifications` (
  `id` int(10) UNSIGNED NOT NULL,
  `business_id` int(10) UNSIGNED NOT NULL,
  `appointment_id` int(10) UNSIGNED DEFAULT NULL,
  `type` enum('booking','cancellation','subscription_expiry_3d','subscription_expiry_1d','subscription_expired') NOT NULL DEFAULT 'booking',
  `customer_name` varchar(100) NOT NULL DEFAULT '',
  `customer_phone` varchar(30) DEFAULT NULL,
  `service_name` varchar(100) DEFAULT NULL,
  `staff_name` varchar(100) DEFAULT NULL,
  `appointment_start` datetime DEFAULT NULL,
  `result` enum('pending','approved','rejected','cancelled','cancel_approved','cancel_rejected','info') NOT NULL DEFAULT 'pending',
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `read_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Tablo döküm verisi `notifications`
--

INSERT INTO `notifications` (`id`, `business_id`, `appointment_id`, `type`, `customer_name`, `customer_phone`, `service_name`, `staff_name`, `appointment_start`, `result`, `is_read`, `is_deleted`, `read_at`, `deleted_at`, `created_at`) VALUES
(41, 44, 1, 'booking', 'sdsda sdsad', '5454345443', 'Saç-Sakal kesimi', 'sadsad', '2026-03-05 14:30:00', 'pending', 1, 0, '2026-03-05 06:53:49', NULL, '2026-03-05 06:51:33'),
(42, 44, 2, 'booking', 'abdullah arat', '5462924044', 'Saç-Sakal kesimi', 'ahmet çamurcu', '2026-03-05 14:30:00', 'pending', 1, 0, '2026-03-05 07:16:38', NULL, '2026-03-05 07:16:01'),
(43, 44, 3, 'booking', 'Abdullah sadasd', '5645454543', 'Saç-Sakal kesimi', 'dssdsaf', '2026-03-05 15:30:00', 'pending', 1, 0, '2026-03-05 12:09:16', NULL, '2026-03-05 08:17:39'),
(44, 44, 4, 'booking', 'abdullah abd', '5454534435', 'Saç-Sakal kesimi', 'dssdsaf', '2026-03-11 15:45:00', 'pending', 1, 0, '2026-03-05 12:09:17', NULL, '2026-03-05 08:23:50'),
(45, 44, 5, 'booking', 'Ensar sad', '5445343544', 'Saç-Sakal kesimi', 'berber', '2026-03-05 15:30:00', 'pending', 1, 0, '2026-03-05 12:09:17', NULL, '2026-03-05 12:03:08'),
(46, 44, 6, 'booking', 'Abdullah sda', '5544534343', 'Saç-Sakal kesimi', 'berber', '2026-03-05 16:30:00', 'pending', 1, 0, '2026-03-05 12:09:18', NULL, '2026-03-05 12:06:51'),
(47, 44, 7, 'booking', 'Abdullah sda', '5544534343', 'Saç-Sakal kesimi', 'berber', '2026-03-05 18:15:00', 'pending', 1, 0, '2026-03-06 15:51:31', NULL, '2026-03-05 12:10:35'),
(48, 44, 8, 'booking', 'Abdullah sda', '5544534343', 'Saç-Sakal kesimi', 'sadsad', '2026-03-05 18:15:00', 'pending', 1, 0, '2026-03-06 15:51:30', NULL, '2026-03-05 12:17:19'),
(49, 44, 9, 'booking', 'Abdullah sda', '5544534343', 'Saç-Sakal kesimi', 'dssdsaf', '2026-03-09 13:30:00', 'pending', 1, 0, '2026-03-05 12:18:50', NULL, '2026-03-05 12:18:12'),
(50, 44, 10, 'booking', 'Abdullah sda', '5544534343', 'Saç-Sakal kesimi', 'sadsad', '2026-03-09 15:45:00', 'pending', 1, 0, '2026-03-05 12:32:39', NULL, '2026-03-05 12:32:23'),
(51, 44, 11, 'booking', 'deneme arat', '5555555555', 'Saç-Sakal kesimi', NULL, '2026-03-11 13:15:00', 'pending', 1, 0, '2026-03-06 15:51:28', NULL, '2026-03-06 03:54:53'),
(52, 44, 12, 'booking', 'Abdullah Beşir Arat', '5434543543', 'Saç-Sakal kesimi', 'berber', '2026-03-06 15:30:00', 'pending', 1, 0, '2026-03-06 15:51:28', NULL, '2026-03-06 15:02:06'),
(53, 44, 13, 'booking', 'Abdullah Beşir Arat', '5434543543', 'Saç-Sakal kesimi', 'sadsad', '2026-03-06 15:15:00', 'pending', 1, 0, '2026-03-06 15:51:27', NULL, '2026-03-06 15:02:17'),
(54, 44, 14, 'booking', 'Abdullah Beşir Arat', '5434543543', 'Saç-Sakal kesimi', 'berber', '2026-03-06 18:15:00', 'pending', 1, 0, '2026-03-06 15:51:26', NULL, '2026-03-06 15:50:13'),
(55, 44, 15, 'booking', 'dsaasdas asddasdsa', '5525434454', 'Saç-Sakal kesimi', 'berber', '2026-03-09 14:30:00', 'pending', 0, 0, NULL, NULL, '2026-03-06 16:47:34'),
(56, 44, 16, 'booking', 'dsaasdas asddasdsa', '5525434454', 'Saç-Sakal kesimi', 'ahmet çamurcu', '2026-03-09 15:30:00', 'pending', 0, 0, NULL, NULL, '2026-03-06 16:47:56'),
(57, 44, 17, 'booking', 'dsaasdas asddasdsa', '5525434454', 'Makas Kesim', 'ahmet çamurcu', '2026-03-10 12:15:00', 'pending', 0, 0, NULL, NULL, '2026-03-06 16:50:18'),
(58, 44, 18, 'booking', 'dsaasdas asddasdsa', '5525434454', 'Saç-Sakal kesimi', NULL, '2026-03-10 13:30:00', 'pending', 0, 0, NULL, NULL, '2026-03-06 17:01:36'),
(59, 44, 19, 'booking', 'dsaasdas asddasdsa', '5525434454', 'Saç + Sakal', 'sadsad', '2026-03-10 13:15:00', 'pending', 0, 0, NULL, NULL, '2026-03-06 17:01:52'),
(60, 44, 20, 'booking', 'dsaasdas asddasdsa', '5525434454', 'Çocuk Kesimi', 'sadsad', '2026-03-10 11:15:00', 'pending', 0, 0, NULL, NULL, '2026-03-06 17:02:48'),
(61, 44, 21, 'booking', 'dsaasdas asddasdsa', '5525434454', 'Saç-Sakal kesimi', 'sadsad', '2026-03-07 10:00:00', 'pending', 0, 0, NULL, NULL, '2026-03-06 17:07:00'),
(62, 44, 22, 'booking', 'dsaasdas asddasdsa', '5525434454', 'Saç-Sakal kesimi', 'berber', '2026-03-07 13:30:00', 'pending', 0, 0, NULL, NULL, '2026-03-06 17:22:32'),
(63, 44, 23, 'booking', 'dsaasdas asddasdsa', '5525434454', 'Saç-Sakal kesimi', 'berber', '2026-03-12 14:30:00', 'pending', 0, 0, NULL, NULL, '2026-03-06 17:24:25'),
(64, 44, 24, 'booking', 'dsaasdas asddasdsa', '5525434454', 'Saç-Sakal kesimi', 'berber', '2026-03-12 13:30:00', 'pending', 0, 0, NULL, NULL, '2026-03-06 17:28:17'),
(65, 44, 25, 'booking', 'dsaasdas asddasdsa', '5525434454', 'Çocuk Kesimi', 'ahmet çamurcu', '2026-03-12 12:15:00', 'pending', 0, 0, NULL, NULL, '2026-03-06 17:31:31'),
(66, 44, 26, 'booking', 'dsaasdas asddasdsa', '5525434454', 'Saç-Sakal kesimi', 'ahmet çamurcu', '2026-03-12 11:15:00', 'pending', 0, 0, NULL, NULL, '2026-03-06 17:32:09'),
(67, 44, 27, 'booking', 'dsaasdas asddasdsa', '5525434454', 'Saç + Sakal', 'dssdsaf', '2026-03-13 14:30:00', 'pending', 0, 0, NULL, NULL, '2026-03-06 17:35:05');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `otp_tokens`
--

CREATE TABLE `otp_tokens` (
  `id` int(10) UNSIGNED NOT NULL,
  `phone` varchar(20) NOT NULL,
  `code` char(6) NOT NULL,
  `purpose` enum('register','login','phone_change') NOT NULL DEFAULT 'register',
  `attempts` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='SMS OTP doğrulama token''ları. 5 dakika TTL.';

--
-- Tablo döküm verisi `otp_tokens`
--

INSERT INTO `otp_tokens` (`id`, `phone`, `code`, `purpose`, `attempts`, `expires_at`, `used_at`, `ip`, `created_at`) VALUES
(5, '5462924044', '$2y$10', 'register', 0, '2026-03-04 17:04:54', NULL, '::1', '2026-03-04 18:59:54'),
(6, '5454544563', '$2y$10', 'register', 0, '2026-03-04 18:06:17', NULL, '::1', '2026-03-04 20:01:18'),
(7, '5555555555', '$2y$10', 'register', 0, '2026-03-04 18:08:56', NULL, '::1', '2026-03-04 20:03:56'),
(8, '5454345443', '$2y$10', 'register', 0, '2026-03-05 04:56:14', NULL, '::1', '2026-03-05 06:51:14'),
(9, '5645454543', '$2y$10', 'register', 0, '2026-03-05 06:20:51', NULL, '::1', '2026-03-05 08:15:51'),
(10, '5454534435', '$2y$10', 'register', 0, '2026-03-05 06:25:35', NULL, '::1', '2026-03-05 08:20:35'),
(11, '5454534454', '$2y$10', 'register', 0, '2026-03-05 06:29:04', NULL, '::1', '2026-03-05 08:24:04'),
(12, '5454345444', '$2y$10', 'register', 0, '2026-03-05 06:31:07', NULL, '::1', '2026-03-05 08:26:07'),
(13, '5445343544', '$2y$10', 'register', 0, '2026-03-05 10:07:21', NULL, '::1', '2026-03-05 12:02:21'),
(15, '5544534343', '$2y$10', 'register', 0, '2026-03-05 10:10:32', NULL, '::1', '2026-03-05 12:05:32'),
(16, '5533434324', '$2y$10', 'register', 0, '2026-03-05 11:14:00', NULL, '::1', '2026-03-05 13:09:00'),
(17, '5434543543', '$2y$10', 'register', 0, '2026-03-06 13:06:35', NULL, '::1', '2026-03-06 15:01:35'),
(19, '5525434454', '$2y$10', 'register', 0, '2026-03-06 14:49:16', NULL, '::1', '2026-03-06 16:44:16'),
(20, '5333333333', '$2y$10', 'register', 0, '2026-03-06 18:30:59', NULL, '::1', '2026-03-06 20:25:59'),
(22, '5545434343', '$2y$10', 'register', 0, '2026-03-06 18:56:47', NULL, '::1', '2026-03-06 20:51:47');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `payment_cards`
--

CREATE TABLE `payment_cards` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `iyzico_card_token` varchar(200) NOT NULL,
  `card_brand` varchar(30) DEFAULT NULL COMMENT 'Visa, Mastercard, Troy, Amex',
  `card_last4` varchar(4) DEFAULT NULL,
  `expire_month` varchar(2) DEFAULT NULL,
  `expire_year` varchar(4) DEFAULT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `deleted_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `promo_codes`
--

CREATE TABLE `promo_codes` (
  `id` int(10) UNSIGNED NOT NULL,
  `code` varchar(32) NOT NULL,
  `plan` varchar(30) DEFAULT NULL,
  `discount_type` enum('free','percent','fixed') NOT NULL DEFAULT 'free',
  `discount_value` decimal(10,2) NOT NULL DEFAULT 100.00,
  `max_uses` int(10) UNSIGNED DEFAULT NULL,
  `used_count` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `expires_at` datetime DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `note` varchar(255) DEFAULT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Tablo döküm verisi `promo_codes`
--

INSERT INTO `promo_codes` (`id`, `code`, `plan`, `discount_type`, `discount_value`, `max_uses`, `used_count`, `expires_at`, `is_active`, `note`, `created_by`, `created_at`, `updated_at`) VALUES
(7, 'DJM9ATSF', 'yearly_1', 'free', 100.00, NULL, 1, '2027-03-05 07:36:00', 1, 'berber dükkanı için kod', 106, '2026-03-05 07:37:31', '2026-03-05 07:38:19');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `promo_code_uses`
--

CREATE TABLE `promo_code_uses` (
  `id` int(10) UNSIGNED NOT NULL,
  `promo_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `subscription_id` int(10) UNSIGNED DEFAULT NULL,
  `used_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Tablo döküm verisi `promo_code_uses`
--

INSERT INTO `promo_code_uses` (`id`, `promo_id`, `user_id`, `subscription_id`, `used_at`) VALUES
(6, 7, 107, 7, '2026-03-05 07:38:19');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `push_subscriptions`
--

CREATE TABLE `push_subscriptions` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `endpoint` text NOT NULL,
  `p256dh` varchar(255) NOT NULL,
  `auth` varchar(255) NOT NULL,
  `user_agent` varchar(300) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `last_used_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `reviews`
--

CREATE TABLE `reviews` (
  `id` int(10) UNSIGNED NOT NULL,
  `business_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `appointment_id` int(10) UNSIGNED NOT NULL,
  `rating` tinyint(1) UNSIGNED NOT NULL,
  `comment` text DEFAULT NULL,
  `reply` text DEFAULT NULL,
  `reply_at` datetime DEFAULT NULL,
  `is_visible` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `services`
--

CREATE TABLE `services` (
  `id` int(10) UNSIGNED NOT NULL,
  `business_id` int(10) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `price` decimal(10,2) DEFAULT NULL,
  `duration_min` smallint(6) NOT NULL DEFAULT 30,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Tablo döküm verisi `services`
--

INSERT INTO `services` (`id`, `business_id`, `name`, `price`, `duration_min`, `created_at`) VALUES
(239, 42, 'Saç-Sakal kesimi', 12.00, 30, '2026-03-05 02:32:56'),
(240, 42, 'Uzun saç kesimi', 50.00, 30, '2026-03-05 02:32:56'),
(241, 43, 'Yıkama & fön', 12.00, 30, '2026-03-05 02:40:03'),
(242, 43, 'Uzun saç kesimi', 12.00, 30, '2026-03-05 02:40:03'),
(245, 44, 'Saç-Sakal kesimi', 10.00, 30, '2026-03-06 15:56:21'),
(246, 44, 'Saç + Sakal', 400.00, 45, '2026-03-06 15:56:21'),
(247, 44, 'Çocuk Kesimi', 200.00, 25, '2026-03-06 15:56:21'),
(248, 44, 'Makas Kesim', 350.00, 40, '2026-03-06 15:56:21'),
(249, 44, 'Sakal Kesimi', 150.00, 20, '2026-03-06 15:56:21'),
(250, 44, 'Keratin Bakımı', 800.00, 90, '2026-03-06 15:56:21');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `slot_locks`
--

CREATE TABLE `slot_locks` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `business_id` int(10) UNSIGNED NOT NULL,
  `staff_id` int(10) UNSIGNED DEFAULT NULL,
  `day_str` date NOT NULL,
  `start_min` smallint(6) NOT NULL,
  `duration_min` smallint(6) NOT NULL,
  `lock_token` varchar(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Tablo döküm verisi `slot_locks`
--

INSERT INTO `slot_locks` (`id`, `business_id`, `staff_id`, `day_str`, `start_min`, `duration_min`, `lock_token`, `expires_at`, `created_at`) VALUES
(33, 44, NULL, '2026-03-07', 870, 30, '92716df02a20edddc9da30ef256e1027aa8a09745d81c176', '2026-03-07 02:50:17', '2026-03-07 02:48:17');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `sms_queue`
--

CREATE TABLE `sms_queue` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `phone` varchar(15) NOT NULL COMMENT '905XXXXXXXXX formatı',
  `message` varchar(480) NOT NULL COMMENT 'Max 3 SMS uzunluğu (480 karakter)',
  `type` varchar(30) DEFAULT NULL COMMENT 'booking|approved|rejected|reminder_24h|reminder_1h',
  `appointment_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'İlgili randevu (opsiyonel)',
  `status` enum('pending','sent','failed') NOT NULL DEFAULT 'pending',
  `attempts` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `last_error` text DEFAULT NULL,
  `scheduled_at` datetime DEFAULT NULL COMMENT 'NULL = hemen gönder',
  `sent_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='SMS kuyruğu. cron_send_sms.php tarafından işlenir.';

--
-- Tablo döküm verisi `sms_queue`
--

INSERT INTO `sms_queue` (`id`, `phone`, `message`, `type`, `appointment_id`, `status`, `attempts`, `last_error`, `scheduled_at`, `sent_at`, `created_at`) VALUES
(1, '905454345443', 'Webey: berber işletmesine 05.03.2026 14:30 için randevunuz iletilmiştir. Onay için bekleyiniz.', 'booking', 1, 'pending', 0, NULL, NULL, NULL, '2026-03-05 06:51:35'),
(2, '905462924044', 'Webey: berber işletmesine 05.03.2026 14:30 için randevunuz iletilmiştir. Onay için bekleyiniz.', 'booking', 2, 'pending', 0, NULL, NULL, NULL, '2026-03-05 07:16:03'),
(3, '905645454543', 'Webey: berber işletmesine 05.03.2026 15:30 için randevunuz iletilmiştir. Onay için bekleyiniz.', 'booking', 3, 'pending', 0, NULL, NULL, NULL, '2026-03-05 08:17:41'),
(4, '905454534435', 'Webey: berber işletmesine 11.03.2026 15:45 için randevunuz iletilmiştir. Onay için bekleyiniz.', 'booking', 4, 'pending', 0, NULL, NULL, NULL, '2026-03-05 08:23:52'),
(5, '905445343544', 'Webey: berber işletmesine 05.03.2026 15:30 için randevunuz iletilmiştir. Onay için bekleyiniz.', 'booking', 5, 'pending', 0, NULL, NULL, NULL, '2026-03-05 12:03:10'),
(6, '905544534343', 'Webey: berber işletmesine 05.03.2026 16:30 için randevunuz iletilmiştir. Onay için bekleyiniz.', 'booking', 6, 'pending', 0, NULL, NULL, NULL, '2026-03-05 12:06:53'),
(7, '905544534343', 'Webey: berber işletmesine 05.03.2026 18:15 için randevunuz iletilmiştir. Onay için bekleyiniz.', 'booking', 7, 'pending', 0, NULL, NULL, NULL, '2026-03-05 12:10:37'),
(8, '905544534343', 'Webey: berber işletmesine 05.03.2026 18:15 için randevunuz iletilmiştir. Onay için bekleyiniz.', 'booking', 8, 'pending', 0, NULL, NULL, NULL, '2026-03-05 12:17:21'),
(9, '905544534343', 'Webey: berber işletmesine 09.03.2026 13:30 için randevunuz iletilmiştir. Onay için bekleyiniz.', 'booking', 9, 'pending', 0, NULL, NULL, NULL, '2026-03-05 12:18:14'),
(10, '905544534343', 'Webey: İptal talebiniz işletme tarafından reddedildi. Randevunuz devam etmektedir.', 'cancellation_rejected', 6, 'pending', 0, NULL, NULL, NULL, '2026-03-05 12:32:04'),
(11, '905544534343', 'Webey: berber işletmesine 09.03.2026 15:45 için randevunuz iletilmiştir. Onay için bekleyiniz.', 'booking', 10, 'pending', 0, NULL, NULL, NULL, '2026-03-05 12:32:25'),
(12, '905555555555', 'Webey: berber işletmesine 11.03.2026 13:15 için randevunuz iletilmiştir. Onay için bekleyiniz.', 'booking', 11, 'pending', 0, NULL, NULL, NULL, '2026-03-06 03:54:55'),
(13, '905434543543', 'Webey: berber işletmesine 06.03.2026 15:30 için randevunuz iletilmiştir. Onay için bekleyiniz.', 'booking', 12, 'pending', 0, NULL, NULL, NULL, '2026-03-06 15:02:08'),
(14, '905434543543', 'Webey: berber işletmesine 06.03.2026 15:15 için randevunuz iletilmiştir. Onay için bekleyiniz.', 'booking', 13, 'pending', 0, NULL, NULL, NULL, '2026-03-06 15:02:19'),
(15, '905434543543', 'Webey: berber işletmesine 06.03.2026 18:15 için randevunuz iletilmiştir. Onay için bekleyiniz.', 'booking', 14, 'pending', 0, NULL, NULL, NULL, '2026-03-06 15:50:15'),
(16, '905434543543', 'Webey: İptal talebiniz onaylandı. Randevunuz iptal edilmiştir. İyi günler!', 'cancelled', 13, 'pending', 0, NULL, NULL, NULL, '2026-03-06 15:51:09'),
(17, '905525434454', 'Webey: berber işletmesine 09.03.2026 14:30 için randevunuz iletilmiştir. Onay için bekleyiniz.', 'booking', 15, 'pending', 0, NULL, NULL, NULL, '2026-03-06 16:47:36'),
(18, '905525434454', 'Webey: berber işletmesine 09.03.2026 15:30 için randevunuz iletilmiştir. Onay için bekleyiniz.', 'booking', 16, 'pending', 0, NULL, NULL, NULL, '2026-03-06 16:47:58'),
(19, '905525434454', 'Webey: berber işletmesine 10.03.2026 12:15 için randevunuz iletilmiştir. Onay için bekleyiniz.', 'booking', 17, 'pending', 0, NULL, NULL, NULL, '2026-03-06 16:50:20'),
(20, '905525434454', 'Webey: berber işletmesine 10.03.2026 13:30 için randevunuz iletilmiştir. Onay için bekleyiniz.', 'booking', 18, 'pending', 0, NULL, NULL, NULL, '2026-03-06 17:01:38'),
(21, '905525434454', 'Webey: berber işletmesine 10.03.2026 13:15 için randevunuz iletilmiştir. Onay için bekleyiniz.', 'booking', 19, 'pending', 0, NULL, NULL, NULL, '2026-03-06 17:01:54'),
(22, '905525434454', 'Webey: berber işletmesine 10.03.2026 11:15 için randevunuz iletilmiştir. Onay için bekleyiniz.', 'booking', 20, 'pending', 0, NULL, NULL, NULL, '2026-03-06 17:02:50'),
(23, '905525434454', 'Webey: berber işletmesine 07.03.2026 10:00 için randevunuz iletilmiştir. Onay için bekleyiniz.', 'booking', 21, 'pending', 0, NULL, NULL, NULL, '2026-03-06 17:07:02'),
(24, '905525434454', 'Webey: berber işletmesine 07.03.2026 13:30 için randevunuz iletilmiştir. Onay için bekleyiniz.', 'booking', 22, 'pending', 0, NULL, NULL, NULL, '2026-03-06 17:22:34'),
(25, '905525434454', 'Webey: berber işletmesine 12.03.2026 14:30 için randevunuz iletilmiştir. Onay için bekleyiniz.', 'booking', 23, 'pending', 0, NULL, NULL, NULL, '2026-03-06 17:24:27'),
(26, '905525434454', 'Webey: berber işletmesine 12.03.2026 13:30 için randevunuz iletilmiştir. Onay için bekleyiniz.', 'booking', 24, 'pending', 0, NULL, NULL, NULL, '2026-03-06 17:28:19'),
(27, '905525434454', 'Webey: berber işletmesine 12.03.2026 12:15 için randevunuz iletilmiştir. Onay için bekleyiniz.', 'booking', 25, 'pending', 0, NULL, NULL, NULL, '2026-03-06 17:31:33'),
(28, '905525434454', 'Webey: berber işletmesine 12.03.2026 11:15 için randevunuz iletilmiştir. Onay için bekleyiniz.', 'booking', 26, 'pending', 0, NULL, NULL, NULL, '2026-03-06 17:32:11'),
(29, '905525434454', 'Webey: berber işletmesine 13.03.2026 14:30 için randevunuz iletilmiştir. Onay için bekleyiniz.', 'booking', 27, 'pending', 0, NULL, NULL, NULL, '2026-03-06 17:35:07');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `staff`
--

CREATE TABLE `staff` (
  `id` int(10) UNSIGNED NOT NULL,
  `business_id` int(10) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `position` varchar(80) NOT NULL DEFAULT 'Personel',
  `phone` varchar(20) DEFAULT NULL,
  `color` varchar(30) DEFAULT NULL,
  `photo_url` varchar(500) DEFAULT NULL,
  `photo_opt` varchar(500) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `is_active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Tablo döküm verisi `staff`
--

INSERT INTO `staff` (`id`, `business_id`, `name`, `position`, `phone`, `color`, `photo_url`, `photo_opt`, `created_at`, `is_active`) VALUES
(76, 42, 'abcd', 'Sahip', '5544344353', '#7c3aed', NULL, NULL, '2026-03-05 02:32:56', 1),
(77, 42, 'sadsad', 'SPA Uzmanı', '5454545545', '#059669', NULL, NULL, '2026-03-05 02:32:56', 1),
(78, 43, 'arat', 'Sahip', '5455434345', '#7c3aed', NULL, NULL, '2026-03-05 02:40:03', 1),
(79, 43, 'dsasdasasda', 'Nail Art Uzmanı', '5454455454', '#0ea5e9', NULL, NULL, '2026-03-05 02:40:03', 1),
(80, 44, 'berber', 'Sahip', '5344444444', '#111827', 'uploads/biz/44/staff_80/original.png', 'uploads/optimized/44/staff_80/avatar.webp', '2026-03-05 04:45:38', 1),
(81, 44, 'sadsad', 'Nail Art Uzmanı', '5454554454', '#059669', 'uploads/biz/44/staff_81/original.png', 'uploads/optimized/44/staff_81/avatar.webp', '2026-03-05 04:45:38', 1),
(82, 44, 'ahmet çamurcu', 'Personel', '5454544534', NULL, 'uploads/biz/44/staff_82/original.png', 'uploads/optimized/44/staff_82/avatar.webp', '2026-03-05 06:45:56', 1),
(83, 44, 'dssdsaf', 'Personel', '5455435454', NULL, 'uploads/biz/44/staff_83/original.png', 'uploads/optimized/44/staff_83/avatar.webp', '2026-03-05 07:14:46', 1);

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `staff_hours`
--

CREATE TABLE `staff_hours` (
  `id` int(10) UNSIGNED NOT NULL,
  `staff_id` int(10) UNSIGNED NOT NULL,
  `business_id` int(10) UNSIGNED NOT NULL,
  `day` enum('mon','tue','wed','thu','fri','sat','sun') NOT NULL,
  `is_open` tinyint(1) NOT NULL DEFAULT 1,
  `open_time` time DEFAULT NULL,
  `close_time` time DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Tablo döküm verisi `staff_hours`
--

INSERT INTO `staff_hours` (`id`, `staff_id`, `business_id`, `day`, `is_open`, `open_time`, `close_time`) VALUES
(274, 82, 44, 'mon', 1, '10:00:00', '19:00:00'),
(275, 82, 44, 'tue', 1, '10:00:00', '19:00:00'),
(276, 82, 44, 'wed', 1, '10:00:00', '19:00:00'),
(277, 82, 44, 'thu', 1, '10:00:00', '16:00:00'),
(278, 82, 44, 'fri', 1, '10:00:00', '19:00:00'),
(279, 82, 44, 'sat', 0, NULL, NULL),
(280, 82, 44, 'sun', 0, NULL, NULL),
(288, 83, 44, 'mon', 1, '10:00:00', '18:00:00'),
(289, 83, 44, 'tue', 1, '10:00:00', '18:00:00'),
(290, 83, 44, 'wed', 1, '10:00:00', '18:00:00'),
(291, 83, 44, 'thu', 1, '10:00:00', '18:00:00'),
(292, 83, 44, 'fri', 1, '10:00:00', '18:00:00'),
(293, 83, 44, 'sat', 0, NULL, NULL),
(294, 83, 44, 'sun', 0, NULL, NULL);

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `staff_services`
--

CREATE TABLE `staff_services` (
  `staff_id` int(10) UNSIGNED NOT NULL,
  `service_id` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Tablo döküm verisi `staff_services`
--

INSERT INTO `staff_services` (`staff_id`, `service_id`) VALUES
(76, 239),
(76, 240),
(77, 239),
(77, 240),
(78, 241),
(78, 242),
(79, 241),
(79, 242),
(80, 245),
(80, 246),
(80, 247),
(80, 248),
(80, 249),
(80, 250),
(81, 245),
(81, 246),
(81, 247),
(81, 248),
(81, 249),
(81, 250),
(82, 245),
(82, 246),
(82, 247),
(82, 248),
(82, 249),
(82, 250),
(83, 245),
(83, 246),
(83, 247),
(83, 248),
(83, 249),
(83, 250);

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `subscriptions`
--

CREATE TABLE `subscriptions` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `plan` varchar(30) NOT NULL COMMENT 'monthly_1, monthly_3, monthly_6, yearly_1, yearly_2',
  `status` enum('trialing','active','cancelled','expired','past_due') NOT NULL DEFAULT 'trialing',
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `start_date` datetime NOT NULL,
  `end_date` datetime NOT NULL,
  `cancel_at_period_end` tinyint(1) NOT NULL DEFAULT 0,
  `cancelled_at` datetime DEFAULT NULL,
  `iyzico_subscription_id` varchar(100) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Tablo döküm verisi `subscriptions`
--

INSERT INTO `subscriptions` (`id`, `user_id`, `plan`, `status`, `price`, `start_date`, `end_date`, `cancel_at_period_end`, `cancelled_at`, `iyzico_subscription_id`, `created_at`, `updated_at`) VALUES
(2, 107, 'monthly_3', 'cancelled', 0.00, '2026-03-05 03:02:19', '2026-06-05 03:02:19', 0, '2026-03-05 05:30:10', NULL, '2026-03-05 05:02:19', '2026-03-05 05:30:10'),
(3, 107, 'monthly_6', 'cancelled', 0.00, '2026-03-05 03:30:10', '2026-09-05 03:30:10', 0, '2026-03-05 05:31:25', NULL, '2026-03-05 05:30:10', '2026-03-05 05:31:25'),
(4, 107, 'yearly_1', 'cancelled', 0.00, '2026-03-05 03:31:25', '2027-03-05 03:31:25', 0, '2026-03-05 05:50:00', NULL, '2026-03-05 05:31:25', '2026-03-05 05:50:00'),
(5, 107, 'yearly_2', 'cancelled', 0.00, '2026-03-05 03:50:00', '2026-03-05 06:54:10', 0, '2026-03-05 06:54:10', NULL, '2026-03-05 05:50:00', '2026-03-05 06:54:10'),
(6, 107, 'monthly_1', 'cancelled', 0.00, '2026-03-05 05:13:30', '2026-03-05 07:37:39', 0, '2026-03-05 07:37:39', NULL, '2026-03-05 07:13:30', '2026-03-05 07:37:39'),
(7, 107, 'yearly_1', 'active', 0.00, '2026-03-05 05:38:19', '2027-03-05 05:38:19', 0, NULL, NULL, '2026-03-05 07:38:19', NULL);

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `subscription_reminders`
--

CREATE TABLE `subscription_reminders` (
  `id` int(10) UNSIGNED NOT NULL,
  `subscription_id` int(10) UNSIGNED NOT NULL,
  `remind_type` enum('expiry_3d','expiry_1d','expired') NOT NULL,
  `channel` enum('notification','email','sms') NOT NULL,
  `status` enum('sent','failed') NOT NULL DEFAULT 'sent',
  `sent_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `users`
--

CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `google_id` varchar(64) DEFAULT NULL,
  `email` varchar(191) NOT NULL,
  `avatar_url` varchar(500) DEFAULT NULL,
  `email_verified` tinyint(1) DEFAULT 0,
  `email_verified_at` datetime DEFAULT NULL,
  `phone_verified_at` datetime DEFAULT NULL COMMENT 'NULL = doğrulanmamış',
  `email_verify_token` varchar(128) DEFAULT NULL,
  `email_verify_sent_at` datetime DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `reset_token` varchar(128) DEFAULT NULL,
  `reset_token_expires` datetime DEFAULT NULL,
  `name` varchar(100) DEFAULT NULL,
  `role` enum('admin','user','staff','superadmin') NOT NULL DEFAULT 'user',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `last_login_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Tablo döküm verisi `users`
--

INSERT INTO `users` (`id`, `google_id`, `email`, `avatar_url`, `email_verified`, `email_verified_at`, `phone_verified_at`, `email_verify_token`, `email_verify_sent_at`, `password_hash`, `reset_token`, `reset_token_expires`, `name`, `role`, `created_at`, `last_login_at`) VALUES
(101, NULL, '5462924044@phone.user', NULL, 0, NULL, NULL, NULL, NULL, '$2y$11$skro7f907uKS62osOokIRu2Gx5VCu/nIUX8oacrak/ddngzz0blW.', NULL, NULL, NULL, 'user', '2026-03-04 19:00:37', '2026-03-06 20:33:26'),
(102, NULL, '5454544563@phone.user', NULL, 0, NULL, NULL, NULL, NULL, '$2y$11$KVprl/gFKo.aUHzH4JzxMe0.flRuQieEWKyf1awkCmRW5t/95fLPC', NULL, NULL, NULL, 'user', '2026-03-04 20:01:39', NULL),
(103, NULL, '5555555555@phone.user', NULL, 0, NULL, NULL, NULL, NULL, '$2y$11$U.IURRdkRWlzhbD6u70cy.mZcRLvuVpGhSwh6PCjHetEbk8VYy7t.', NULL, NULL, NULL, 'user', '2026-03-04 20:04:21', '2026-03-06 03:54:53'),
(104, NULL, 'abcd@gmail.com', NULL, 0, NULL, NULL, 'b272a056efe871b4601121d18f84ce8000ecc9001e4bac939b2de061f421b682', '2026-03-05 02:32:23', '$2y$11$NQR7qXMef6o6T.MaIrkds.lnoY6XPpgJYOrvQ2leU1oPaN5Uc1NCC', NULL, NULL, NULL, 'admin', '2026-03-05 02:32:23', NULL),
(105, NULL, 'superadmin@webey.com', NULL, 0, NULL, NULL, NULL, NULL, '$2y$11$6rzQHA6b/ESomeRXxtxZ4uJLq5LzZWZb3WaLna7dny/EwIKtdfCj', NULL, NULL, NULL, 'superadmin', '2026-03-05 02:35:58', NULL),
(106, NULL, 'superadminim@gmail.com', NULL, 0, NULL, NULL, 'f6ea1776a4bc5decf5147bdbafd5ed1dbb651ca2611445e50c65fb1b4a074052', '2026-03-05 02:39:19', '$2y$11$nTXxh2rHmq2hpFY3.QE.pumUq/nGV2bGvrPNUE94Iaj5aOTJSyHlu', NULL, NULL, NULL, 'superadmin', '2026-02-02 05:28:47', '2026-03-06 18:35:02'),
(107, NULL, 'berber@gmail.com', NULL, 0, NULL, NULL, '7647becb5f7fbecb4d2502b665c2b6b8af59dae9364c6229ba11e6dc13b84fb4', '2026-03-05 04:45:07', '$2y$11$RI7qewScxbVp0vRkWc4Mt.vNdvpzWxIuYgyEQeSij0ptmY36kQ7XS', NULL, NULL, NULL, 'admin', '2026-02-02 05:29:12', '2026-03-06 21:06:43'),
(108, NULL, '5454345443@phone.user', NULL, 0, NULL, NULL, NULL, NULL, '$2y$11$vdpJB6wv7KS1BsJvktBf6.rPlWlBLUvkKUPJHaKvEepwTJadTux/C', NULL, NULL, NULL, 'user', '2026-03-05 06:51:32', NULL),
(109, NULL, '5645454543@phone.user', NULL, 0, NULL, NULL, NULL, NULL, '$2y$11$5s8jenPoTFY1JH9UJaMr0ODIpyR3Ng0Ni/jsqX6jQEmweko06n2Bq', NULL, NULL, NULL, 'user', '2026-03-05 08:17:03', NULL),
(110, NULL, '5454534435@phone.user', NULL, 0, NULL, NULL, NULL, NULL, '$2y$11$UBfvRe2pDLY3FHByICalVewX6Gzj0iwkLjXV3FunybsLCcgOSuq7.', NULL, NULL, NULL, 'user', '2026-03-05 08:22:33', NULL),
(111, NULL, '5454534454@phone.user', NULL, 0, NULL, NULL, NULL, NULL, '$2y$11$b8kQwmk1g7XGHayt6ac9reTafkZj/lvYl27hiC11rISz3rGyhvZZm', NULL, NULL, NULL, 'user', '2026-03-05 08:24:50', NULL),
(112, NULL, '5454345444@phone.user', NULL, 0, NULL, NULL, NULL, NULL, '$2y$11$Y9py2AdTlTyoyt.KmOs33uLjOrfw15TNKfGVtIHKxCHU5pdI.JeRO', NULL, NULL, NULL, 'user', '2026-03-05 08:26:45', NULL),
(113, NULL, '5445343544@phone.user', NULL, 0, NULL, NULL, NULL, NULL, '$2y$11$PC/IJFnEVkAx2TJpdVatCeNj2BAFFvN0b/dK8TRv8Bkh2oESUKaKC', NULL, NULL, NULL, 'user', '2026-03-05 12:02:42', NULL),
(114, NULL, '5544534343@phone.user', NULL, 0, NULL, NULL, NULL, NULL, '$2y$11$SOQ7YD.hUffc0SBFgMS79OJIzW/zhtTxlaENT4YZtrPyc.MIY5CZ.', NULL, NULL, NULL, 'user', '2026-03-05 12:05:49', NULL),
(115, NULL, '5434543543@phone.user', NULL, 0, NULL, NULL, NULL, NULL, '$2y$11$1jBj.Ikg4iH77fEEqC4b0uGz53HRYgyzhmqpenmLdNuQZRv3js6ZK', NULL, NULL, NULL, 'user', '2026-03-06 15:02:05', NULL),
(116, NULL, '5525434454@phone.user', NULL, 0, NULL, NULL, NULL, NULL, '$2y$11$eAA1qmG8Do6g2pJI4O/ECuAZohtRyaWLwosHUyg/N.qVn7B0KlkM2', NULL, NULL, NULL, 'user', '2026-03-06 16:44:35', NULL),
(117, NULL, '5333333333@phone.user', NULL, 0, NULL, NULL, NULL, NULL, '$2y$11$oibaY4Bhur7v4ktpT8eebOQ36MWT1oYFzPYxn3mYZxzrR2eeoC3yW', NULL, NULL, NULL, 'user', '2026-03-06 20:26:24', NULL),
(118, NULL, '5545434343@phone.user', NULL, 0, NULL, NULL, NULL, NULL, '$2y$11$5takUOIH/k0pj17eceP.7.QzZ3YG/UFby./4EwG3aJOlxdWx0EgI6', NULL, NULL, NULL, 'user', '2026-03-06 20:52:05', NULL);

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `user_notifications`
--

CREATE TABLE `user_notifications` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `appointment_id` int(10) UNSIGNED DEFAULT NULL,
  `type` enum('appt_approved','appt_cancelled','appt_rejected','appt_reminder','info') NOT NULL DEFAULT 'info',
  `title` varchar(255) NOT NULL,
  `message` text DEFAULT NULL,
  `business_name` varchar(255) DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `read_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Tablo döküm verisi `user_notifications`
--

INSERT INTO `user_notifications` (`id`, `user_id`, `appointment_id`, `type`, `title`, `message`, `business_name`, `is_read`, `read_at`, `created_at`) VALUES
(1, 114, 8, 'appt_approved', '✅ Randevunuz Onaylandı', 'berber — 05.03.2026 18:15 · Saç-Sakal kesimi', 'berber', 1, '2026-03-05 12:18:34', '2026-03-05 12:17:25'),
(2, 114, 8, 'appt_cancelled', '❌ Randevunuz İptal Edildi', 'berber — 05.03.2026 18:15 · Saç-Sakal kesimi', 'berber', 1, '2026-03-05 12:17:50', '2026-03-05 12:17:43'),
(3, 114, 9, 'appt_approved', '✅ Randevunuz Onaylandı', 'berber — 09.03.2026 13:30 · Saç-Sakal kesimi', 'berber', 1, '2026-03-05 12:18:33', '2026-03-05 12:18:24'),
(4, 114, 9, 'appt_cancelled', '❌ Randevunuz İptal Edildi', 'berber — 09.03.2026 13:30 · Saç-Sakal kesimi', 'berber', 1, '2026-03-05 12:19:00', '2026-03-05 12:18:50'),
(5, 114, 10, 'appt_approved', '✅ Randevunuz Onaylandı', 'berber — 09.03.2026 15:45 · Saç-Sakal kesimi', 'berber', 1, '2026-03-05 12:32:50', '2026-03-05 12:32:29'),
(6, 114, 10, 'appt_cancelled', '❌ Randevunuz İptal Edildi', 'berber — 09.03.2026 15:45 · Saç-Sakal kesimi', 'berber', 1, '2026-03-05 12:32:49', '2026-03-05 12:32:39'),
(7, 115, 14, 'appt_approved', '✅ Randevunuz Onaylandı', 'berber — 06.03.2026 18:15 · Saç-Sakal kesimi', 'berber', 1, '2026-03-06 15:52:00', '2026-03-06 15:51:11'),
(8, 115, 14, 'appt_approved', '✅ Randevunuz Onaylandı', 'berber — 06.03.2026 18:15 · Saç-Sakal kesimi', 'berber', 1, '2026-03-06 15:51:59', '2026-03-06 15:51:26'),
(9, 115, 13, 'appt_approved', '✅ Randevunuz Onaylandı', 'berber — 06.03.2026 15:15 · Saç-Sakal kesimi', 'berber', 1, '2026-03-06 15:51:59', '2026-03-06 15:51:27'),
(10, 115, 12, 'appt_approved', '✅ Randevunuz Onaylandı', 'berber — 06.03.2026 15:30 · Saç-Sakal kesimi', 'berber', 1, '2026-03-06 15:51:58', '2026-03-06 15:51:28'),
(11, 103, 11, 'appt_approved', '✅ Randevunuz Onaylandı', 'berber — 11.03.2026 13:15 · Saç-Sakal kesimi', 'berber', 0, NULL, '2026-03-06 15:51:28'),
(12, 114, 8, 'appt_approved', '✅ Randevunuz Onaylandı', 'berber — 05.03.2026 18:15 · Saç-Sakal kesimi', 'berber', 0, NULL, '2026-03-06 15:51:30'),
(13, 116, 15, 'appt_approved', '✅ Randevunuz Onaylandı', 'berber — 09.03.2026 14:30 · Saç-Sakal kesimi', 'berber', 1, '2026-03-06 17:35:14', '2026-03-06 16:47:48'),
(14, 116, 17, 'appt_approved', '✅ Randevunuz Onaylandı', 'berber — 10.03.2026 12:15 · Makas Kesim', 'berber', 1, '2026-03-06 17:35:14', '2026-03-06 16:50:29'),
(15, 116, 16, 'appt_approved', '✅ Randevunuz Onaylandı', 'berber — 09.03.2026 15:30 · Saç-Sakal kesimi', 'berber', 1, '2026-03-06 17:35:14', '2026-03-06 16:50:31'),
(16, 116, 20, 'appt_approved', '✅ Randevunuz Onaylandı', 'berber — 10.03.2026 11:15 · Çocuk Kesimi', 'berber', 1, '2026-03-06 17:35:14', '2026-03-06 17:03:59'),
(17, 116, 19, 'appt_approved', '✅ Randevunuz Onaylandı', 'berber — 10.03.2026 13:15 · Saç + Sakal', 'berber', 1, '2026-03-06 17:35:14', '2026-03-06 17:04:01'),
(18, 116, 18, 'appt_approved', '✅ Randevunuz Onaylandı', 'berber — 10.03.2026 13:30 · Saç-Sakal kesimi', 'berber', 1, '2026-03-06 17:35:14', '2026-03-06 17:04:02'),
(19, 116, 21, 'appt_approved', '✅ Randevunuz Onaylandı', 'berber — 07.03.2026 10:00 · Saç-Sakal kesimi', 'berber', 1, '2026-03-06 17:35:14', '2026-03-06 17:08:03'),
(20, 116, 24, 'appt_approved', '✅ Randevunuz Onaylandı', 'berber — 12.03.2026 13:30 · Saç-Sakal kesimi', 'berber', 1, '2026-03-06 17:35:14', '2026-03-06 17:31:10'),
(21, 116, 25, 'appt_cancelled', '❌ Randevunuz İptal Edildi', 'berber — 12.03.2026 12:15 · Çocuk Kesimi', 'berber', 1, '2026-03-06 17:35:14', '2026-03-06 17:31:40'),
(22, 116, 26, 'appt_approved', '✅ Randevunuz Onaylandı', 'berber — 12.03.2026 11:15 · Saç-Sakal kesimi', 'berber', 1, '2026-03-06 17:35:14', '2026-03-06 17:32:22'),
(23, 116, 26, 'appt_cancelled', '❌ Randevunuz İptal Edildi', 'berber — 12.03.2026 11:15 · Saç-Sakal kesimi', 'berber', 1, '2026-03-06 17:35:14', '2026-03-06 17:32:40'),
(24, 116, 27, 'appt_approved', '✅ Randevunuz Onaylandı', 'berber — 13.03.2026 14:30 · Saç + Sakal', 'berber', 0, NULL, '2026-03-06 17:36:02'),
(25, 116, 23, 'appt_approved', '✅ Randevunuz Onaylandı', 'berber — 12.03.2026 14:30 · Saç-Sakal kesimi', 'berber', 0, NULL, '2026-03-06 23:43:48');

--
-- Dökümü yapılmış tablolar için indeksler
--

--
-- Tablo için indeksler `admin_users`
--
ALTER TABLE `admin_users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_admin_users_user_id` (`user_id`);

--
-- Tablo için indeksler `api_rate_limits`
--
ALTER TABLE `api_rate_limits`
  ADD PRIMARY KEY (`cache_key`),
  ADD KEY `idx_rl_expires` (`expires_at`);

--
-- Tablo için indeksler `appointments`
--
ALTER TABLE `appointments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_appt_business_start` (`business_id`,`start_at`),
  ADD KEY `idx_appt_staff_start` (`staff_id`,`start_at`),
  ADD KEY `fk_appt_service` (`service_id`),
  ADD KEY `idx_appt_status` (`status`),
  ADD KEY `idx_appt_customer_phone` (`customer_phone`),
  ADD KEY `idx_reminder_check` (`status`,`start_at`,`reminder_24h_sent`,`reminder_1h_sent`),
  ADD KEY `idx_appt_biz_staff_time` (`business_id`,`staff_id`,`start_at`),
  ADD KEY `idx_appt_customer_time` (`customer_user_id`,`start_at`),
  ADD KEY `idx_customer_user_id` (`customer_user_id`),
  ADD KEY `idx_business_start_at` (`business_id`,`start_at`),
  ADD KEY `idx_staff_start_status` (`staff_id`,`start_at`,`status`),
  ADD KEY `idx_status_created` (`status`,`created_at`);

--
-- Tablo için indeksler `appointment_logs`
--
ALTER TABLE `appointment_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_appt_log_appt` (`appointment_id`),
  ADD KEY `idx_appt_log_created` (`created_at`);

--
-- Tablo için indeksler `appointment_reminders`
--
ALTER TABLE `appointment_reminders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_appt_remind` (`appointment_id`,`channel`,`remind_before`),
  ADD KEY `idx_status_created` (`status`,`created_at`);

--
-- Tablo için indeksler `businesses`
--
ALTER TABLE `businesses`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_businesses_owner` (`owner_id`),
  ADD UNIQUE KEY `uq_business_slug` (`slug`),
  ADD KEY `idx_owner_id` (`owner_id`),
  ADD KEY `idx_biz_status` (`status`),
  ADD KEY `idx_biz_city_district` (`city`,`district`),
  ADD KEY `idx_biz_onboarding` (`onboarding_completed`),
  ADD KEY `idx_biz_city_status` (`city`,`status`,`id`),
  ADD KEY `idx_city_district_status` (`city`,`district`,`status`),
  ADD KEY `idx_status_onboarding` (`status`,`onboarding_completed`),
  ADD KEY `idx_slug` (`slug`);

--
-- Tablo için indeksler `business_hours`
--
ALTER TABLE `business_hours`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_business_hours_day` (`business_id`,`day`);

--
-- Tablo için indeksler `csrf_tokens`
--
ALTER TABLE `csrf_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_session` (`session_id`),
  ADD KEY `idx_token` (`token`),
  ADD KEY `idx_expires` (`expires_at`);

--
-- Tablo için indeksler `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_user` (`user_id`),
  ADD UNIQUE KEY `uq_cust_email` (`email`),
  ADD KEY `idx_cust_phone` (`phone`),
  ADD KEY `idx_phone` (`phone`);

--
-- Tablo için indeksler `email_queue`
--
ALTER TABLE `email_queue`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_eq_status_scheduled` (`status`,`scheduled_at`,`created_at`);

--
-- Tablo için indeksler `favorites`
--
ALTER TABLE `favorites`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_user_business` (`user_id`,`business_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_business_id` (`business_id`),
  ADD KEY `idx_user_created` (`user_id`,`created_at`);

--
-- Tablo için indeksler `invoices`
--
ALTER TABLE `invoices`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_subscription_id` (`subscription_id`);

--
-- Tablo için indeksler `login_attempts`
--
ALTER TABLE `login_attempts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ip_time` (`ip`,`attempted_at`);

--
-- Tablo için indeksler `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_appt_type` (`appointment_id`,`type`),
  ADD KEY `idx_business` (`business_id`),
  ADD KEY `idx_business_read` (`business_id`,`is_deleted`,`is_read`);

--
-- Tablo için indeksler `otp_tokens`
--
ALTER TABLE `otp_tokens`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_otp_phone_expires` (`phone`,`expires_at`),
  ADD KEY `idx_otp_code` (`code`,`expires_at`);

--
-- Tablo için indeksler `payment_cards`
--
ALTER TABLE `payment_cards`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_token` (`iyzico_card_token`),
  ADD KEY `idx_user_id` (`user_id`);

--
-- Tablo için indeksler `promo_codes`
--
ALTER TABLE `promo_codes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_promo_code` (`code`),
  ADD KEY `idx_promo_active` (`is_active`,`expires_at`),
  ADD KEY `idx_promo_plan` (`plan`);

--
-- Tablo için indeksler `promo_code_uses`
--
ALTER TABLE `promo_code_uses`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_promo_user` (`promo_id`,`user_id`),
  ADD KEY `idx_pcu_user` (`user_id`),
  ADD KEY `idx_pcu_promo` (`promo_id`);

--
-- Tablo için indeksler `push_subscriptions`
--
ALTER TABLE `push_subscriptions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user` (`user_id`);

--
-- Tablo için indeksler `reviews`
--
ALTER TABLE `reviews`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_appointment_review` (`appointment_id`),
  ADD KEY `idx_business_visible` (`business_id`,`is_visible`,`created_at`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_business_visible_rating` (`business_id`,`is_visible`,`rating`);

--
-- Tablo için indeksler `services`
--
ALTER TABLE `services`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_svc_business_name` (`business_id`,`name`);

--
-- Tablo için indeksler `slot_locks`
--
ALTER TABLE `slot_locks`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_slot` (`business_id`,`staff_id`,`day_str`,`start_min`),
  ADD KEY `idx_business_date` (`business_id`,`day_str`,`expires_at`),
  ADD KEY `idx_token` (`lock_token`),
  ADD KEY `idx_expires` (`expires_at`);

--
-- Tablo için indeksler `sms_queue`
--
ALTER TABLE `sms_queue`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_sms_status_scheduled` (`status`,`scheduled_at`,`created_at`),
  ADD KEY `idx_sms_appt` (`appointment_id`),
  ADD KEY `idx_sms_phone` (`phone`);

--
-- Tablo için indeksler `staff`
--
ALTER TABLE `staff`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_staff_biz_active` (`business_id`,`is_active`);

--
-- Tablo için indeksler `staff_hours`
--
ALTER TABLE `staff_hours`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_staff_day` (`staff_id`,`day`),
  ADD KEY `fk_sh_business` (`business_id`);

--
-- Tablo için indeksler `staff_services`
--
ALTER TABLE `staff_services`
  ADD PRIMARY KEY (`staff_id`,`service_id`),
  ADD KEY `service_id` (`service_id`);

--
-- Tablo için indeksler `subscriptions`
--
ALTER TABLE `subscriptions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_end_date` (`end_date`),
  ADD KEY `idx_sub_user_status_end` (`user_id`,`status`,`end_date`);

--
-- Tablo için indeksler `subscription_reminders`
--
ALTER TABLE `subscription_reminders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_sub_remind` (`subscription_id`,`remind_type`,`channel`);

--
-- Tablo için indeksler `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_users_email` (`email`),
  ADD KEY `idx_email_verify_token` (`email_verify_token`),
  ADD KEY `idx_reset_token` (`reset_token`),
  ADD KEY `idx_users_google_id` (`google_id`),
  ADD KEY `idx_users_email` (`email`),
  ADD KEY `idx_last_login` (`last_login_at`),
  ADD KEY `idx_role_created` (`role`,`created_at`);

--
-- Tablo için indeksler `user_notifications`
--
ALTER TABLE `user_notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_un_user_read` (`user_id`,`is_read`),
  ADD KEY `idx_un_user_created` (`user_id`,`created_at`);

--
-- Dökümü yapılmış tablolar için AUTO_INCREMENT değeri
--

--
-- Tablo için AUTO_INCREMENT değeri `admin_users`
--
ALTER TABLE `admin_users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Tablo için AUTO_INCREMENT değeri `appointments`
--
ALTER TABLE `appointments`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- Tablo için AUTO_INCREMENT değeri `appointment_logs`
--
ALTER TABLE `appointment_logs`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Tablo için AUTO_INCREMENT değeri `appointment_reminders`
--
ALTER TABLE `appointment_reminders`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `businesses`
--
ALTER TABLE `businesses`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=45;

--
-- Tablo için AUTO_INCREMENT değeri `business_hours`
--
ALTER TABLE `business_hours`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=652;

--
-- Tablo için AUTO_INCREMENT değeri `csrf_tokens`
--
ALTER TABLE `csrf_tokens`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `customers`
--
ALTER TABLE `customers`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=63;

--
-- Tablo için AUTO_INCREMENT değeri `email_queue`
--
ALTER TABLE `email_queue`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `favorites`
--
ALTER TABLE `favorites`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- Tablo için AUTO_INCREMENT değeri `invoices`
--
ALTER TABLE `invoices`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- Tablo için AUTO_INCREMENT değeri `login_attempts`
--
ALTER TABLE `login_attempts`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- Tablo için AUTO_INCREMENT değeri `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=68;

--
-- Tablo için AUTO_INCREMENT değeri `otp_tokens`
--
ALTER TABLE `otp_tokens`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- Tablo için AUTO_INCREMENT değeri `payment_cards`
--
ALTER TABLE `payment_cards`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `promo_codes`
--
ALTER TABLE `promo_codes`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- Tablo için AUTO_INCREMENT değeri `promo_code_uses`
--
ALTER TABLE `promo_code_uses`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- Tablo için AUTO_INCREMENT değeri `push_subscriptions`
--
ALTER TABLE `push_subscriptions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `reviews`
--
ALTER TABLE `reviews`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- Tablo için AUTO_INCREMENT değeri `services`
--
ALTER TABLE `services`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=251;

--
-- Tablo için AUTO_INCREMENT değeri `slot_locks`
--
ALTER TABLE `slot_locks`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- Tablo için AUTO_INCREMENT değeri `sms_queue`
--
ALTER TABLE `sms_queue`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- Tablo için AUTO_INCREMENT değeri `staff`
--
ALTER TABLE `staff`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=84;

--
-- Tablo için AUTO_INCREMENT değeri `staff_hours`
--
ALTER TABLE `staff_hours`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=295;

--
-- Tablo için AUTO_INCREMENT değeri `subscriptions`
--
ALTER TABLE `subscriptions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- Tablo için AUTO_INCREMENT değeri `subscription_reminders`
--
ALTER TABLE `subscription_reminders`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=119;

--
-- Tablo için AUTO_INCREMENT değeri `user_notifications`
--
ALTER TABLE `user_notifications`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- Dökümü yapılmış tablolar için kısıtlamalar
--

--
-- Tablo kısıtlamaları `admin_users`
--
ALTER TABLE `admin_users`
  ADD CONSTRAINT `fk_admin_users_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Tablo kısıtlamaları `appointments`
--
ALTER TABLE `appointments`
  ADD CONSTRAINT `fk_appt_business` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_appt_customer_user` FOREIGN KEY (`customer_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_appt_service` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_appt_staff` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE SET NULL;

--
-- Tablo kısıtlamaları `businesses`
--
ALTER TABLE `businesses`
  ADD CONSTRAINT `fk_businesses_owner` FOREIGN KEY (`owner_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Tablo kısıtlamaları `business_hours`
--
ALTER TABLE `business_hours`
  ADD CONSTRAINT `fk_bh_business` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`) ON DELETE CASCADE;

--
-- Tablo kısıtlamaları `customers`
--
ALTER TABLE `customers`
  ADD CONSTRAINT `fk_cust_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Tablo kısıtlamaları `promo_code_uses`
--
ALTER TABLE `promo_code_uses`
  ADD CONSTRAINT `fk_pcu_promo` FOREIGN KEY (`promo_id`) REFERENCES `promo_codes` (`id`) ON DELETE CASCADE;

--
-- Tablo kısıtlamaları `push_subscriptions`
--
ALTER TABLE `push_subscriptions`
  ADD CONSTRAINT `fk_push_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Tablo kısıtlamaları `services`
--
ALTER TABLE `services`
  ADD CONSTRAINT `fk_services_business` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`) ON DELETE CASCADE;

--
-- Tablo kısıtlamaları `staff`
--
ALTER TABLE `staff`
  ADD CONSTRAINT `fk_staff_business` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`) ON DELETE CASCADE;

--
-- Tablo kısıtlamaları `staff_hours`
--
ALTER TABLE `staff_hours`
  ADD CONSTRAINT `fk_sh_business` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_sh_staff` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE;

--
-- Tablo kısıtlamaları `staff_services`
--
ALTER TABLE `staff_services`
  ADD CONSTRAINT `staff_services_ibfk_1` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `staff_services_ibfk_2` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE CASCADE;

--
-- Tablo kısıtlamaları `user_notifications`
--
ALTER TABLE `user_notifications`
  ADD CONSTRAINT `fk_un_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

DELIMITER $$
--
-- Olaylar
--
CREATE DEFINER=`root`@`localhost` EVENT `evt_cleanup_login_attempts` ON SCHEDULE EVERY 1 HOUR STARTS '2026-03-02 03:13:48' ON COMPLETION NOT PRESERVE ENABLE COMMENT 'Eski giriş denemelerini temizle' DO DELETE FROM `login_attempts`
    WHERE `attempted_at` < DATE_SUB(NOW(), INTERVAL 1 HOUR)$$

CREATE DEFINER=`root`@`localhost` EVENT `evt_cleanup_csrf_tokens` ON SCHEDULE EVERY 4 HOUR STARTS '2026-03-02 03:13:48' ON COMPLETION NOT PRESERVE ENABLE COMMENT 'Süresi dolmuş CSRF tokenlarını temizle' DO DELETE FROM `csrf_tokens`
    WHERE `expires_at` < NOW()$$

DELIMITER ;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
