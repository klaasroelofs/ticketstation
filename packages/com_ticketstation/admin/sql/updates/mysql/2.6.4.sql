-- When a payment link was last sent from the Box Office. Until now "Resend Payment" moved the
-- order date to that moment, so the automatic cleanup would count its period from it; that also
-- changed the date shown in the Box Office, on the tickets and in the mails. The cleanup now
-- counts from this column when it is set, and the order date stays what it was.
ALTER TABLE `#__ticketstation_orders` ADD COLUMN `payment_requested` datetime DEFAULT NULL AFTER `orderdate`;
