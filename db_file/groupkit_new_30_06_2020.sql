-- phpMyAdmin SQL Dump
-- version 4.8.5
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Jun 30, 2020 at 10:51 AM
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
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `fb_id` varchar(100) NOT NULL,
  `responder_type` varchar(100) NOT NULL,
  `responder_json` text NOT NULL,
  `user_id` int(11) NOT NULL,
  `group_id` int(11) NOT NULL,
  `is_check` int(11) NOT NULL,
  `created_at` timestamp NOT NULL,
  `updated_at` timestamp NOT NULL,
  `is_deleted` tinyint(4) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `facebook_groups`
--

DROP TABLE IF EXISTS `facebook_groups`;
CREATE TABLE IF NOT EXISTS `facebook_groups` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `fb_name` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `fb_num` int(11) NOT NULL,
  `fb_id` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `is_deleted` tinyint(4) NOT NULL DEFAULT '0',
  `img` longtext COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `facebook_groups`
--

INSERT INTO `facebook_groups` (`id`, `fb_name`, `fb_num`, `fb_id`, `user_id`, `created_at`, `updated_at`, `is_deleted`, `img`) VALUES
(3, 'QTech2', 0, '547486429302265', 1, '2020-06-29 00:33:52', '2020-06-29 09:01:09', 1, NULL),
(4, 'Qodic_1', 0, '3102027389891885', 1, '2020-06-29 00:34:33', '2020-06-29 03:19:04', 0, NULL);

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
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `f_name` varchar(100) NOT NULL,
  `l_name` varchar(100) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `fb_id` varchar(100) NOT NULL,
  `a1` longtext,
  `a2` longtext,
  `a3` longtext,
  `notes` longtext,
  `user_id` int(11) NOT NULL,
  `date_add_time` timestamp NULL DEFAULT NULL,
  `respond_status` varchar(100) DEFAULT NULL,
  `group_id` int(11) NOT NULL,
  `img` longtext,
  `created_at` timestamp NOT NULL,
  `updated_at` timestamp NOT NULL,
  `is_deleted` tinyint(4) NOT NULL DEFAULT '0',
  `tags` longtext,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=latin1;

--
-- Dumping data for table `group_members`
--

INSERT INTO `group_members` (`id`, `f_name`, `l_name`, `email`, `fb_id`, `a1`, `a2`, `a3`, `notes`, `user_id`, `date_add_time`, `respond_status`, `group_id`, `img`, `created_at`, `updated_at`, `is_deleted`, `tags`) VALUES
(4, 'Dev74', 'Devloper', 'gft@gmail.com', '100022526884794', 'gft@gmail.com', '132', '1', '1', 1, '2020-06-29 00:33:52', 'N/A', 3, 'https://scontent.famd1-2.fna.fbcdn.net/v/t1.30497-1/cp0/c18.0.60.60a/p60x60/84241059_189132118950875_4138507100605120512_n.jpg?_nc_cat=1&_nc_sid=7206a8&_nc_oc=AQlHD0pEkcwdM0Yl6Cqrcm2sys8OUFZ4xL4-6GC0a0rt_JRfBeaJwTcW9Igfv3Ya3Dn2UBYIPEvGLch8AcIika65&_nc_ht=scontent.famd1-2.fna&oh=0614e6c88a4e516118b1db8da721da64&oe=5F20C473', '2020-06-28 18:30:00', '2020-06-29 07:45:22', 0, '1'),
(5, 'Dev', 'Devloper', 'test@gmail.com', '100022526884794', '43', 'test@gmail.com', NULL, NULL, 1, '2020-06-29 00:34:33', 'N/A', 4, 'https://scontent.famd1-2.fna.fbcdn.net/v/t1.30497-1/cp0/c18.0.60.60a/p60x60/84241059_189132118950875_4138507100605120512_n.jpg?_nc_cat=1&_nc_sid=7206a8&_nc_oc=AQlHD0pEkcwdM0Yl6Cqrcm2sys8OUFZ4xL4-6GC0a0rt_JRfBeaJwTcW9Igfv3Ya3Dn2UBYIPEvGLch8AcIika65&_nc_ht=scontent.famd1-2.fna&oh=0614e6c88a4e516118b1db8da721da64&oe=5F20C473', '2020-06-29 00:50:00', '2020-06-29 02:55:05', 0, NULL);

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
) ENGINE=MyISAM AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `migrations`
--

INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES
(1, '2014_10_12_000000_create_users_table', 1),
(2, '2014_10_12_100000_create_password_resets_table', 1),
(3, '2016_06_01_000001_create_oauth_auth_codes_table', 1),
(4, '2016_06_01_000002_create_oauth_access_tokens_table', 1),
(5, '2016_06_01_000003_create_oauth_refresh_tokens_table', 1),
(6, '2016_06_01_000004_create_oauth_clients_table', 1),
(7, '2016_06_01_000005_create_oauth_personal_access_clients_table', 1),
(8, '2019_05_03_000001_create_customer_columns', 1),
(9, '2019_05_03_000002_create_subscriptions_table', 1),
(10, '2019_05_03_000003_create_subscription_items_table', 1),
(11, '2019_08_19_000000_create_failed_jobs_table', 1),
(12, '2020_06_15_092740_create_plans_table', 1);

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
('d21461fba5962bd26a89f9b4bb5607ca4945f0b58dea76323bcbc37431985a66b6f041719062d720', 1, 1, 'ajay.qodic@gmail.com', '[]', 0, '2020-06-29 00:31:07', '2020-06-29 00:31:07', '2021-06-29 06:01:07'),
('9b9bcf97164bd2b4999d5aef6129e82bbc1ebaf2e29de62361777050f695ec6acf109ada82bb1881', 1, 1, 'ajay.qodic@gmail.com', '[]', 0, '2020-06-29 00:36:39', '2020-06-29 00:36:39', '2021-06-29 06:06:39'),
('07e6498c8222df2ecd0bca20c64b58616647536f5d63684f916d8daa084866fc5cbbfbccc7590e2d', 1, 1, 'ajay.qodic@gmail.com', '[]', 0, '2020-06-29 23:46:13', '2020-06-29 23:46:13', '2021-06-30 05:16:13'),
('efa8c519d409249b3032bbc88663e244515675cf4e723b98de2a6bdcef372049a1be4f12a3def80d', 1, 1, 'ajay.qodic@gmail.com', '[]', 0, '2020-06-30 00:07:34', '2020-06-30 00:07:34', '2021-06-30 05:37:34'),
('04c5132840ba8200998720547fcf2f951980987e40408771db510d494525cd47de346ec4938a0c70', 1, 1, 'ajay.qodic@gmail.com', '[]', 0, '2020-06-30 00:22:21', '2020-06-30 00:22:21', '2021-06-30 05:52:21'),
('c9a8dd794e5d845c8e0678dd6ce6f10f75012a28c25f7ff05b3397b2add29e952b5aa754c5d0bdba', 1, 1, 'ajay.qodic@gmail.com', '[]', 0, '2020-06-30 00:55:42', '2020-06-30 00:55:42', '2021-06-30 06:25:42'),
('d8cd0a276976cf0cf8057d4b1d37d574ab907c3789c62961922ae027c4166c978aae7cf8a0c845a2', 1, 1, 'ajay.qodic@gmail.com', '[]', 0, '2020-06-30 00:59:23', '2020-06-30 00:59:23', '2021-06-30 06:29:23'),
('926f0430d02082774852c65913cf76220a33d6ea2025857408463183f3aee27a0863c15dbe7cc0b2', 1, 1, 'ajay.qodic@gmail.com', '[]', 0, '2020-06-30 01:09:45', '2020-06-30 01:09:45', '2021-06-30 06:39:45'),
('2b85e600a744ff1345d54c57487845d3d1d3368df743821e1b156c5257a22afec65f323e030c84ac', 1, 1, 'ajay.qodic@gmail.com', '[]', 0, '2020-06-30 02:11:36', '2020-06-30 02:11:36', '2021-06-30 07:41:36'),
('b0c42f639a69b42e0582889976dc2bda1fc1ff0a9e695675fda9e31a7dcdc39c6d3645c34ab8e285', 1, 1, 'ajay.qodic@gmail.com', '[]', 0, '2020-06-30 02:11:47', '2020-06-30 02:11:47', '2021-06-30 07:41:47'),
('bf73584b552b302da7a0b9dfdf30149cb5431148a03b207ecbf7a63b602347658f72858eebfd29eb', 1, 1, 'ajay.qodic@gmail.com', '[]', 0, '2020-06-30 02:16:02', '2020-06-30 02:16:02', '2021-06-30 07:46:02'),
('ece0bd0132ab8199a8b09bae51b091184a51522e3bfa74a5bd44b2757ddd72874537e029193e82f9', 1, 1, 'ajay.qodic@gmail.com', '[]', 0, '2020-06-30 02:24:14', '2020-06-30 02:24:14', '2021-06-30 07:54:14'),
('714a5cee8bbf302b2f11aed53a5bbf69f7f5a130866159098016ebbffd520fc9be2f9a46ddd35ab6', 1, 1, 'ajay.qodic@gmail.com', '[]', 0, '2020-06-30 02:24:18', '2020-06-30 02:24:18', '2021-06-30 07:54:18'),
('9f7d3c2ef6f2c8a33e0bf50df8c6b70b0b39df831780ceb339dae84421066d64e956cd7f290b47f5', 1, 1, 'ajay.qodic@gmail.com', '[]', 0, '2020-06-30 02:28:05', '2020-06-30 02:28:05', '2021-06-30 07:58:05'),
('be62d3fee99670ac2d771e0b3b0071c881c8deddb18e739b28d87121c195179a6c1b95162478bf77', 1, 1, 'ajay.qodic@gmail.com', '[]', 0, '2020-06-30 02:39:38', '2020-06-30 02:39:38', '2021-06-30 08:09:38'),
('3843ca2d2e3f74d6bfa6649a1dc7e27c3c4edb71fb46155e722d48014a9772fb6adf363a7b97fd91', 1, 1, 'ajay.qodic@gmail.com', '[]', 0, '2020-06-30 02:50:24', '2020-06-30 02:50:24', '2021-06-30 08:20:24'),
('38ec79d5ccfaa8bb87bbc99db4553c90fa63b14b0d83a5e91d79c6c5b91725579a251447071a4069', 1, 1, 'ajay.qodic@gmail.com', '[]', 0, '2020-06-30 02:55:47', '2020-06-30 02:55:47', '2021-06-30 08:25:47'),
('0b136bd95dd93f57cfb7c4155f835cf0db55b20fbad5449510f6f55ce87aec6e733030c868c1c70f', 1, 1, 'ajay.qodic@gmail.com', '[]', 0, '2020-06-30 02:57:10', '2020-06-30 02:57:10', '2021-06-30 08:27:10'),
('bcf8f6ec46ebe7ba00414b7528ba02bad54a5cd0d6202d86c0a49e69ac8e2febde74ec99bd098acf', 1, 1, 'ajay.qodic@gmail.com', '[]', 0, '2020-06-30 02:58:28', '2020-06-30 02:58:28', '2021-06-30 08:28:28'),
('e0b0d6eed91defad0bac26d6fdd7b0dabcdcc0b7e23e150af4ad0a511cae8d10a63d1fa5a09c6b7d', 1, 1, 'ajay.qodic@gmail.com', '[]', 0, '2020-06-30 02:59:47', '2020-06-30 02:59:47', '2021-06-30 08:29:47'),
('4bac437226e579780ca57a1d36af2aa688bcac29a33dbfe4a061807061a6a72f23bde3435e6e5569', 1, 1, 'ajay.qodic@gmail.com', '[]', 0, '2020-06-30 03:11:08', '2020-06-30 03:11:08', '2021-06-30 08:41:08'),
('53ff5f5b8604ebcd03e235585ad0ed170c44a45aa5dcca388b2bbb60c4385f45431de50b2afe729f', 1, 1, 'ajay.qodic@gmail.com', '[]', 0, '2020-06-30 03:13:05', '2020-06-30 03:13:05', '2021-06-30 08:43:05'),
('99f91b880d4ac6c05cf04c1a074af001315f4d73f2ab5cb253981f430ce643308dcf9ac2ab2ff601', 1, 1, 'ajay.qodic@gmail.com', '[]', 0, '2020-06-30 03:14:43', '2020-06-30 03:14:43', '2021-06-30 08:44:43'),
('7ee57976f8a1c547aaa9f1877b8e66d7f91eb02c8fe1fe0525a6c23157178804844545fe1720bd74', 1, 1, 'ajay.qodic@gmail.com', '[]', 0, '2020-06-30 03:15:46', '2020-06-30 03:15:46', '2021-06-30 08:45:46'),
('934a84084bc2cda2ccd2b049fa67327212f82e0fac4c6234a87775ec462f2f89afa06e15bff65161', 1, 1, 'ajay.qodic@gmail.com', '[]', 0, '2020-06-30 03:16:02', '2020-06-30 03:16:02', '2021-06-30 08:46:02'),
('2ea18db680d02ae83f5599ff68c258a35ce18143533fd76d95950fb4acee763a2ad1f619853ac6c5', 1, 1, 'ajay.qodic@gmail.com', '[]', 0, '2020-06-30 03:17:53', '2020-06-30 03:17:53', '2021-06-30 08:47:53'),
('607f70009d836a4a2791c78719a451b7a2826298a5a8e21ca7cd62100af30c853c844878c0ae0225', 1, 1, 'ajay.qodic@gmail.com', '[]', 0, '2020-06-30 03:17:59', '2020-06-30 03:17:59', '2021-06-30 08:47:59'),
('f8ac6afe1dd8274aecf27bbba04abbec3b9220002cd85167776ae0bdd33f044a8520caeae4279e24', 1, 1, 'ajay.qodic@gmail.com', '[]', 0, '2020-06-30 03:29:32', '2020-06-30 03:29:32', '2021-06-30 08:59:32'),
('7bd845f4a438b408d3f219a2a23f6a9e918fe698e11c203533e1d031e8f2df129af2323bab3ee8d2', 1, 1, 'ajay.qodic@gmail.com', '[]', 0, '2020-06-30 03:29:38', '2020-06-30 03:29:38', '2021-06-30 08:59:38'),
('92cab71c16ab68f3f3a46987a91223c18c7ea9023768404d127e2276c2d581832f1935554555d97b', 1, 1, 'ajay.qodic@gmail.com', '[]', 0, '2020-06-30 03:33:04', '2020-06-30 03:33:04', '2021-06-30 09:03:04'),
('bf90a5ccb3b5750fdb5bae9adcc801d7c133bf540dc06593509ae45678c1b3afff39bbdc32b2fb7c', 1, 1, 'ajay.qodic@gmail.com', '[]', 0, '2020-06-30 03:38:11', '2020-06-30 03:38:11', '2021-06-30 09:08:11'),
('327d1951d39432fa6371a16b03242b18b2991699aa155b6b7d3fb593e2a23c95504ac078cc70775d', 1, 1, 'ajay.qodic@gmail.com', '[]', 0, '2020-06-30 03:39:04', '2020-06-30 03:39:04', '2021-06-30 09:09:04'),
('35b9cdccfcd5b932b0cb94f2331dd62a64b844bea441ca2ea6b193298e68ea1ceef98b4740de45f4', 1, 1, 'ajay.qodic@gmail.com', '[]', 0, '2020-06-30 03:43:34', '2020-06-30 03:43:34', '2021-06-30 09:13:34'),
('ce5fb8ba65a6eef7d19649156491d83ba268964cb6592a7b9da7f9497b5982c6d8f5470b6fd0f65d', 1, 1, 'ajay.qodic@gmail.com', '[]', 0, '2020-06-30 03:44:43', '2020-06-30 03:44:43', '2021-06-30 09:14:43'),
('2facee05d2c09263e68d8a8cb90d26d15a4c39327cf3d9ab277a08d937caf19920533f345e35bc79', 1, 1, 'ajay.qodic@gmail.com', '[]', 0, '2020-06-30 03:45:21', '2020-06-30 03:45:21', '2021-06-30 09:15:21'),
('22cdc06eab370ac3b3df3a8eb92da1bdcdfcab4097fed6a07454c905fc4abc1c0091acd15fe195ec', 1, 1, 'ajay.qodic@gmail.com', '[]', 0, '2020-06-30 03:46:25', '2020-06-30 03:46:25', '2021-06-30 09:16:25'),
('2b096a98968a94c4593d76a1479b929acaaa81429bfa39049030ff59edf46e3de78d74bdd64ab976', 1, 1, 'ajay.qodic@gmail.com', '[]', 0, '2020-06-30 04:11:35', '2020-06-30 04:11:35', '2021-06-30 09:41:35'),
('c8c1cf6be86f5f5e2ce2cf6b41bc2255a1cf97bb3c74436f465fcaf7b9f6a6123c50a2a77f88f76c', 1, 1, 'ajay.qodic@gmail.com', '[]', 0, '2020-06-30 04:13:08', '2020-06-30 04:13:08', '2021-06-30 09:43:08'),
('973e0bad1205fa8e3ce4c93f8dbe4dc83701266cda5e5496848b989a0ca031637353f838c04bc135', 1, 1, 'ajay.qodic@gmail.com', '[]', 0, '2020-06-30 04:13:33', '2020-06-30 04:13:33', '2021-06-30 09:43:33'),
('6809de20fb3094e69eff9fec448e10478b99ed9d97fa4347fc54b20f7dba43f6442fe43d831a56d0', 1, 1, 'ajay.qodic@gmail.com', '[]', 0, '2020-06-30 04:15:33', '2020-06-30 04:15:33', '2021-06-30 09:45:33'),
('c6d2a9b05935d33b47771aafe317b34b0685757724cada1b8d998f2e24d5d335ed6b29e845efe666', 1, 1, 'ajay.qodic@gmail.com', '[]', 0, '2020-06-30 04:16:15', '2020-06-30 04:16:15', '2021-06-30 09:46:15'),
('e39432a9fe1fe3dd3d43f792f164c6ffdd62a673e04728e18e3b3523649c18c7209c7fe87529e407', 1, 1, 'ajay.qodic@gmail.com', '[]', 0, '2020-06-30 04:20:10', '2020-06-30 04:20:10', '2021-06-30 09:50:10'),
('4acf5f195a1f4d447af154b819e7d114e57d9d398a73dea135e8766e2d9ba7a1c7c611424a28fef3', 1, 1, 'ajay.qodic@gmail.com', '[]', 0, '2020-06-30 04:20:50', '2020-06-30 04:20:50', '2021-06-30 09:50:50'),
('aa14ac49795947fff136729dc3dab3e28ad1cf363d4cd50e947121edf586432fcfdda4888ce1a33c', 1, 1, 'ajay.qodic@gmail.com', '[]', 0, '2020-06-30 04:37:54', '2020-06-30 04:37:54', '2021-06-30 10:07:54'),
('3ce36480fa6be682e515434cad8821e532eb840302fd9e470c38f4429df326a88027f4a9bd46c9fa', 1, 1, 'ajay.qodic@gmail.com', '[]', 0, '2020-06-30 04:44:20', '2020-06-30 04:44:20', '2021-06-30 10:14:20'),
('f0cf62c21aa4d1d5d5206f43890673fad03764c591844a36a93b65915d21cfa7322d11659d712aef', 1, 1, 'ajay.qodic@gmail.com', '[]', 0, '2020-06-30 04:46:55', '2020-06-30 04:46:55', '2021-06-30 10:16:55'),
('8ec3c3d98346fbcbbea925a21dfd21f817ea38a1db39c0841a869957392bce3fed3e4e04dc5f9878', 1, 1, 'ajay.qodic@gmail.com', '[]', 0, '2020-06-30 04:48:21', '2020-06-30 04:48:21', '2021-06-30 10:18:21'),
('27a49c74c8713e085e7d004232ed48a1831f26a1274c1ecd5b877282fab90f8919c0584a5848adc2', 1, 1, 'ajay.qodic@gmail.com', '[]', 0, '2020-06-30 04:48:33', '2020-06-30 04:48:33', '2021-06-30 10:18:33'),
('f4958d1c0f7f909a8bb9c53821efa9c1cc46cc73f0bd8080e6d837c328293f0ec2287b8b1ea56860', 1, 1, 'ajay.qodic@gmail.com', '[]', 0, '2020-06-30 04:51:40', '2020-06-30 04:51:40', '2021-06-30 10:21:40'),
('002c06534395fd863d1b9c3a6f009f636cbce15fcca9f5bebd5caace702addd09a8d7c2a11fa4e54', 1, 1, 'ajay.qodic@gmail.com', '[]', 0, '2020-06-30 04:57:48', '2020-06-30 04:57:48', '2021-06-30 10:27:48'),
('77f4ffffa41334ba712b92227cc7367ecc3655f9471c1ede494abec0a4c0e8785b05e4919fb8c94a', 1, 1, 'ajay.qodic@gmail.com', '[]', 0, '2020-06-30 04:58:32', '2020-06-30 04:58:32', '2021-06-30 10:28:32'),
('8d7ee13dea195c8501028d9477b1e527c585180d37a03ed9f75f625fdb388fb90fe614dc9b2d60d5', 1, 1, 'ajay.qodic@gmail.com', '[]', 0, '2020-06-30 04:58:57', '2020-06-30 04:58:57', '2021-06-30 10:28:57'),
('9bf4680c519b8c4d2fce7a3e864e6e3bceb622eb7bc02fffc22e081ec128ff7a18789e524c9859a1', 1, 1, 'ajay.qodic@gmail.com', '[]', 0, '2020-06-30 04:58:58', '2020-06-30 04:58:58', '2021-06-30 10:28:58'),
('687a602e3f8ccb15e8a12fd4801d8138fef1fadae5c7b00f4b67dd07202f69a1eac5beb806ee83ad', 1, 1, 'ajay.qodic@gmail.com', '[]', 0, '2020-06-30 05:02:27', '2020-06-30 05:02:27', '2021-06-30 10:32:27'),
('ec45092f0fdb736cebbb97e76b98dfc0060e77487e38453f8c0e3f7c1706ad30d2706c791d9bb37b', 1, 1, 'ajay.qodic@gmail.com', '[]', 0, '2020-06-30 05:04:24', '2020-06-30 05:04:24', '2021-06-30 10:34:24'),
('4797a1f77f1ecae11f208d5cb534fed7bba073f8dc2b220dba85b1e05e0a539b4c364a4361252eed', 1, 1, 'ajay.qodic@gmail.com', '[]', 0, '2020-06-30 05:05:17', '2020-06-30 05:05:17', '2021-06-30 10:35:17'),
('e6706551ddec3bf01c64c86d17bb029e1b8117aeb1530baee26e57ce0c7a8bec3a276f0e0a60858f', 1, 1, 'ajay.qodic@gmail.com', '[]', 0, '2020-06-30 05:06:01', '2020-06-30 05:06:01', '2021-06-30 10:36:01'),
('dcfc84a2d3c32fc7917a74340f76a62f06d2f6f356ba5b73978203cc1d24a2d140dff08500e85477', 1, 1, 'ajay.qodic@gmail.com', '[]', 0, '2020-06-30 05:07:24', '2020-06-30 05:07:24', '2021-06-30 10:37:24'),
('4aa42b80590b4f05cdbc21971aa86da5e4130dea064b4295a2ff300b8b238a6e0b9e0a79af332fb2', 1, 1, 'ajay.qodic@gmail.com', '[]', 0, '2020-06-30 05:08:10', '2020-06-30 05:08:10', '2021-06-30 10:38:10'),
('9fc4a9bcd6c98facfc661938c5795456b95887305a4b2ee1ed899e5b4ffde497f06ae3d65e494be6', 1, 1, 'ajay.qodic@gmail.com', '[]', 0, '2020-06-30 05:12:01', '2020-06-30 05:12:01', '2021-06-30 10:42:01'),
('cf5baa0199e9041b1cc82d2aa576ecb606c73d3866230a4abaab83addc0039462447c38694eabb83', 1, 1, 'ajay.qodic@gmail.com', '[]', 0, '2020-06-30 05:14:14', '2020-06-30 05:14:14', '2021-06-30 10:44:14'),
('9914086702961045f48fcdf2d72b7a64a6ce8c7636ad458312eb282234d56cc65b7f234d62d2c481', 1, 1, 'ajay.qodic@gmail.com', '[]', 0, '2020-06-30 05:14:44', '2020-06-30 05:14:44', '2021-06-30 10:44:44'),
('c56f2b3cf8e13fc73df003a49ee798a90e39e2ec092e88f0dcd32e51bd66f4e6a15fa8c3595de181', 1, 1, 'ajay.qodic@gmail.com', '[]', 0, '2020-06-30 05:16:55', '2020-06-30 05:16:55', '2021-06-30 10:46:55'),
('b87546515e9528ff9310795bd019366dc0c50b8d7b98d942e1d1f05b54c452e14b4f38f2b28a7260', 1, 1, 'ajay.qodic@gmail.com', '[]', 0, '2020-06-30 05:18:09', '2020-06-30 05:18:09', '2021-06-30 10:48:09'),
('4d6e31ebd3e0c358f4487f9d325e194b1d1e82ad61ad32d268e43bc0ce0d4ae0e8e7308ab5547345', 1, 1, 'ajay.qodic@gmail.com', '[]', 0, '2020-06-30 05:19:51', '2020-06-30 05:19:51', '2021-06-30 10:49:51'),
('bf4ef9c6ebe75982ac58f9bca87c9fd8a439cef9f5b55262c69ee04f1a5099cf0716d0ee947eb70a', 1, 1, 'ajay.qodic@gmail.com', '[]', 0, '2020-06-30 05:20:36', '2020-06-30 05:20:36', '2021-06-30 10:50:36'),
('c0770cc527bbd05d29e5c52521ee35c9d7b7194cb2e82f92ad84f3877e01e8793443bfe462857f61', 1, 1, 'ajay.qodic@gmail.com', '[]', 0, '2020-06-30 05:20:37', '2020-06-30 05:20:37', '2021-06-30 10:50:37');

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
(1, NULL, 'y', 'x512RBovmLklIpNWyTRooGAYtWgXXlJ2cvLdPyd7', NULL, 'http://localhost', 1, 0, 0, '2020-06-29 00:30:42', '2020-06-29 00:30:42');

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
(1, 1, '2020-06-29 00:30:42', '2020-06-29 00:30:42');

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
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `plans_slug_unique` (`slug`)
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
  `quantity` int(11) NOT NULL,
  `trial_ends_at` timestamp NULL DEFAULT NULL,
  `ends_at` timestamp NULL DEFAULT NULL,
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

INSERT INTO `users` (`id`, `name`, `email`, `email_verified_at`, `password`, `remember_token`, `created_at`, `updated_at`, `stripe_id`, `card_brand`, `card_last_four`, `trial_ends_at`) VALUES
(1, 'Ajay', 'ajay.qodic@gmail.com', '2020-06-29 00:22:16', '$2y$10$je65TW2B/eCNM56LP1W4v.2vpYqz.fIl/CUE6n/TP3UOFzEkpRXKm', 'drO2LwhJTwo2TClWxU3HQGiJsCAIg9yNWGdofzDrjT9RUv9iwt8VYV4wXYs4', '2020-06-29 00:21:50', '2020-06-29 00:38:37', NULL, NULL, NULL, NULL);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
