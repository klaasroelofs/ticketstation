-- A coupon can be limited to whole events (all their tickets, also tickets added later). Empty
-- means no event limit; together with coupon_tickets the coupon counts for the tickets of these
-- events plus the tickets selected by hand.
ALTER TABLE `#__ticketstation_coupons` ADD COLUMN `coupon_events` varchar(2000) NOT NULL DEFAULT '' /** CAN FAIL **/;

-- The payment link sent to a waiting-list customer gets its own mail template (6). It starts as a
-- copy of the Sending Payment Link template (3), so what customers receive doesn't change until
-- the text is edited.
INSERT IGNORE INTO `#__ticketstation_templates` (`mailid`, `alias`, `mailbody`, `mailsubject`)
SELECT 6, 'Waiting list payment link', `mailbody`, `mailsubject` FROM `#__ticketstation_templates` WHERE `mailid` = 3;

-- Waiting list per event: 0 = follow the Configuration (Waiting list active), 1 = on, 2 = off.
ALTER TABLE `#__ticketstation_events` ADD COLUMN `waitinglist` tinyint(1) NOT NULL DEFAULT '0' /** CAN FAIL **/;

