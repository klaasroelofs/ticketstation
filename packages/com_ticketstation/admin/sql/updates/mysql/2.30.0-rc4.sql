-- The invoice numbers of the live and the test series come from a counter that only goes up, so
-- the number of an invoice that was removed is never given to another one.

ALTER TABLE `#__ticketstation_config` ADD COLUMN `invoice_counter_live` int(11) NOT NULL DEFAULT 0 /** CAN FAIL **/;
ALTER TABLE `#__ticketstation_config` ADD COLUMN `invoice_counter_test` int(11) NOT NULL DEFAULT 0 /** CAN FAIL **/;

UPDATE `#__ticketstation_config`
SET `invoice_counter_live` = (SELECT COALESCE(MAX(i.`invoice_no`), 0) FROM `#__ticketstation_invoices` AS i WHERE i.`test` = 0),
    `invoice_counter_test` = (SELECT COALESCE(MAX(i.`invoice_no`), 0) FROM `#__ticketstation_invoices` AS i WHERE i.`test` = 1)
WHERE `configid` = 1;
