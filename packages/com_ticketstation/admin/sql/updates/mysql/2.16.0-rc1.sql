-- Apple Wallet and Google Wallet: customers can add their tickets to their phone's wallet.
-- Each wallet has its own switch and credentials; the look of the passes (logo, colours) is shared.
ALTER TABLE `#__ticketstation_config` ADD COLUMN `wallet_apple` tinyint(1) NOT NULL DEFAULT 0 /** CAN FAIL **/;
ALTER TABLE `#__ticketstation_config` ADD COLUMN `wallet_apple_cert` text DEFAULT NULL /** CAN FAIL **/;
ALTER TABLE `#__ticketstation_config` ADD COLUMN `wallet_apple_key` text DEFAULT NULL /** CAN FAIL **/;
ALTER TABLE `#__ticketstation_config` ADD COLUMN `wallet_apple_pending_key` text DEFAULT NULL /** CAN FAIL **/;
ALTER TABLE `#__ticketstation_config` ADD COLUMN `wallet_google` tinyint(1) NOT NULL DEFAULT 0 /** CAN FAIL **/;
ALTER TABLE `#__ticketstation_config` ADD COLUMN `wallet_google_issuer_id` varchar(30) NOT NULL DEFAULT '' /** CAN FAIL **/;
ALTER TABLE `#__ticketstation_config` ADD COLUMN `wallet_google_key` text DEFAULT NULL /** CAN FAIL **/;
ALTER TABLE `#__ticketstation_config` ADD COLUMN `wallet_logo` varchar(255) NOT NULL DEFAULT '' /** CAN FAIL **/;
ALTER TABLE `#__ticketstation_config` ADD COLUMN `wallet_bg_color` varchar(7) NOT NULL DEFAULT '#1f2937' /** CAN FAIL **/;
ALTER TABLE `#__ticketstation_config` ADD COLUMN `wallet_fg_color` varchar(7) NOT NULL DEFAULT '#ffffff' /** CAN FAIL **/;
