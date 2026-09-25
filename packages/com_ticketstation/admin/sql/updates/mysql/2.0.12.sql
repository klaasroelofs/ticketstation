-- Adds the logo path used on invoices (config screen, new "Company Logo" field).
ALTER TABLE `#__ticketstation_config` ADD COLUMN `invoice_logo` varchar(255) NOT NULL DEFAULT '' AFTER `invoice_eid`;

-- Transaction fees were computed (Amount::getAmount()) but never stored on the invoice, so
-- they never made it onto the PDF even though the customer actually paid them.
ALTER TABLE `#__ticketstation_invoices` ADD COLUMN `fees` decimal(10,2) NOT NULL DEFAULT '0.00' AFTER `discount`;
