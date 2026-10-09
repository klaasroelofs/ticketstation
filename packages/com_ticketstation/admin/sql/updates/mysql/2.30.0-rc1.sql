-- Test mode and live mode are kept apart: every order, payment, invoice and waiting-list entry
-- remembers in which mode it was made (1 = made in test mode). Existing rows are live, except what
-- is recognisably a test order: its tickets carry the fixed test barcode.

ALTER TABLE `#__ticketstation_orders` ADD COLUMN `test` tinyint(1) NOT NULL DEFAULT 0 /** CAN FAIL **/;
ALTER TABLE `#__ticketstation_transactions` ADD COLUMN `test` tinyint(1) NOT NULL DEFAULT 0 /** CAN FAIL **/;
ALTER TABLE `#__ticketstation_transactions_temp` ADD COLUMN `test` tinyint(1) NOT NULL DEFAULT 0 /** CAN FAIL **/;
ALTER TABLE `#__ticketstation_invoices` ADD COLUMN `test` tinyint(1) NOT NULL DEFAULT 0 /** CAN FAIL **/;
ALTER TABLE `#__ticketstation_waitinglist` ADD COLUMN `test` tinyint(1) NOT NULL DEFAULT 0 /** CAN FAIL **/;

UPDATE `#__ticketstation_orders` SET `test` = 1 WHERE `barcode` = '123456789012';

UPDATE `#__ticketstation_transactions` AS t
INNER JOIN `#__ticketstation_orders` AS o ON o.`orderid` = t.`orderid`
SET t.`test` = 1
WHERE o.`test` = 1;

UPDATE `#__ticketstation_transactions_temp` AS t
INNER JOIN `#__ticketstation_orders` AS o ON o.`ordercode` = t.`ordercode`
SET t.`test` = 1
WHERE o.`test` = 1;

UPDATE `#__ticketstation_invoices` AS i
INNER JOIN `#__ticketstation_orders` AS o ON o.`ordercode` = i.`ordercode`
SET i.`test` = 1
WHERE o.`test` = 1;

UPDATE `#__ticketstation_waitinglist` AS w
INNER JOIN `#__ticketstation_orders` AS o ON o.`ordercode` = w.`ordercode`
SET w.`test` = 1
WHERE o.`test` = 1;
