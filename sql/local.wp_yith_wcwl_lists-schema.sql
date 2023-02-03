/*!40101 SET NAMES binary*/;
/*!40014 SET FOREIGN_KEY_CHECKS=0*/;

CREATE TABLE `wp_yith_wcwl_lists` (
  `ID` bigint(20) NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) DEFAULT NULL,
  `session_id` varchar(255) DEFAULT NULL,
  `wishlist_slug` varchar(200) NOT NULL,
  `wishlist_name` text,
  `wishlist_token` varchar(64) NOT NULL,
  `wishlist_privacy` tinyint(1) NOT NULL DEFAULT '0',
  `is_default` tinyint(1) NOT NULL DEFAULT '0',
  `dateadded` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expiration` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`ID`),
  UNIQUE KEY `wishlist_token` (`wishlist_token`),
  KEY `wishlist_slug` (`wishlist_slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
