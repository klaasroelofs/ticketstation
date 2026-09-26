-- A ticket has one seat plan settings row, but older data could hold more, which showed every
-- seat of its chart twice. Keep each ticket's first row (the one the settings screen and the
-- seat queries use) and make ticketid unique so a second row can't come back.
DELETE `s`
FROM `#__ticketstation_seatplansettings` AS `s`
INNER JOIN `#__ticketstation_seatplansettings` AS `k` ON `k`.`ticketid` = `s`.`ticketid` AND `k`.`id` < `s`.`id`;

-- Marked CAN FAIL: the key already exists when this file runs a second time (see 2.5.1.sql).
ALTER TABLE `#__ticketstation_seatplansettings` ADD UNIQUE KEY `idx_ticketid` (`ticketid`) /** CAN FAIL **/;
