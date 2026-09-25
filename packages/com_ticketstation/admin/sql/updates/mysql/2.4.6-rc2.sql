-- The note a customer adds in the cart gets its own table, separate from the Box Office
-- "Order Reference" (#__ticketstation_remarks), which is printed on the tickets
CREATE TABLE IF NOT EXISTS `#__ticketstation_customer_notes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ordercode` int(11) NOT NULL,
  `note` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `ordercode` (`ordercode`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;
