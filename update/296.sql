CREATE TABLE `lb_pool_members` (  `member_id` int(11) NOT NULL AUTO_INCREMENT,  `device_id` int(11) NOT NULL,  `member_name` varchar(128) COLLATE utf8_unicode_ci NOT NULL,  `pool_name` varchar(128) COLLATE utf8_unicode_ci NOT NULL,  `member_ip` varchar(64) COLLATE utf8_unicode_ci NOT NULL,  `member_port` int(8) NOT NULL,  `member_state` int(1) NOT NULL,  `member_enabled` int(1) NOT NULL,  `member_conns` int(11) NOT NULL,  `member_bps_in` int(11) NOT NULL,  `member_bps_out` int(11) NOT NULL,  PRIMARY KEY (`member_id`,`pool_name`) ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
ALTER TABLE  `lb_pool_members` ADD INDEX (  `device_id` ) ;