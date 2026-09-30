-- A seat chart can stop customers on the site from leaving a single free seat between their
-- choice and a taken seat or the end of a row (SeatOrphans). Off for every existing chart.
ALTER TABLE `#__ticketstation_seatplansettings` ADD COLUMN `prevent_orphans` tinyint(1) NOT NULL DEFAULT '0' /** CAN FAIL **/;

-- A seat number is kept as typed, leading zeros included ("01"), so a row named with a number
-- still reads naturally: row 1, seats 01..12 print as 101..112. Existing numbers stay as they are.
ALTER TABLE `#__ticketstation_seatplancoords` MODIFY `seatid` varchar(10) NOT NULL DEFAULT '0';
