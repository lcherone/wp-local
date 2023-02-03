/*!40101 SET NAMES binary*/;
/*!40014 SET FOREIGN_KEY_CHECKS=0*/;

CREATE TABLE `wp_yith_wcwl` (
  `ID` bigint(20) NOT NULL AUTO_INCREMENT,
  `prod_id` bigint(20) NOT NULL,
  `quantity` int(11) NOT NULL,
  `user_id` bigint(20) DEFAULT NULL,
  `wishlist_id` bigint(20) DEFAULT NULL,
  `position` int(11) DEFAULT '0',
  `original_price` decimal(9,3) DEFAULT NULL,
  `original_currency` char(3) DEFAULT NULL,
  `dateadded` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `on_sale` tinyint(4) NOT NULL DEFAULT '0',
  PRIMARY KEY (`ID`),
  KEY `prod_id` (`prod_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
