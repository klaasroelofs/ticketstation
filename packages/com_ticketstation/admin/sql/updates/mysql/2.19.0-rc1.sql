-- "Doors open" on a ticket: the time (HH:MM) the doors open, on the date of the ticket. Empty when
-- not used. Printed through its own field on the Ticket layout tab.
ALTER TABLE `#__ticketstation_tickets` ADD COLUMN `doors_open` varchar(5) NOT NULL DEFAULT '' AFTER `enddate` /** CAN FAIL **/;
ALTER TABLE `#__ticketstation_tickets` ADD COLUMN `doors_open_fontcolor` varchar(6) NOT NULL DEFAULT '' AFTER `ticketdate_position` /** CAN FAIL **/;
ALTER TABLE `#__ticketstation_tickets` ADD COLUMN `doors_open_fontsize` varchar(3) NOT NULL DEFAULT '' AFTER `doors_open_fontcolor` /** CAN FAIL **/;
ALTER TABLE `#__ticketstation_tickets` ADD COLUMN `doors_open_position` varchar(10) NOT NULL DEFAULT '' AFTER `doors_open_fontsize` /** CAN FAIL **/;
