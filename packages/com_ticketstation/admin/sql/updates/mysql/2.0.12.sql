-- (This file used to add the config column invoice_logo. Invoices use company_logo, so the column
-- was never read and 2.33.0-rc1 drops it; the statement is gone so the Database check doesn't ask for it.)

-- Transaction fees were computed (Amount::getAmount()) but never stored on the invoice, so
-- they never made it onto the PDF even though the customer actually paid them.
ALTER TABLE `#__ticketstation_invoices` ADD COLUMN `fees` decimal(10,2) NOT NULL DEFAULT '0.00' AFTER `discount`;
