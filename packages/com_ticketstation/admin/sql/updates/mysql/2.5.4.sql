-- The currency of Mollie payments is now a Mollie setting (it was always EUR). It replaces
-- the unused `paypal_valuta` column in the Configuration, left over from the PayPal integration.
ALTER TABLE `#__ticketstation_mollie` ADD COLUMN `currency` varchar(3) NOT NULL DEFAULT 'EUR' AFTER `payment_methods` /** CAN FAIL **/;
ALTER TABLE `#__ticketstation_config` DROP COLUMN `paypal_valuta` /** CAN FAIL **/;
