-- Where the position of a text on the ticket is: at the start of the text (L, as it always was) or at
-- the end of it (R, for Arabic and Persian), chosen on the Ticket Layout tab. Existing tickets keep L.
ALTER TABLE `#__ticketstation_tickets` ADD COLUMN `ticket_textalign` varchar(1) NOT NULL DEFAULT 'L' AFTER `ticket_font` /** CAN FAIL **/;
