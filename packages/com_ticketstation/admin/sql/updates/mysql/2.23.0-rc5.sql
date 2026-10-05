-- A pass whose ticket was removed (a deleted order or ticket): removed = 1 makes the wallet show it as
-- expired (Google) or void and expired (Apple). For Apple the pass is kept, without anything about
-- the customer, in snapshot: the iPhones that still have it fetch it from the web service.
ALTER TABLE `#__ticketstation_wallet_passes` ADD COLUMN `removed` tinyint(1) NOT NULL DEFAULT 0 /** CAN FAIL **/;
ALTER TABLE `#__ticketstation_wallet_passes` ADD COLUMN `snapshot` mediumtext DEFAULT NULL /** CAN FAIL **/;
