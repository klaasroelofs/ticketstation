-- Customer portal, Joomla accounts: addresses a customer has linked to their account, after confirming
-- with the link in a mail. portal_mode 2 (email link and Joomla account) is a value of the existing
-- column, nothing to change there.
CREATE TABLE IF NOT EXISTS `#__ticketstation_portal_links` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `userid` int(11) NOT NULL,
  `emailaddress` varchar(255) NOT NULL,
  `created` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_portal_link` (`userid`, `emailaddress`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;
