-- Invoices made in test mode get a series of their own (TEST-00001), so the live invoice numbers
-- have no gaps. Every invoice gets its own number within its series; the numbers that exist stay
-- as they are (the invoiceid).

ALTER TABLE `#__ticketstation_invoices` ADD COLUMN `invoice_no` int(11) NOT NULL DEFAULT 0 /** CAN FAIL **/;

UPDATE `#__ticketstation_invoices` SET `invoice_no` = `invoiceid` WHERE `invoice_no` = 0;

ALTER TABLE `#__ticketstation_invoices` ADD UNIQUE KEY `idx_invoice_no` (`test`, `invoice_no`) /** CAN FAIL **/;

-- Seats are taken by one mode at a time: when the shop is live, the seats of test orders are free
-- (they are released when test mode is switched off; this does it for the test orders that exist now).
UPDATE `#__ticketstation_seatplancoords` AS c
INNER JOIN `#__ticketstation_orders` AS o ON o.`orderid` = c.`orderid`
INNER JOIN `#__ticketstation_config` AS cfg ON cfg.`configid` = 1
SET c.`booked` = c.`blocked`, c.`orderid` = 0
WHERE o.`test` = 1 AND cfg.`test_mode` = 0;
