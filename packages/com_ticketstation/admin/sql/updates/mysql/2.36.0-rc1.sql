-- Customer portal "My orders": off by default. portal_mode 0 = off, 1 = on (customers get a link by
-- email); portal_past_months limits how far back past orders are shown, 0 = always.
ALTER TABLE `#__ticketstation_config` ADD COLUMN `portal_mode` tinyint(1) NOT NULL DEFAULT 0 /** CAN FAIL **/;
ALTER TABLE `#__ticketstation_config` ADD COLUMN `portal_past_months` smallint(4) NOT NULL DEFAULT 0 /** CAN FAIL **/;

-- The requests for a link by email, to limit how often one address or one visitor can ask. Only a
-- hash of the address is kept.
CREATE TABLE IF NOT EXISTS `#__ticketstation_portal_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `emailhash` char(64) NOT NULL,
  `ip` varchar(60) NOT NULL DEFAULT '',
  `created` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_portal_email` (`emailhash`, `created`),
  KEY `idx_portal_ip` (`ip`, `created`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

-- The mail with the link to the portal (template 9).
INSERT IGNORE INTO `#__ticketstation_templates` VALUES("9","Link to My orders","<p>Hi {firstname},</p><p>Use the link below to see your orders: download your tickets, add them to your wallet or calendar, or get your invoice. The link works for {minutes} minutes.</p><p>{portallink}</p><p>Did you not ask for this? Then you can ignore this mail; nothing changes.</p><p>Kind regards,</p><p>{company_name}<br />{company_website}</p>","Your orders");
