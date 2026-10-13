-- The company colour of the default ticket layout (empty: the Ticketstation blue).
ALTER TABLE `#__ticketstation_config` ADD COLUMN `company_color` varchar(7) NOT NULL DEFAULT '' /** CAN FAIL **/;

-- Puts the provider column of the temporary transactions in the shape 2.25.0-rc3.sql gives it. On
-- sites where it ended up with another default (or none), the Database check reported it as having
-- the wrong type or attributes.
UPDATE `#__ticketstation_transactions_temp` SET `provider` = '' WHERE `provider` IS NULL;
ALTER TABLE `#__ticketstation_transactions_temp` MODIFY `provider` varchar(50) NOT NULL DEFAULT '';
