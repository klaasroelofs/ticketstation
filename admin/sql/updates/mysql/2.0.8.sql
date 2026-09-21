CREATE TABLE IF NOT EXISTS `#__ticketstation_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ordercode` varchar(50) NOT NULL,
  `event_type` varchar(50) NOT NULL,
  `message` varchar(500) NOT NULL,
  `context` text DEFAULT NULL,
  `actor` varchar(150) DEFAULT NULL,
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ordercode` (`ordercode`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;
