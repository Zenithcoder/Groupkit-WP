-- phpMyAdmin SQL Dump
-- version 4.8.5
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Jul 08, 2020 at 01:58 PM
-- Server version: 5.7.26
-- PHP Version: 7.2.18

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `groupkit_new`
--

-- --------------------------------------------------------

--
-- Table structure for table `auto_responder`
--

DROP TABLE IF EXISTS `auto_responder`;
CREATE TABLE IF NOT EXISTS `auto_responder` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `fb_id` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `responder_type` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `responder_json` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `group_id` int(10) UNSIGNED NOT NULL,
  `is_check` int(11) NOT NULL,
  `is_deleted` int(11) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `facebook_groups`
--

DROP TABLE IF EXISTS `facebook_groups`;
CREATE TABLE IF NOT EXISTS `facebook_groups` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `fb_id` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `fb_name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `img` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `is_deleted` int(11) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `facebook_groups`
--

INSERT INTO `facebook_groups` (`id`, `fb_id`, `fb_name`, `img`, `user_id`, `is_deleted`, `created_at`, `updated_at`) VALUES
(1, '547486429302265', 'QTech2', 'https://scontent.famd1-1.fna.fbcdn.net/v/t1.0-9/104279164_1111579442560967_4952017587450127023_n.jpg?_nc_cat=111&_nc_sid=825194&_nc_oc=AQnk4BE9nYu_9ovDJ63bIgvDbYIdA7yNa-IwBfcJkmoE2psKXmI2rMLpxGNXbeOReZ7-_SyS5b9QHpzoL28Z6aAO&_nc_ht=scontent.famd1-1.fna&oh=3402e4c339ab0c4a3064198ae004a81e&oe=5F29D7CE', 1, 1, '2020-07-08 06:08:07', '2020-07-08 06:25:07'),
(2, '547486429302265', 'QTech2', 'https://scontent.famd1-1.fna.fbcdn.net/v/t1.0-9/104279164_1111579442560967_4952017587450127023_n.jpg?_nc_cat=111&_nc_sid=825194&_nc_oc=AQnk4BE9nYu_9ovDJ63bIgvDbYIdA7yNa-IwBfcJkmoE2psKXmI2rMLpxGNXbeOReZ7-_SyS5b9QHpzoL28Z6aAO&_nc_ht=scontent.famd1-1.fna&oh=3402e4c339ab0c4a3064198ae004a81e&oe=5F29D7CE', 1, 0, '2020-07-08 06:26:00', '2020-07-08 06:26:00');

-- --------------------------------------------------------

--
-- Table structure for table `failed_jobs`
--

DROP TABLE IF EXISTS `failed_jobs`;
CREATE TABLE IF NOT EXISTS `failed_jobs` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `connection` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `group_members`
--

DROP TABLE IF EXISTS `group_members`;
CREATE TABLE IF NOT EXISTS `group_members` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `f_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `l_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fb_id` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `a1` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `a2` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `a3` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `date_add_time` timestamp NOT NULL,
  `respond_status` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tags` text COLLATE utf8mb4_unicode_ci,
  `img` text COLLATE utf8mb4_unicode_ci,
  `user_id` int(10) UNSIGNED NOT NULL,
  `group_id` int(10) UNSIGNED NOT NULL,
  `is_deleted` int(11) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `group_members`
--

INSERT INTO `group_members` (`id`, `f_name`, `l_name`, `email`, `fb_id`, `a1`, `a2`, `a3`, `notes`, `date_add_time`, `respond_status`, `tags`, `img`, `user_id`, `group_id`, `is_deleted`, `created_at`, `updated_at`) VALUES
(1, 'Yoan', 'Marinov', 'qa_dev@corp.acesse.com', '100022526884794', NULL, NULL, NULL, NULL, '2020-07-08 06:23:02', 'N/A', '[\"a\",\"a\",\"aa\",\"a\"]', 'https://scontent.famd1-1.fna.fbcdn.net/v/t1.30497-1/cp0/c18.0.60.60a/p60x60/84241059_189132118950875_4138507100605120512_n.jpg?_nc_cat=1&_nc_sid=7206a8&_nc_oc=AQl7iei94R4TTv8bMgQm8nLAqOAMn1oHsadFTXDgnlkqFh8EtFsMhNCL43UTj__FNBpvlvGU6GS3FU52UWhpPJmy&_nc_ht=scontent.famd1-1.fna&oh=45f01be8ce8b78447610a77c447b6cc7&oe=5F2CA1F3', 1, 1, 0, '2020-07-08 06:23:02', '2020-07-08 06:25:00'),
(2, 'Pradeep', 'Thomas', NULL, '1112859703', NULL, NULL, NULL, NULL, '2020-07-08 06:23:02', 'N/A', '[]', 'https://scontent.famd1-2.fna.fbcdn.net/v/t1.0-1/cp0/p60x60/48405250_10216934651454524_720741730411872256_o.jpg?_nc_cat=104&_nc_sid=7206a8&_nc_oc=AQn_y-SPGZt_8nTXCS36FPQT84NVB-moeoyMY0GiS4NP-REgQn8M-Anq-7zChe-fpMmdKbHfBu4mGf8TNyosyjzH&_nc_ht=scontent.famd1-2.fna&oh=7b43a8c441dc3d8ebb98d4837d78d9da&oe=5F2BBEE4', 1, 1, 0, '2020-07-08 06:23:02', '2020-07-08 06:23:02'),
(3, 'Saqib', 'Khan', NULL, '100052734124633', NULL, NULL, NULL, NULL, '2020-07-08 06:23:02', 'N/A', '[]', 'https://scontent.famd1-1.fna.fbcdn.net/v/t1.0-1/cp0/p60x60/83167344_102074381560366_5572352915592596630_o.jpg?_nc_cat=103&_nc_sid=7206a8&_nc_oc=AQnQakNkeROKcQKvzLu1e6akU6wVmogZN78DjHucch1xRaRKqxd6jz7YYzykfJBbzjWFueUxEwkW22_e75c1LApK&_nc_ht=scontent.famd1-1.fna&oh=36aab261de840fce0eeb8bddb527522f&oe=5F2A597C', 1, 1, 0, '2020-07-08 06:23:02', '2020-07-08 06:23:02'),
(4, 'Irfan', 'Khan', NULL, '100048978971672', NULL, NULL, NULL, NULL, '2020-07-08 06:23:02', 'N/A', '[]', 'https://scontent.famd1-2.fna.fbcdn.net/v/t1.0-1/cp0/c0.0.60.60a/p60x60/90935275_106484244327541_5563585852237414400_n.jpg?_nc_cat=100&_nc_sid=7206a8&_nc_oc=AQkV1fA_ckJgp0tJhwyyI8ZVf-Fbh2W1NzzpMk2EaG6iAte15otKmMXiOlo7j8XSUTg9uGll0SY9p0EMyZ0xr8R_&_nc_ht=scontent.famd1-2.fna&oh=c2e1a959071261c3fe447de17b9d9ce9&oe=5F29AF5D', 1, 1, 0, '2020-07-08 06:23:02', '2020-07-08 06:23:02'),
(5, 'Makk', 'Adesra', NULL, '100011267642460', NULL, NULL, NULL, NULL, '2020-07-08 06:23:02', 'N/A', '[]', 'https://scontent.famd1-2.fna.fbcdn.net/v/t1.0-1/cp0/p60x60/106911869_1125520597833518_676731315726627437_n.jpg?_nc_cat=101&_nc_sid=7206a8&_nc_oc=AQltKZvdiprwtu7jITXGudHCOl0zpvUBTEGLMtLSkkYSwDO7Zee5LoNx_pATS51AXco5Su1EjIXikUNOa98H0MPu&_nc_ht=scontent.famd1-2.fna&oh=0e485eeb2b9f9368f29f8f81268a6b54&oe=5F2B7ADB', 1, 1, 0, '2020-07-08 06:23:02', '2020-07-08 06:23:02'),
(6, 'Dev', 'Devloper', NULL, '100022526884794', NULL, NULL, NULL, NULL, '2020-07-08 06:26:00', 'N/A', '[]', 'https://scontent.famd1-1.fna.fbcdn.net/v/t1.30497-1/cp0/c18.0.60.60a/p60x60/84241059_189132118950875_4138507100605120512_n.jpg?_nc_cat=1&_nc_sid=7206a8&_nc_oc=AQl7iei94R4TTv8bMgQm8nLAqOAMn1oHsadFTXDgnlkqFh8EtFsMhNCL43UTj__FNBpvlvGU6GS3FU52UWhpPJmy&_nc_ht=scontent.famd1-1.fna&oh=45f01be8ce8b78447610a77c447b6cc7&oe=5F2CA1F3', 1, 2, 0, '2020-07-08 06:26:00', '2020-07-08 06:26:00'),
(7, 'Pradeep', 'Thomas', NULL, '1112859703', NULL, NULL, NULL, NULL, '2020-07-08 06:26:00', 'N/A', '[]', 'https://scontent.famd1-2.fna.fbcdn.net/v/t1.0-1/cp0/p60x60/48405250_10216934651454524_720741730411872256_o.jpg?_nc_cat=104&_nc_sid=7206a8&_nc_oc=AQn_y-SPGZt_8nTXCS36FPQT84NVB-moeoyMY0GiS4NP-REgQn8M-Anq-7zChe-fpMmdKbHfBu4mGf8TNyosyjzH&_nc_ht=scontent.famd1-2.fna&oh=7b43a8c441dc3d8ebb98d4837d78d9da&oe=5F2BBEE4', 1, 2, 0, '2020-07-08 06:26:00', '2020-07-08 06:26:00'),
(8, 'Saqib', 'Khan', NULL, '100052734124633', NULL, NULL, NULL, NULL, '2020-07-08 06:26:00', 'N/A', '[]', 'https://scontent.famd1-1.fna.fbcdn.net/v/t1.0-1/cp0/p60x60/83167344_102074381560366_5572352915592596630_o.jpg?_nc_cat=103&_nc_sid=7206a8&_nc_oc=AQnQakNkeROKcQKvzLu1e6akU6wVmogZN78DjHucch1xRaRKqxd6jz7YYzykfJBbzjWFueUxEwkW22_e75c1LApK&_nc_ht=scontent.famd1-1.fna&oh=36aab261de840fce0eeb8bddb527522f&oe=5F2A597C', 1, 2, 0, '2020-07-08 06:26:00', '2020-07-08 06:26:00'),
(9, 'Irfan', 'Khan', NULL, '100048978971672', NULL, NULL, NULL, NULL, '2020-07-08 06:26:00', 'N/A', '[]', 'https://scontent.famd1-2.fna.fbcdn.net/v/t1.0-1/cp0/c0.0.60.60a/p60x60/90935275_106484244327541_5563585852237414400_n.jpg?_nc_cat=100&_nc_sid=7206a8&_nc_oc=AQkV1fA_ckJgp0tJhwyyI8ZVf-Fbh2W1NzzpMk2EaG6iAte15otKmMXiOlo7j8XSUTg9uGll0SY9p0EMyZ0xr8R_&_nc_ht=scontent.famd1-2.fna&oh=c2e1a959071261c3fe447de17b9d9ce9&oe=5F29AF5D', 1, 2, 0, '2020-07-08 06:26:00', '2020-07-08 06:26:00'),
(10, 'Makk', 'Adesra', NULL, '100011267642460', NULL, NULL, NULL, NULL, '2020-07-08 06:26:00', 'N/A', '[]', 'https://scontent.famd1-2.fna.fbcdn.net/v/t1.0-1/cp0/p60x60/106911869_1125520597833518_676731315726627437_n.jpg?_nc_cat=101&_nc_sid=7206a8&_nc_oc=AQltKZvdiprwtu7jITXGudHCOl0zpvUBTEGLMtLSkkYSwDO7Zee5LoNx_pATS51AXco5Su1EjIXikUNOa98H0MPu&_nc_ht=scontent.famd1-2.fna&oh=0e485eeb2b9f9368f29f8f81268a6b54&oe=5F2B7ADB', 1, 2, 0, '2020-07-08 06:26:00', '2020-07-08 06:26:00');

-- --------------------------------------------------------

--
-- Table structure for table `migrations`
--

DROP TABLE IF EXISTS `migrations`;
CREATE TABLE IF NOT EXISTS `migrations` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `migration` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=72 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `migrations`
--

INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES
(52, '2014_10_12_000000_create_users_table', 1),
(53, '2014_10_12_100000_create_password_resets_table', 1),
(54, '2016_06_01_000001_create_oauth_auth_codes_table', 1),
(55, '2016_06_01_000002_create_oauth_access_tokens_table', 1),
(56, '2016_06_01_000003_create_oauth_refresh_tokens_table', 1),
(57, '2016_06_01_000004_create_oauth_clients_table', 1),
(58, '2016_06_01_000005_create_oauth_personal_access_clients_table', 1),
(59, '2019_05_03_000001_create_customer_columns', 1),
(60, '2019_05_03_000002_create_subscriptions_table', 1),
(61, '2019_05_03_000003_create_subscription_items_table', 1),
(62, '2019_08_19_000000_create_failed_jobs_table', 1),
(63, '2020_06_15_092740_create_plans_table', 1),
(64, '2020_07_08_105242_create_auto_responder_table', 1),
(65, '2020_07_08_105301_create_facebook_groups_table', 1),
(71, '2020_07_08_105319_create_group_members_table', 2),
(67, '2020_07_08_105417_create_refund_payment_table', 1),
(68, '2020_07_08_105437_create_payments_table', 1);

-- --------------------------------------------------------

--
-- Table structure for table `oauth_access_tokens`
--

DROP TABLE IF EXISTS `oauth_access_tokens`;
CREATE TABLE IF NOT EXISTS `oauth_access_tokens` (
  `id` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `client_id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `scopes` text COLLATE utf8mb4_unicode_ci,
  `revoked` tinyint(1) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `oauth_access_tokens_user_id_index` (`user_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `oauth_access_tokens`
--

INSERT INTO `oauth_access_tokens` (`id`, `user_id`, `client_id`, `name`, `scopes`, `revoked`, `created_at`, `updated_at`, `expires_at`) VALUES
('528f05334aec14457339315091446f2edde9c0022d6d619483b08bd90638583ff592140678522e68', 1, 1, 'pradeep@groupkit.com', '[]', 0, '2020-07-08 06:06:29', '2020-07-08 06:06:29', '2021-07-08 11:36:29');

-- --------------------------------------------------------

--
-- Table structure for table `oauth_auth_codes`
--

DROP TABLE IF EXISTS `oauth_auth_codes`;
CREATE TABLE IF NOT EXISTS `oauth_auth_codes` (
  `id` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `client_id` bigint(20) UNSIGNED NOT NULL,
  `scopes` text COLLATE utf8mb4_unicode_ci,
  `revoked` tinyint(1) NOT NULL,
  `expires_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `oauth_auth_codes_user_id_index` (`user_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `oauth_clients`
--

DROP TABLE IF EXISTS `oauth_clients`;
CREATE TABLE IF NOT EXISTS `oauth_clients` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `secret` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `provider` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `redirect` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `personal_access_client` tinyint(1) NOT NULL,
  `password_client` tinyint(1) NOT NULL,
  `revoked` tinyint(1) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `oauth_clients_user_id_index` (`user_id`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `oauth_clients`
--

INSERT INTO `oauth_clients` (`id`, `user_id`, `name`, `secret`, `provider`, `redirect`, `personal_access_client`, `password_client`, `revoked`, `created_at`, `updated_at`) VALUES
(1, NULL, 'GroupKit Personal Access Client', '5TjlGQlK02zfyLSe2hMd1qWyoligbiuz7tQe41VN', NULL, 'http://localhost', 1, 0, 0, '2020-07-08 06:06:05', '2020-07-08 06:06:05');

-- --------------------------------------------------------

--
-- Table structure for table `oauth_personal_access_clients`
--

DROP TABLE IF EXISTS `oauth_personal_access_clients`;
CREATE TABLE IF NOT EXISTS `oauth_personal_access_clients` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `client_id` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `oauth_personal_access_clients`
--

INSERT INTO `oauth_personal_access_clients` (`id`, `client_id`, `created_at`, `updated_at`) VALUES
(1, 1, '2020-07-08 06:06:05', '2020-07-08 06:06:05');

-- --------------------------------------------------------

--
-- Table structure for table `oauth_refresh_tokens`
--

DROP TABLE IF EXISTS `oauth_refresh_tokens`;
CREATE TABLE IF NOT EXISTS `oauth_refresh_tokens` (
  `id` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `access_token_id` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `revoked` tinyint(1) NOT NULL,
  `expires_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

DROP TABLE IF EXISTS `password_resets`;
CREATE TABLE IF NOT EXISTS `password_resets` (
  `email` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  KEY `password_resets_email_index` (`email`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

DROP TABLE IF EXISTS `payments`;
CREATE TABLE IF NOT EXISTS `payments` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `txn_id` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payment_gross` double(10,10) NOT NULL,
  `currency_code` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payer_email` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `receiver_email` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payment_date` timestamp NOT NULL,
  `payment_status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `is_deleted` int(11) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `plans`
--

DROP TABLE IF EXISTS `plans`;
CREATE TABLE IF NOT EXISTS `plans` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `stripe_plan` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `cost` double(8,2) NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `is_deleted` int(11) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `plans_slug_unique` (`slug`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `refund_payment`
--

DROP TABLE IF EXISTS `refund_payment`;
CREATE TABLE IF NOT EXISTS `refund_payment` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `refund_pay_id` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `refund_object` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `refund_amount` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `refund_balance_transaction` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `refund_charge` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `refund_currency` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `receipt_number` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `reason` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `is_deleted` int(11) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `subscriptions`
--

DROP TABLE IF EXISTS `subscriptions`;
CREATE TABLE IF NOT EXISTS `subscriptions` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int(10) UNSIGNED NOT NULL,
  `name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `stripe_id` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `stripe_plan` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `stripe_status` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `quantity` int(11) NOT NULL,
  `trial_ends_at` timestamp NULL DEFAULT NULL,
  `ends_at` timestamp NULL DEFAULT NULL,
  `is_deleted` int(11) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `subscription_items`
--

DROP TABLE IF EXISTS `subscription_items`;
CREATE TABLE IF NOT EXISTS `subscription_items` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `subscription_id` bigint(20) UNSIGNED NOT NULL,
  `stripe_id` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `stripe_plan` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `quantity` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `subscription_items_subscription_id_stripe_plan_unique` (`subscription_id`,`stripe_plan`),
  KEY `subscription_items_stripe_id_index` (`stripe_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
CREATE TABLE IF NOT EXISTS `users` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_deleted` int(11) NOT NULL DEFAULT '0',
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `stripe_id` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `card_brand` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `card_last_four` varchar(4) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `trial_ends_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`),
  KEY `users_stripe_id_index` (`stripe_id`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `email_verified_at`, `password`, `is_deleted`, `remember_token`, `created_at`, `updated_at`, `stripe_id`, `card_brand`, `card_last_four`, `trial_ends_at`) VALUES
(1, 'Pradeep', 'pradeep@groupkit.com', NULL, '$2y$10$KLvLHHiiJMF0Mb574F1yFOR4kvIawVTOKqYS3BfGOS2WPTHgFHjTq', 0, NULL, '2020-07-08 06:06:29', '2020-07-08 06:06:59', 'cus_HbpEv3evlt1Y4X', 'visa', '4242', NULL);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
