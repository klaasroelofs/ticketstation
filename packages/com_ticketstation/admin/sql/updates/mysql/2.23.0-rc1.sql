-- Live updates of Apple Wallet passes: switch (off by default), the moment a pass last changed
-- (milliseconds; the update tag the devices ask for), the message the pass shows now, and the
-- devices that registered for a pass (the PassKit web service).
ALTER TABLE `#__ticketstation_config` ADD COLUMN `wallet_apple_updates` tinyint(1) NOT NULL DEFAULT 0 /** CAN FAIL **/;

ALTER TABLE `#__ticketstation_wallet_passes` ADD COLUMN `updated_at` bigint(20) NOT NULL DEFAULT 0 /** CAN FAIL **/;
ALTER TABLE `#__ticketstation_wallet_passes` ADD COLUMN `message_shown` int(11) NOT NULL DEFAULT 0 /** CAN FAIL **/;

CREATE TABLE IF NOT EXISTS `#__ticketstation_wallet_devices` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `device_id` varchar(64) NOT NULL,
  `pass_row` int(11) NOT NULL,
  `push_token` varchar(255) NOT NULL DEFAULT '',
  `created` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_device_pass` (`device_id`, `pass_row`),
  KEY `idx_pass_row` (`pass_row`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;
