-- The Mollie payment methods offered at checkout, comma-separated Mollie method ids.
-- Existing sites keep iDEAL only, which is what they offered until now.
ALTER TABLE `#__ticketstation_mollie` ADD COLUMN `payment_methods` varchar(255) NOT NULL DEFAULT 'ideal' /** CAN FAIL **/;
