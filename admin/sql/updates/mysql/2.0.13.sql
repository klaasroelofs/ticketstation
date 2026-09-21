-- Adds return_token column to temp transactions for secure, unguessable payment authorization.
-- Replaces the weak md5(ordercode) token with a cryptographically random token per payment attempt.
ALTER TABLE `#__ticketstation_transactions_temp` ADD COLUMN `return_token` varchar(64) DEFAULT NULL AFTER `ordercode`;
CREATE UNIQUE INDEX `idx_return_token` ON `#__ticketstation_transactions_temp` (`return_token`);
