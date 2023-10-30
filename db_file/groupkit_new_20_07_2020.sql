-- phpMyAdmin SQL Dump
-- version 4.8.5
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Jul 20, 2020 at 04:48 AM
-- Server version: 5.7.26
-- PHP Version: 7.3.5

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
  `responder_type` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `responder_json` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `group_id` int(10) UNSIGNED NOT NULL,
  `is_check` int(11) NOT NULL,
  `is_deleted` int(11) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `auto_responder`
--

INSERT INTO `auto_responder` (`id`, `responder_type`, `responder_json`, `user_id`, `group_id`, `is_check`, `is_deleted`, `created_at`, `updated_at`) VALUES
(2, 'Mailerlite', '{\"activeList\":{\"label\":\"Clients &amp; Community\",\"value\":103375046},\"api_key\":\"067529f962e0bb0fd7fd91334f93420a\"}', 1, 4, 0, 1, '2020-07-16 05:23:20', '2020-07-19 23:15:41');

-- --------------------------------------------------------

--
-- Table structure for table `facebook_groups`
--

DROP TABLE IF EXISTS `facebook_groups`;
CREATE TABLE IF NOT EXISTS `facebook_groups` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `fb_id` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `fb_name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `img` text COLLATE utf8mb4_unicode_ci,
  `user_id` int(10) UNSIGNED NOT NULL,
  `is_deleted` int(11) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `facebook_groups`
--

INSERT INTO `facebook_groups` (`id`, `fb_id`, `fb_name`, `img`, `user_id`, `is_deleted`, `created_at`, `updated_at`) VALUES
(1, '547486429302265', 'QTech2', 'https://scontent.famd1-1.fna.fbcdn.net/v/t1.0-9/104279164_1111579442560967_4952017587450127023_n.jpg?_nc_cat=111&_nc_sid=825194&_nc_oc=AQkjlOEeHta4Ggz8WX2ATO5rqlPmND9SEKQ2HU-l3iAnncqiOGi7-TUjtiRg5l3YYaGez-1hK8_DnYi_9yLSzXWQ&_nc_ht=scontent.famd1-1.fna&oh=52d32335766f7600b9baab5b79797cd0&oe=5F2DCC4E', 1, 1, '2020-07-10 03:42:51', '2020-07-10 03:47:15'),
(2, '547486429302265', 'QTech2', 'https://scontent.famd1-1.fna.fbcdn.net/v/t1.0-9/104279164_1111579442560967_4952017587450127023_n.jpg?_nc_cat=111&_nc_sid=825194&_nc_oc=AQkjlOEeHta4Ggz8WX2ATO5rqlPmND9SEKQ2HU-l3iAnncqiOGi7-TUjtiRg5l3YYaGez-1hK8_DnYi_9yLSzXWQ&_nc_ht=scontent.famd1-1.fna&oh=52d32335766f7600b9baab5b79797cd0&oe=5F2DCC4E', 1, 1, '2020-07-10 03:48:08', '2020-07-10 04:00:40'),
(3, '547486429302265', 'QTech2', 'https://scontent.famd1-1.fna.fbcdn.net/v/t1.0-9/104279164_1111579442560967_4952017587450127023_n.jpg?_nc_cat=111&_nc_sid=825194&_nc_oc=AQkjlOEeHta4Ggz8WX2ATO5rqlPmND9SEKQ2HU-l3iAnncqiOGi7-TUjtiRg5l3YYaGez-1hK8_DnYi_9yLSzXWQ&_nc_ht=scontent.famd1-1.fna&oh=52d32335766f7600b9baab5b79797cd0&oe=5F2DCC4E', 1, 1, '2020-07-10 04:04:41', '2020-07-10 04:05:36'),
(4, '547486429302265', 'QTech2', 'https://scontent.famd1-1.fna.fbcdn.net/v/t1.0-9/104279164_1111579442560967_4952017587450127023_n.jpg?_nc_cat=111&_nc_sid=825194&_nc_oc=AQkjlOEeHta4Ggz8WX2ATO5rqlPmND9SEKQ2HU-l3iAnncqiOGi7-TUjtiRg5l3YYaGez-1hK8_DnYi_9yLSzXWQ&_nc_ht=scontent.famd1-1.fna&oh=52d32335766f7600b9baab5b79797cd0&oe=5F2DCC4E', 1, 0, '2020-07-10 04:06:28', '2020-07-15 06:06:36');

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
) ENGINE=MyISAM AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `group_members`
--

INSERT INTO `group_members` (`id`, `f_name`, `l_name`, `email`, `fb_id`, `a1`, `a2`, `a3`, `notes`, `date_add_time`, `respond_status`, `tags`, `img`, `user_id`, `group_id`, `is_deleted`, `created_at`, `updated_at`) VALUES
(4, 'Dev', 'Devloper', 'pradeep@gmail.com', '100022526884794', 'pradeep@gmail.com', NULL, NULL, NULL, '2020-07-10 05:09:59', 'N/A', '[]', 'https://scontent.famd1-2.fna.fbcdn.net/v/t1.30497-1/cp0/c18.0.60.60a/p60x60/84241059_189132118950875_4138507100605120512_n.jpg?_nc_cat=1&_nc_sid=7206a8&_nc_oc=AQkdWQHo0pf3xxOtO3NghNUbEdEl1X6__mcfSQAP5v5dCMbcBFuGL0HkMEnqNlW0vgYWIUlFiHjxB8LEaSPXQ-aJ&_nc_ht=scontent.famd1-2.fna&oh=ebd4b5c69a822fad3b872339bf8ceb1d&oe=5F2CA1F3', 1, 4, 0, '2020-07-10 05:09:59', '2020-07-10 05:09:59'),
(5, 'One', 'Devloper', '', '100022526884794', 'one@gmail.com', NULL, NULL, NULL, '2020-07-10 05:09:59', 'N/A', '[]', 'https://scontent.famd1-2.fna.fbcdn.net/v/t1.30497-1/cp0/c18.0.60.60a/p60x60/84241059_189132118950875_4138507100605120512_n.jpg?_nc_cat=1&_nc_sid=7206a8&_nc_oc=AQkdWQHo0pf3xxOtO3NghNUbEdEl1X6__mcfSQAP5v5dCMbcBFuGL0HkMEnqNlW0vgYWIUlFiHjxB8LEaSPXQ-aJ&_nc_ht=scontent.famd1-2.fna&oh=ebd4b5c69a822fad3b872339bf8ceb1d&oe=5F2CA1F3', 1, 4, 0, '2020-07-10 05:09:59', '2020-07-10 05:09:59');

-- --------------------------------------------------------

--
-- Table structure for table `json`
--

DROP TABLE IF EXISTS `json`;
CREATE TABLE IF NOT EXISTS `json` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `json` longtext,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=22 DEFAULT CHARSET=latin1;

--
-- Dumping data for table `json`
--

INSERT INTO `json` (`id`, `json`) VALUES
(21, '{\"user_id\":1,\"subscriptions\":null,\"txn_id\":\"ch_1H4gh4LRkwVQ3lNkv6nDaqdM\",\"payment_gross\":2000,\"payer_email\":null,\"receiver_email\":\"\",\"payment_date\":\"2020-07-14 05:28:42\",\"currency_code\":\"usd\",\"payment_status\":\"succeeded\",\"is_deleted\":0}'),
(18, '{\"user_id\":1,\"subscriptions\":null,\"txn_id\":\"ch_1H4gPHLRkwVQ3lNkYKHBO55n\",\"payment_gross\":2000,\"payer_email\":null,\"receiver_email\":\"\",\"payment_date\":\"2020-07-14\",\"currency_code\":\"usd\",\"payment_status\":\"succeeded\",\"is_deleted\":0}'),
(19, '{\"user_id\":1,\"subscriptions\":null,\"txn_id\":\"ch_1H4gQxLRkwVQ3lNkGwiMbZrz\",\"payment_gross\":2000,\"payer_email\":null,\"receiver_email\":\"\",\"payment_date\":\"2020-07-14\",\"currency_code\":\"usd\",\"payment_status\":\"succeeded\",\"is_deleted\":0}'),
(20, '{\"user_id\":1,\"subscriptions\":null,\"txn_id\":\"ch_1H4gUnLRkwVQ3lNkA2JS0ONl\",\"payment_gross\":2000,\"payer_email\":null,\"receiver_email\":\"\",\"payment_date\":\"2020-07-14 05:16:01\",\"currency_code\":\"usd\",\"payment_status\":\"succeeded\",\"is_deleted\":0}'),
(15, '1'),
(16, '{\"id\":\"ch_1H4gMQLRkwVQ3lNkMm162yZR\",\"object\":\"charge\",\"amount\":2000,\"amount_refunded\":0,\"application\":null,\"application_fee\":null,\"application_fee_amount\":null,\"balance_transaction\":\"txn_1H4gMQLRkwVQ3lNkFzHcP6Nx\",\"billing_details\":{\"address\":{\"city\":null,\"country\":null,\"line1\":null,\"line2\":null,\"postal_code\":null,\"state\":null},\"email\":null,\"name\":null,\"phone\":null},\"calculated_statement_descriptor\":\"GROUPKIT\",\"captured\":true,\"created\":1594703242,\"currency\":\"usd\",\"customer\":\"cus_HdyIMhfxw7yVdY\",\"description\":\"Payment for Invoice\",\"destination\":null,\"dispute\":null,\"disputed\":false,\"failure_code\":null,\"failure_message\":null,\"fraud_details\":[],\"invoice\":\"in_1H4gMOLRkwVQ3lNkpz2e04Pl\",\"livemode\":false,\"metadata\":[],\"on_behalf_of\":null,\"order\":null,\"outcome\":{\"network_status\":\"approved_by_network\",\"reason\":null,\"risk_level\":\"normal\",\"risk_score\":11,\"seller_message\":\"Payment complete.\",\"type\":\"authorized\"},\"paid\":true,\"payment_intent\":\"pi_1H4gMPLRkwVQ3lNktpAQcr5S\",\"payment_method\":\"card_1H4gMMLRkwVQ3lNkuEmmV3AI\",\"payment_method_details\":{\"card\":{\"brand\":\"visa\",\"checks\":{\"address_line1_check\":null,\"address_postal_code_check\":null,\"cvc_check\":null},\"country\":\"US\",\"exp_month\":7,\"exp_year\":2021,\"fingerprint\":\"gJ9PdRh0Hk6KVCKc\",\"funding\":\"credit\",\"installments\":null,\"last4\":\"4242\",\"network\":\"visa\",\"three_d_secure\":null,\"wallet\":null},\"type\":\"card\"},\"receipt_email\":null,\"receipt_number\":null,\"receipt_url\":\"https:\\/\\/pay.stripe.com\\/receipts\\/acct_1GYkX6LRkwVQ3lNk\\/ch_1H4gMQLRkwVQ3lNkMm162yZR\\/rcpt_HdyJyWerATL3WHtLErJv36Q9NEVAGwu\",\"refunded\":false,\"refunds\":{\"object\":\"list\",\"data\":[],\"has_more\":false,\"total_count\":0,\"url\":\"\\/v1\\/charges\\/ch_1H4gMQLRkwVQ3lNkMm162yZR\\/refunds\"},\"review\":null,\"shipping\":null,\"source\":{\"id\":\"card_1H4gMMLRkwVQ3lNkuEmmV3AI\",\"object\":\"card\",\"address_city\":null,\"address_country\":null,\"address_line1\":null,\"address_line1_check\":null,\"address_line2\":null,\"address_state\":null,\"address_zip\":null,\"address_zip_check\":null,\"brand\":\"Visa\",\"country\":\"US\",\"customer\":\"cus_HdyIMhfxw7yVdY\",\"cvc_check\":null,\"dynamic_last4\":null,\"exp_month\":7,\"exp_year\":2021,\"fingerprint\":\"gJ9PdRh0Hk6KVCKc\",\"funding\":\"credit\",\"last4\":\"4242\",\"metadata\":[],\"name\":null,\"tokenization_method\":null},\"source_transfer\":null,\"statement_descriptor\":null,\"statement_descriptor_suffix\":null,\"status\":\"succeeded\",\"transfer_data\":null,\"transfer_group\":null}'),
(17, '{\"id\":\"ch_1H4gOELRkwVQ3lNkZeUyzd8x\",\"object\":\"charge\",\"amount\":2000,\"amount_refunded\":0,\"application\":null,\"application_fee\":null,\"application_fee_amount\":null,\"balance_transaction\":\"txn_1H4gOELRkwVQ3lNktB2oILKI\",\"billing_details\":{\"address\":{\"city\":null,\"country\":null,\"line1\":null,\"line2\":null,\"postal_code\":null,\"state\":null},\"email\":null,\"name\":null,\"phone\":null},\"calculated_statement_descriptor\":\"GROUPKIT\",\"captured\":true,\"created\":1594703354,\"currency\":\"usd\",\"customer\":\"cus_HdyK6zj0trq479\",\"description\":\"Payment for Invoice\",\"destination\":null,\"dispute\":null,\"disputed\":false,\"failure_code\":null,\"failure_message\":null,\"fraud_details\":[],\"invoice\":\"in_1H4gOCLRkwVQ3lNk2h19AIlw\",\"livemode\":false,\"metadata\":[],\"on_behalf_of\":null,\"order\":null,\"outcome\":{\"network_status\":\"approved_by_network\",\"reason\":null,\"risk_level\":\"normal\",\"risk_score\":15,\"seller_message\":\"Payment complete.\",\"type\":\"authorized\"},\"paid\":true,\"payment_intent\":\"pi_1H4gODLRkwVQ3lNk88lLBAzg\",\"payment_method\":\"card_1H4gOALRkwVQ3lNkxFnLuVcu\",\"payment_method_details\":{\"card\":{\"brand\":\"visa\",\"checks\":{\"address_line1_check\":null,\"address_postal_code_check\":null,\"cvc_check\":null},\"country\":\"US\",\"exp_month\":7,\"exp_year\":2021,\"fingerprint\":\"gJ9PdRh0Hk6KVCKc\",\"funding\":\"credit\",\"installments\":null,\"last4\":\"4242\",\"network\":\"visa\",\"three_d_secure\":null,\"wallet\":null},\"type\":\"card\"},\"receipt_email\":null,\"receipt_number\":null,\"receipt_url\":\"https:\\/\\/pay.stripe.com\\/receipts\\/acct_1GYkX6LRkwVQ3lNk\\/ch_1H4gOELRkwVQ3lNkZeUyzd8x\\/rcpt_HdyKczBVbN9TjQwgR5aqR2USfgW8EkS\",\"refunded\":false,\"refunds\":{\"object\":\"list\",\"data\":[],\"has_more\":false,\"total_count\":0,\"url\":\"\\/v1\\/charges\\/ch_1H4gOELRkwVQ3lNkZeUyzd8x\\/refunds\"},\"review\":null,\"shipping\":null,\"source\":{\"id\":\"card_1H4gOALRkwVQ3lNkxFnLuVcu\",\"object\":\"card\",\"address_city\":null,\"address_country\":null,\"address_line1\":null,\"address_line1_check\":null,\"address_line2\":null,\"address_state\":null,\"address_zip\":null,\"address_zip_check\":null,\"brand\":\"Visa\",\"country\":\"US\",\"customer\":\"cus_HdyK6zj0trq479\",\"cvc_check\":null,\"dynamic_last4\":null,\"exp_month\":7,\"exp_year\":2021,\"fingerprint\":\"gJ9PdRh0Hk6KVCKc\",\"funding\":\"credit\",\"last4\":\"4242\",\"metadata\":[],\"name\":null,\"tokenization_method\":null},\"source_transfer\":null,\"statement_descriptor\":null,\"statement_descriptor_suffix\":null,\"status\":\"succeeded\",\"transfer_data\":null,\"transfer_group\":null}'),
(14, '1');

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
('528f05334aec14457339315091446f2edde9c0022d6d619483b08bd90638583ff592140678522e68', 1, 1, 'pradeep@groupkit.com', '[]', 0, '2020-07-08 06:06:29', '2020-07-08 06:06:29', '2021-07-08 11:36:29'),
('944cf6e52a3d29b6ecc7673cc54cd13b0f3611a0ef7a8c772810fc1c3c5b4b9314be3623ce5f7f31', 1, 1, 'pradeep@groupkit.com', '[]', 0, '2020-07-08 23:10:30', '2020-07-08 23:10:30', '2021-07-09 04:40:30'),
('f711ce9f330fe5c0704a9698a4d4d037d394cd7da8c209e9773ca582959816f146fa849e53f4a138', 1, 1, 'pradeep@groupkit.com', '[]', 0, '2020-07-09 23:11:25', '2020-07-09 23:11:25', '2021-07-10 04:41:25'),
('10621a7ee6324b3fd3fe711114609c4f645f3f17feb317f61e7a110058af3cb4944a3e8ff207039b', 1, 1, 'pradeep@groupkit.com', '[]', 0, '2020-07-10 06:04:19', '2020-07-10 06:04:19', '2021-07-10 11:34:19'),
('1b56b4f32241acda378fbb5adc20e3a4df46a0aab1966ba7888002f7caca4f4ed2328caa5ffaa1ce', 1, 1, 'pradeep@groupkit.com', '[]', 0, '2020-07-10 06:05:10', '2020-07-10 06:05:10', '2021-07-10 11:35:10'),
('f382c7cd65368a127afecea8493ff68a8b5228a731416d4fde2444feb0591560bfdbe4b0f4e69fe2', 1, 1, 'pradeep@groupkit.com', '[]', 0, '2020-07-10 06:05:10', '2020-07-10 06:05:10', '2021-07-10 11:35:10'),
('d62c45c7465ec25503479c2a35d92d33cc6937ee9b2f98f2506587661bbeba1285243ac3e398a77f', 1, 1, 'pradeep@groupkit.com', '[]', 0, '2020-07-10 06:21:10', '2020-07-10 06:21:10', '2021-07-10 11:51:10'),
('8202af3d38b06bcd55f06375705ad82f4d426d7f13ef236e2fea8a9e182c732f395f5e8f65a2a9b6', 1, 1, 'pradeep@groupkit.com', '[]', 0, '2020-07-10 06:39:15', '2020-07-10 06:39:15', '2021-07-10 12:09:15'),
('2a54df48eb7fb93431ec46fb101056c8993460f7e10285218675bec1c321f507d6e62fc1ca3d92ca', 1, 1, 'pradeep@groupkit.com', '[]', 0, '2020-07-10 06:39:16', '2020-07-10 06:39:16', '2021-07-10 12:09:16'),
('c993c4dfac52352d6e7ce9132e0bf30c50c230863b5f3505e5cad207d3ce51642dda1faa04478bef', 1, 1, 'pradeep@groupkit.com', '[]', 0, '2020-07-10 06:41:16', '2020-07-10 06:41:16', '2021-07-10 12:11:16'),
('a268475f300ba05b532d2e40aac0b01f6a71c369636e4ca17bf98131abacb1f3d57ba91775ae1fb5', 1, 1, 'pradeep@groupkit.com', '[]', 0, '2020-07-10 06:41:16', '2020-07-10 06:41:16', '2021-07-10 12:11:16'),
('801c050d84686e4a95b66e22fbfa138383a3e92d7de74883a671b0f21826a89dac2d8b56128c5880', 1, 1, 'pradeep@groupkit.com', '[]', 0, '2020-07-10 06:48:32', '2020-07-10 06:48:32', '2021-07-10 12:18:32'),
('ae360d64f6a1dee452756e1708223e2868b653d2b8dcf699df5bf59567cc08a37e659ebddc5b6c69', 1, 1, 'pradeep@groupkit.com', '[]', 0, '2020-07-10 06:48:32', '2020-07-10 06:48:32', '2021-07-10 12:18:32'),
('216a5c0fc4abf2314805c106c573fc64becd4a3bc62fc2bb9c0d1cb1ed0babce7941110eca44e66c', 1, 1, 'pradeep@groupkit.com', '[]', 0, '2020-07-10 06:50:35', '2020-07-10 06:50:35', '2021-07-10 12:20:35'),
('a561225455589d37f8057e7a5eda0f8a3764d9cfbe96fcf702b654bfaea3d9b08795da80c3066bdd', 1, 1, 'pradeep@groupkit.com', '[]', 0, '2020-07-10 06:50:35', '2020-07-10 06:50:35', '2021-07-10 12:20:35'),
('abbbe87ee380c14b93985f019f1264c1db938c3d6116c4805b2c765385456dae92d2e38d28f96176', 1, 1, 'pradeep@groupkit.com', '[]', 0, '2020-07-10 06:54:39', '2020-07-10 06:54:39', '2021-07-10 12:24:39'),
('fabde555a35411460b76b7ac3193795e15bf62a65c1b0ad80b9640c6ff659dd64d655f5249d4bd83', 1, 1, 'pradeep@groupkit.com', '[]', 0, '2020-07-12 22:58:57', '2020-07-12 22:58:57', '2021-07-13 04:28:57'),
('46d326347b15a58dc476517825e7e3e6b72e7d9cf9a0ca51f7b3164a8833c476dfab4f9e7e4e8fbe', 1, 1, 'pradeep@groupkit.com', '[]', 0, '2020-07-13 03:04:13', '2020-07-13 03:04:13', '2021-07-13 08:34:13'),
('734eecfcbe657e7a0fb7c23c6c756f1d231688d54b0561fb1d0bc82ff8cd34e9f52c1d19aef8d629', 1, 1, 'pradeep@groupkit.com', '[]', 0, '2020-07-13 22:28:12', '2020-07-13 22:28:12', '2021-07-14 03:58:12'),
('51953a647dff56ea1006abc17048e06c71f5057029b9b213b317cf522f3840809c7378b97084e3a4', 1, 1, 'pradeep@groupkit.com', '[]', 0, '2020-07-14 23:28:00', '2020-07-14 23:28:00', '2021-07-15 04:58:00'),
('e10f7a6849d72de88e53da537e991fd81a0caf49fb9b48885e4c112b90b435622a6d9fa9e134f59d', 1, 1, 'pradeep@groupkit.com', '[]', 0, '2020-07-14 23:37:44', '2020-07-14 23:37:44', '2021-07-15 05:07:44'),
('a8e7d8f5fa04190b8e881f6ccf5387e21f9598ea6a51a7c8319078dfe712f6da02ef43a77e2232f6', 1, 1, 'pradeep@groupkit.com', '[]', 0, '2020-07-15 23:14:58', '2020-07-15 23:14:58', '2021-07-16 04:44:58'),
('73eeec0bdbb0eb27271f600b043dada7ca8562ad9fa3ff50a755cd26d34f6376ec20da859e0341f6', 1, 1, 'pradeep@groupkit.com', '[]', 0, '2020-07-16 23:16:23', '2020-07-16 23:16:23', '2021-07-17 04:46:23'),
('54d9fa514ecc5b63253080169b014ede34efb618bc6a8aa625843a812ef7147eac96ea9751519c0b', 1, 1, 'pradeep@groupkit.com', '[]', 0, '2020-07-19 23:11:18', '2020-07-19 23:11:18', '2021-07-20 04:41:18');

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
  `txn_id` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payment_gross` float(20,10) DEFAULT NULL,
  `currency_code` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payer_email` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `receiver_email` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payment_date` timestamp NULL DEFAULT NULL,
  `payment_status` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `subscriptions` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_deleted` int(11) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`id`, `txn_id`, `payment_gross`, `currency_code`, `payer_email`, `receiver_email`, `payment_date`, `payment_status`, `user_id`, `subscriptions`, `is_deleted`, `created_at`, `updated_at`) VALUES
(1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, NULL, 0, '2020-07-13 23:52:35', '2020-07-13 23:52:35'),
(2, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, NULL, 0, '2020-07-13 23:55:53', '2020-07-13 23:55:53'),
(3, 'ch_1H4gUnLRkwVQ3lNkA2JS0ONl ', 10.1000003815, 'A', '', '', '2020-07-13 23:57:34', 'a', 1, '', 0, NULL, NULL),
(4, 'ch_1H4gh4LRkwVQ3lNkv6nDaqdM', 2000.0000000000, 'usd', NULL, '', '2020-07-13 23:58:42', 'succeeded', 1, NULL, 0, NULL, NULL),
(5, 'ch_1H4gimLRkwVQ3lNkXRzlJGU0', 4800.0000000000, 'usd', NULL, '', '2020-07-14 00:00:28', 'succeeded', 1, 'sub_HdygjIXbf7SKuf', 0, NULL, NULL),
(6, 'ch_1H4gmLLRkwVQ3lNkFHfM5T30', 4800.0000000000, 'usd', NULL, '', '2020-07-14 00:04:09', 'succeeded', 1, 'sub_HdyjRkxsWJZGUc', 0, '2020-07-14 00:04:13', '2020-07-14 00:04:13');

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
) ENGINE=MyISAM AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `plans`
--

INSERT INTO `plans` (`id`, `name`, `slug`, `stripe_plan`, `cost`, `description`, `is_deleted`, `created_at`, `updated_at`) VALUES
(4, 'GroupKit Pro', 'plan_H81ycCkDlKy6Ng', '30', 48.00, NULL, 0, '2020-07-10 00:05:18', '2020-07-10 00:05:18'),
(3, 'GroupKit Pro Annual', 'plan_H98gMql8UbiAgb', '365', 384.00, NULL, 0, '2020-07-10 00:05:18', '2020-07-10 00:46:41'),
(5, 'GroupKit Basic', 'plan_H81yEbnL2c1ng6', '30', 34.00, NULL, 0, '2020-07-10 00:05:18', '2020-07-10 00:05:18');

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
  `subscriptions` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
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
) ENGINE=MyISAM AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `subscriptions`
--

INSERT INTO `subscriptions` (`id`, `user_id`, `name`, `stripe_id`, `stripe_plan`, `stripe_status`, `quantity`, `trial_ends_at`, `ends_at`, `is_deleted`, `created_at`, `updated_at`) VALUES
(1, 1, 'GroupKit Basic', 'sub_HdiFGAcnRMjCD7', 'plan_H81yEbnL2c1ng6', 'trialing', 1, '2020-07-27 07:01:54', NULL, 0, '2020-07-13 07:01:58', '2020-07-13 07:01:58'),
(2, 1, 'GroupKit Pro', 'sub_HdiQMlX5stdISZ', 'plan_H81ycCkDlKy6Ng', 'active', 1, NULL, NULL, 0, '2020-07-13 07:12:24', '2020-07-13 07:12:24'),
(3, 1, 'GroupKit Pro', 'sub_HdjYrKtJ5k7nFB', 'plan_H81ycCkDlKy6Ng', 'active', 1, NULL, NULL, 0, '2020-07-13 08:23:01', '2020-07-13 08:23:01'),
(4, 1, 'GroupKit Pro', 'sub_HdjdztKzvXY4oL', 'plan_H81ycCkDlKy6Ng', 'active', 1, NULL, NULL, 0, '2020-07-13 08:28:15', '2020-07-13 08:28:15'),
(5, 1, 'GroupKit Pro', 'sub_HdjjafdweG8Q5I', 'plan_H81ycCkDlKy6Ng', 'active', 1, NULL, NULL, 0, '2020-07-13 08:33:34', '2020-07-13 08:33:34'),
(6, 1, 'GroupKit Basic', 'sub_HdjrWWHE1BP9EE', 'plan_H81yEbnL2c1ng6', 'active', 1, NULL, NULL, 0, '2020-07-13 08:41:47', '2020-07-13 08:41:47'),
(7, 1, 'GroupKit Pro', 'sub_HdjsOG8wLoj9K2', 'plan_H81ycCkDlKy6Ng', 'active', 1, NULL, NULL, 0, '2020-07-13 08:42:50', '2020-07-13 08:42:50'),
(8, 1, 'GroupKit Pro', 'sub_HdxNaldgQu1Y0W', 'plan_H81ycCkDlKy6Ng', 'active', 1, NULL, NULL, 0, '2020-07-13 22:39:36', '2020-07-13 22:39:36'),
(9, 1, 'GroupKit Basic', 'sub_HdxStSjoS4TOKT', 'plan_H81yEbnL2c1ng6', 'active', 1, NULL, NULL, 0, '2020-07-13 22:45:14', '2020-07-13 22:45:14'),
(10, 1, 'GroupKit Pro', 'sub_HdxckcKXMVOQ8q', 'plan_H81ycCkDlKy6Ng', 'active', 1, NULL, NULL, 0, '2020-07-13 22:54:40', '2020-07-13 22:54:40'),
(11, 1, 'GroupKit Pro', 'sub_HdygjIXbf7SKuf', 'plan_H81ycCkDlKy6Ng', 'active', 1, NULL, NULL, 0, '2020-07-14 00:00:28', '2020-07-14 00:00:28'),
(12, 1, 'GroupKit Pro', 'sub_HdyjRkxsWJZGUc', 'plan_H81ycCkDlKy6Ng', 'active', 1, NULL, NULL, 0, '2020-07-14 00:04:09', '2020-07-14 00:04:09');

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
) ENGINE=MyISAM AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `subscription_items`
--

INSERT INTO `subscription_items` (`id`, `subscription_id`, `stripe_id`, `stripe_plan`, `quantity`, `created_at`, `updated_at`) VALUES
(1, 1, 'si_HdiFZqadWQ5F8d', 'plan_H81yEbnL2c1ng6', 1, '2020-07-13 07:01:58', '2020-07-13 07:01:58'),
(2, 2, 'si_HdiQanw1X9V56x', 'plan_H81ycCkDlKy6Ng', 1, '2020-07-13 07:12:24', '2020-07-13 07:12:24'),
(3, 3, 'si_HdjYoFE5u433Xb', 'plan_H81ycCkDlKy6Ng', 1, '2020-07-13 08:23:01', '2020-07-13 08:23:01'),
(4, 4, 'si_HdjdKrKS7bE39T', 'plan_H81ycCkDlKy6Ng', 1, '2020-07-13 08:28:15', '2020-07-13 08:28:15'),
(5, 5, 'si_HdjjrDOiiUTBFV', 'plan_H81ycCkDlKy6Ng', 1, '2020-07-13 08:33:34', '2020-07-13 08:33:34'),
(6, 6, 'si_HdjrxfOB3w5kf3', 'plan_H81yEbnL2c1ng6', 1, '2020-07-13 08:41:47', '2020-07-13 08:41:47'),
(7, 7, 'si_Hdjs0UUMi6emwW', 'plan_H81ycCkDlKy6Ng', 1, '2020-07-13 08:42:50', '2020-07-13 08:42:50'),
(8, 8, 'si_HdxNUgd84FgjRv', 'plan_H81ycCkDlKy6Ng', 1, '2020-07-13 22:39:36', '2020-07-13 22:39:36'),
(9, 9, 'si_HdxSArlpNZ65lm', 'plan_H81yEbnL2c1ng6', 1, '2020-07-13 22:45:14', '2020-07-13 22:45:14'),
(10, 10, 'si_HdxcDnILil256g', 'plan_H81ycCkDlKy6Ng', 1, '2020-07-13 22:54:40', '2020-07-13 22:54:40'),
(11, 11, 'si_HdygeL6nXjpRRw', 'plan_H81ycCkDlKy6Ng', 1, '2020-07-14 00:00:28', '2020-07-14 00:00:28'),
(12, 12, 'si_HdyjBseuIQLQCb', 'plan_H81ycCkDlKy6Ng', 1, '2020-07-14 00:04:09', '2020-07-14 00:04:09');

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
(1, 'Pre', 'pradeep@groupkit.com', NULL, '$2y$10$IHWmr1zenExXxOyAWSANK.GR0YUpcG4Ro7ain4lL6MNLS1GjVKoNK', 0, NULL, '2020-07-08 06:06:29', '2020-07-13 07:00:22', 'cus_HdiEGmQfEbCnjx', 'visa', '4242', NULL);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
