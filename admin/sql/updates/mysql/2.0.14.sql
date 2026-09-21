-- Adds validation_token columns to orders and waitinglist tables for secure token-based guest link access.
-- Replaces weak enumerable ordercode/id lookups with cryptographically random single-use tokens.
ALTER TABLE `#__ticketstation_orders` ADD COLUMN `validation_token` varchar(64) DEFAULT NULL AFTER `vat_percentage`;
CREATE UNIQUE INDEX `idx_validation_token_orders` ON `#__ticketstation_orders` (`validation_token`);

ALTER TABLE `#__ticketstation_waitinglist` ADD COLUMN `validation_token` varchar(64) DEFAULT NULL AFTER `date_sent`;
CREATE UNIQUE INDEX `idx_validation_token_waitinglist` ON `#__ticketstation_waitinglist` (`validation_token`);

-- Backfill existing orders and waitinglist rows with random tokens (64-char hex = 32 bytes of random data)
UPDATE `#__ticketstation_orders` SET `validation_token` = CONCAT(
  HEX(UNHEX(MD5(RAND()))), HEX(UNHEX(MD5(RAND())))
) WHERE `validation_token` IS NULL;

UPDATE `#__ticketstation_waitinglist` SET `validation_token` = CONCAT(
  HEX(UNHEX(MD5(RAND()))), HEX(UNHEX(MD5(RAND())))
) WHERE `validation_token` IS NULL;

-- Make validation_token NOT NULL after backfill
ALTER TABLE `#__ticketstation_orders` MODIFY `validation_token` varchar(64) NOT NULL;
ALTER TABLE `#__ticketstation_waitinglist` MODIFY `validation_token` varchar(64) NOT NULL;
