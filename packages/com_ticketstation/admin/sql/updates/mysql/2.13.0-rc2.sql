-- "Send tickets directly" moves from the Mollie settings to the Configuration: it also applies to
-- free orders and works with online payments off, so it is no Mollie setting. The value is kept.
ALTER TABLE `#__ticketstation_config` ADD COLUMN `send_tickets_directly` tinyint(1) NOT NULL DEFAULT 1 /** CAN FAIL **/;
UPDATE `#__ticketstation_config` AS c, `#__ticketstation_mollie` AS m SET c.`send_tickets_directly` = COALESCE(m.`send_tickets_directly`, 1) WHERE c.`configid` = 1 AND m.`configid` = 1 /** CAN FAIL **/;
-- No longer run (the table #__ticketstation_mollie is removed since 2.26.0): ALTER TABLE ... DROP COLUMN `send_tickets_directly`
