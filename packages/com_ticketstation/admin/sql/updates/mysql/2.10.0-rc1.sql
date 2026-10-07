-- The coupon of an order and its terms move from every order row to #__ticketstation_ordertotals.
-- A cart that gets a coupon before checkout has a row there without service fee terms yet:
-- fee_type NULL means the current Configuration applies, as it does without a row.
ALTER TABLE `#__ticketstation_ordertotals` MODIFY `fee_type` tinyint(1) DEFAULT NULL;
ALTER TABLE `#__ticketstation_ordertotals` MODIFY `fee_rate` decimal(10,4) DEFAULT NULL;
ALTER TABLE `#__ticketstation_ordertotals` MODIFY `captured` datetime DEFAULT NULL;
ALTER TABLE `#__ticketstation_ordertotals` ADD COLUMN `coupon` varchar(25) DEFAULT NULL /** CAN FAIL **/;
ALTER TABLE `#__ticketstation_ordertotals` ADD COLUMN `discount_type` tinyint(1) DEFAULT NULL /** CAN FAIL **/;
ALTER TABLE `#__ticketstation_ordertotals` ADD COLUMN `discount_amount` float DEFAULT NULL /** CAN FAIL **/;
ALTER TABLE `#__ticketstation_ordertotals` ADD KEY `coupon` (`coupon`) /** CAN FAIL **/;

-- Existing orders without a row get one with fee_type NULL, so their service fee keeps following
-- the Configuration, as before.
INSERT INTO `#__ticketstation_ordertotals` (`ordercode`, `coupon`, `discount_type`, `discount_amount`)
  SELECT `ordercode`, MAX(`coupon`), MAX(`discount_type`), MAX(`discount_amount`)
  FROM `#__ticketstation_orders`
  WHERE `coupon` IS NOT NULL AND `coupon` != ''
  GROUP BY `ordercode`
ON DUPLICATE KEY UPDATE `coupon` = VALUES(`coupon`), `discount_type` = VALUES(`discount_type`),
  `discount_amount` = VALUES(`discount_amount`) /** CAN FAIL **/;

ALTER TABLE `#__ticketstation_orders` DROP COLUMN `coupon` /** CAN FAIL **/;
ALTER TABLE `#__ticketstation_orders` DROP COLUMN `discount_type` /** CAN FAIL **/;
ALTER TABLE `#__ticketstation_orders` DROP COLUMN `discount_amount` /** CAN FAIL **/;

-- The service fee per order row: still written until 2.9.1, read nowhere since the ordertotals
-- table (2.5.2).
ALTER TABLE `#__ticketstation_orders` DROP COLUMN `fees` /** CAN FAIL **/;

-- Columns nothing reads or writes any more (see the 2.5.0 and 2.5.2 release notes).
ALTER TABLE `#__ticketstation_tickets` DROP COLUMN `totaltickets` /** CAN FAIL **/;
ALTER TABLE `#__ticketstation_seatplansettings` DROP COLUMN `multi_seat` /** CAN FAIL **/;
ALTER TABLE `#__ticketstation_config` DROP COLUMN `send_pdf_tickets` /** CAN FAIL **/;
ALTER TABLE `#__ticketstation_config` DROP COLUMN `send_multi_ticket_only` /** CAN FAIL **/;
ALTER TABLE `#__ticketstation_config` DROP COLUMN `send_multi_ticket_admin` /** CAN FAIL **/;
ALTER TABLE `#__ticketstation_config` DROP COLUMN `admin_receivers_multi_ticket` /** CAN FAIL **/;
ALTER TABLE `#__ticketstation_config` DROP COLUMN `use_euros_in_pdf` /** CAN FAIL **/;
-- No longer run (the table #__ticketstation_mollie is removed since 2.26.0): ALTER TABLE ... DROP COLUMN `trans_cost`
