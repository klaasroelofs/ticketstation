-- "Venue" field on the Ticket layout tab: prints the venue name and city of the ticket.
ALTER TABLE `#__ticketstation_tickets` ADD COLUMN `venue_fontcolor` varchar(6) NOT NULL DEFAULT '' AFTER `ticketdate_position` /** CAN FAIL **/;
ALTER TABLE `#__ticketstation_tickets` ADD COLUMN `venue_fontsize` varchar(3) NOT NULL DEFAULT '' AFTER `venue_fontcolor` /** CAN FAIL **/;
ALTER TABLE `#__ticketstation_tickets` ADD COLUMN `venue_position` varchar(10) NOT NULL DEFAULT '' AFTER `venue_fontsize` /** CAN FAIL **/;
