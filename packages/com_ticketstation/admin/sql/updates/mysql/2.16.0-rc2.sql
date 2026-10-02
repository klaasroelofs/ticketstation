-- The wallet passes handed out per ticket (Apple serial numbers, Google class and object IDs),
-- so a later version with live updates can update the passes customers already have.
CREATE TABLE IF NOT EXISTS `#__ticketstation_wallet_passes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ordercode` varchar(50) NOT NULL,
  `orderid` int(11) NOT NULL,
  `wallet` varchar(10) NOT NULL,
  `pass_id` varchar(255) NOT NULL,
  `class_id` varchar(255) NOT NULL DEFAULT '',
  `created` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_wallet_pass` (`wallet`, `pass_id`),
  KEY `idx_orderid` (`orderid`),
  KEY `idx_ordercode` (`ordercode`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;
