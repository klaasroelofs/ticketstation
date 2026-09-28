-- The font a ticket's text is printed in, chosen on the Ticket Layout tab. Existing tickets keep
-- Raleway, the only font until now.
ALTER TABLE `#__ticketstation_tickets` ADD COLUMN `ticket_font` varchar(20) NOT NULL DEFAULT 'raleway' AFTER `ticket_orientation`;
