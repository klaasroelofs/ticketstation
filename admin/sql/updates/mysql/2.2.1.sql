-- Every ticket scan looks the ticket up by its QR code content
ALTER TABLE `#__ticketstation_orders` ADD INDEX `idx_barcode` (`barcode`);
