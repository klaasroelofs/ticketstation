-- One switch for online payments through Mollie. Off: no Mollie warnings in the backend, and the
-- website only sells free tickets; paid tickets are sold through Reservations and the Box Office.
-- Existing sites keep paying online.
ALTER TABLE `#__ticketstation_mollie` ADD COLUMN `enabled` tinyint(1) NOT NULL DEFAULT 1 /** CAN FAIL **/;
