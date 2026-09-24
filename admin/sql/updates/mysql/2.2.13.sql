-- Links to the terms and conditions and the privacy statement are now configured (Configuration > Company)
ALTER TABLE `#__ticketstation_config` ADD COLUMN `terms_url` varchar(255) NOT NULL DEFAULT '';
ALTER TABLE `#__ticketstation_config` ADD COLUMN `privacy_url` varchar(255) NOT NULL DEFAULT '';

-- Existing installs keep the links that were hard-coded on the payment page until now
UPDATE `#__ticketstation_config` SET `terms_url` = 'downloads/Ticketshop-AlgemeneVoorwaarden.pdf', `privacy_url` = 'downloads/Ticketshop-Privacyverklaring.pdf' WHERE `configid` = 1;
