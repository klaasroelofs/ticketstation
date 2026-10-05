-- Live updates of Google Wallet passes: switch (off by default; the service account needs access to
-- the issuer for it), and per recorded pass what still has to be sent to Google.
ALTER TABLE `#__ticketstation_config` ADD COLUMN `wallet_google_updates` tinyint(1) NOT NULL DEFAULT 0 /** CAN FAIL **/;

-- update_pending: the pass has to be brought in line with its ticket; message_pending: the id of a
-- message (#__ticketstation_wallet_messages) still to be shown on it; updated: the last time Google
-- accepted something; update_error: why the last attempt failed.
ALTER TABLE `#__ticketstation_wallet_passes` ADD COLUMN `update_pending` tinyint(1) NOT NULL DEFAULT 0 /** CAN FAIL **/;
ALTER TABLE `#__ticketstation_wallet_passes` ADD COLUMN `message_pending` int(11) NOT NULL DEFAULT 0 /** CAN FAIL **/;
ALTER TABLE `#__ticketstation_wallet_passes` ADD COLUMN `updated` datetime DEFAULT NULL /** CAN FAIL **/;
ALTER TABLE `#__ticketstation_wallet_passes` ADD COLUMN `update_error` varchar(255) NOT NULL DEFAULT '' /** CAN FAIL **/;
ALTER TABLE `#__ticketstation_wallet_passes` ADD KEY `idx_pending` (`update_pending`, `message_pending`) /** CAN FAIL **/;

-- Messages an admin has sent to the wallets of the buyers of an event.
CREATE TABLE IF NOT EXISTS `#__ticketstation_wallet_messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `eventid` int(11) NOT NULL,
  `header` varchar(100) NOT NULL DEFAULT '',
  `body` text NOT NULL,
  `created` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;
