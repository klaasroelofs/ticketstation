-- Customers are kept apart like orders are: a customer made in test mode is a test customer, so
-- the same e-mail address ordering live is a new customer. Existing customers are live, except
-- those who only have test orders.

ALTER TABLE `#__ticketstation_clients` ADD COLUMN `test` tinyint(1) NOT NULL DEFAULT 0 /** CAN FAIL **/;

UPDATE `#__ticketstation_clients` AS c
SET c.`test` = 1
WHERE EXISTS (SELECT 1 FROM `#__ticketstation_orders` AS o WHERE o.`userid` = c.`clientid` AND o.`test` = 1)
AND NOT EXISTS (SELECT 1 FROM `#__ticketstation_orders` AS o2 WHERE o2.`userid` = c.`clientid` AND o2.`test` = 0);
