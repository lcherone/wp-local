/*!40101 SET NAMES binary*/;
/*!40014 SET FOREIGN_KEY_CHECKS=0*/;

CREATE TABLE `wp_woof_query_cache` (
  `mkey` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mvalue` text COLLATE utf8mb4_unicode_ci NOT NULL,
  KEY `mkey` (`mkey`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
