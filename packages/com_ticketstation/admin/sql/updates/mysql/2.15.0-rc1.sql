-- Service fee "fixed and variable": a percentage of the order plus a fixed amount. fee_rate
-- keeps the percentage, fee_fixed the amount on top of it.
ALTER TABLE `#__ticketstation_ordertotals` ADD COLUMN `fee_fixed` decimal(10,4) DEFAULT NULL AFTER `fee_rate` /** CAN FAIL **/;
