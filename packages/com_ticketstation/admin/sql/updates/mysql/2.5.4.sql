-- The currency of Mollie payments is now a Mollie setting (it was always EUR). It replaces
-- the unused `paypal_valuta` column in the Configuration, left over from the PayPal integration.
-- No longer run (the table #__ticketstation_mollie is removed since 2.26.0): ALTER TABLE ... ADD COLUMN `currency` varchar(3) NOT NULL DEFAULT 'EUR' AFTER `payment_methods`
ALTER TABLE `#__ticketstation_config` DROP COLUMN `paypal_valuta` /** CAN FAIL **/;
