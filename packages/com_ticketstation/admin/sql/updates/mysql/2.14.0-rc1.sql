-- Bypass mode is removed: Reservations cover booking tickets without an online payment, and
-- a free order still completes without Mollie.
ALTER TABLE `#__ticketstation_mollie` DROP COLUMN `bypass_mode` /** CAN FAIL **/;
