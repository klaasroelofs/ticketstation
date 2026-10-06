-- Payment providers are plugins now. Payments record the provider they went through (existing
-- ones are Mollie's), the refunds table loses its Mollie-only column names, and the provider that
-- takes new payments and the payment currency become settings of Ticketstation itself. The old
-- Mollie settings move to the Mollie plugin when the package is installed (pkg_script.php); the
-- table #__ticketstation_mollie stays, unused, until the old columns are removed.

ALTER TABLE `#__ticketstation_transactions_temp` ADD COLUMN `provider` varchar(50) NOT NULL DEFAULT 'mollie' AFTER `transaction_number` /** CAN FAIL **/;
ALTER TABLE `#__ticketstation_transactions_temp` ADD COLUMN `provider_payment_id` varchar(100) NOT NULL DEFAULT '' AFTER `provider` /** CAN FAIL **/;
ALTER TABLE `#__ticketstation_transactions_temp` MODIFY `provider` varchar(50) NOT NULL DEFAULT '';
UPDATE `#__ticketstation_transactions_temp` SET `provider_payment_id` = `message` WHERE `processed` = 1 AND `provider_payment_id` = '' AND `message` LIKE 'tr\_%';

ALTER TABLE `#__ticketstation_transactions` ADD COLUMN `provider` varchar(50) NOT NULL DEFAULT '' AFTER `type` /** CAN FAIL **/;
UPDATE `#__ticketstation_transactions` SET `provider` = 'mollie' WHERE `provider` = '' AND `details` LIKE '%id=tr\_%';
ALTER TABLE `#__ticketstation_transactions` MODIFY `details` text NOT NULL;

ALTER TABLE `#__ticketstation_refunds` ADD COLUMN `provider` varchar(50) NOT NULL DEFAULT '' AFTER `type` /** CAN FAIL **/;
ALTER TABLE `#__ticketstation_refunds` CHANGE `mollie_id` `provider_refund_id` varchar(50) DEFAULT NULL /** CAN FAIL **/;
ALTER TABLE `#__ticketstation_refunds` CHANGE `mollie_payment_id` `provider_payment_id` varchar(50) NOT NULL DEFAULT '' /** CAN FAIL **/;
UPDATE `#__ticketstation_refunds` SET `provider` = 'mollie' WHERE `provider` = '' AND `provider_refund_id` IS NOT NULL;
ALTER TABLE `#__ticketstation_refunds` DROP INDEX `idx_mollie_id` /** CAN FAIL **/;
ALTER TABLE `#__ticketstation_refunds` ADD UNIQUE KEY `idx_provider_refund_id` (`provider_refund_id`) /** CAN FAIL **/;

ALTER TABLE `#__ticketstation_config` ADD COLUMN `payment_provider` varchar(50) NOT NULL DEFAULT '' /** CAN FAIL **/;
ALTER TABLE `#__ticketstation_config` ADD COLUMN `payment_currency` varchar(3) NOT NULL DEFAULT 'EUR' /** CAN FAIL **/;
