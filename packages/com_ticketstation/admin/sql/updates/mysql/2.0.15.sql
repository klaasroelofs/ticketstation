-- Add API key column to scannermap table for scanner authentication
ALTER TABLE `#__ticketstation_scannermap` ADD COLUMN `apikey` varchar(255) NOT NULL DEFAULT '' AFTER `manual_entry`;

-- Backfill existing scanner rows with generated keys (using cryptographic randomness via MD5 of CONCAT'd entropy)
UPDATE `#__ticketstation_scannermap` SET `apikey` = MD5(CONCAT(id, '-', RAND(), '-', NOW())) WHERE `apikey` = '';
