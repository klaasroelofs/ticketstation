-- Refunds through Mollie. An order row stays when its ticket is refunded; refund_state says what
-- happened to the ticket (0 = nothing, 1 = refunded but still valid, 2 = invalid with the place
-- kept taken, 3 = invalid and released for sale again) and refund_id which refund did it.
-- Released rows no longer count against the capacity.
ALTER TABLE `#__ticketstation_orders` ADD COLUMN `refund_state` tinyint(1) NOT NULL DEFAULT '0' /** CAN FAIL **/;
ALTER TABLE `#__ticketstation_orders` ADD COLUMN `refund_id` int(11) NOT NULL DEFAULT '0' /** CAN FAIL **/;

-- Every refund and chargeback of an order: made in the Box Office, reported by Mollie's webhook
-- (made in the Mollie dashboard), or registered by hand for an order paid outside Mollie.
-- attention: 1 = a refund from Mollie waits for a decision about the tickets, 2 = a refund failed
-- or a chargeback was reversed after the tickets were dealt with.
CREATE TABLE IF NOT EXISTS `#__ticketstation_refunds` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ordercode` int(11) NOT NULL,
  `type` varchar(20) NOT NULL DEFAULT 'refund',
  `mollie_id` varchar(50) DEFAULT NULL,
  `mollie_payment_id` varchar(50) NOT NULL DEFAULT '',
  `amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `currency` varchar(3) NOT NULL DEFAULT 'EUR',
  `description` varchar(255) NOT NULL DEFAULT '',
  `status` varchar(20) NOT NULL DEFAULT '',
  `source` varchar(20) NOT NULL DEFAULT 'ticketstation',
  `attention` tinyint(1) NOT NULL DEFAULT '0',
  `created` datetime NOT NULL,
  `created_by` int(11) NOT NULL DEFAULT '0',
  `decided` datetime DEFAULT NULL,
  `decided_by` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_mollie_id` (`mollie_id`),
  KEY `ordercode` (`ordercode`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;
