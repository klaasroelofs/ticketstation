-- Announcing the start of a sale on the website (Upcoming events): per event 1 = with a countdown
-- (what events always did), 2 = date and time only, 0 = not announced; per ticket the same, off by default.
ALTER TABLE `#__ticketstation_events` ADD COLUMN `presale_display` tinyint(1) NOT NULL DEFAULT '1' AFTER `closingdate` /** CAN FAIL **/;
ALTER TABLE `#__ticketstation_tickets` ADD COLUMN `presale_display` tinyint(1) NOT NULL DEFAULT '0' AFTER `publish_date_time` /** CAN FAIL **/;
