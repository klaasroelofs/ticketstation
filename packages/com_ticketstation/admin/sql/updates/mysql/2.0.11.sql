-- Invoicing never had a database backing: #__ticketstation_invoices and
-- #__ticketstation_invoice_items were referenced throughout the code (Invoice.php,
-- CreateInvoice.php, SendonPayment.php) but never actually created by any install or
-- update script. Every query against them would fatally fail. This creates them.
CREATE TABLE IF NOT EXISTS `#__ticketstation_invoices` (
  `invoiceid` int(11) NOT NULL AUTO_INCREMENT,
  `ordercode` varchar(50) NOT NULL,
  `userid` int(11) NOT NULL,
  `invoicedate` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `netto` decimal(10,2) NOT NULL DEFAULT '0.00',
  `bruto` decimal(10,2) NOT NULL DEFAULT '0.00',
  `vat` decimal(10,2) NOT NULL DEFAULT '0.00',
  `discount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `coupon_code` varchar(50) DEFAULT NULL,
  `payment_provider` varchar(50) DEFAULT NULL,
  `sent` tinyint(1) NOT NULL DEFAULT '0',
  `date_sent` datetime DEFAULT NULL,
  PRIMARY KEY (`invoiceid`),
  KEY `ordercode` (`ordercode`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__ticketstation_invoice_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `invoiceid` int(11) NOT NULL,
  `ordercode` varchar(50) NOT NULL,
  `ticketid` int(11) NOT NULL,
  `eventid` int(11) NOT NULL,
  `ticketname` varchar(255) NOT NULL DEFAULT '',
  `quantity` int(11) NOT NULL DEFAULT '1',
  `ticketprice` decimal(10,2) NOT NULL DEFAULT '0.00',
  `vat_percentage` decimal(5,2) NOT NULL DEFAULT '0.00',
  `discount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `netto_ticketprice` decimal(10,2) NOT NULL DEFAULT '0.00',
  `couponcode` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `invoiceid` (`invoiceid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

-- The invoice email has never existed (mailid 5, in line with 1-4).
INSERT IGNORE INTO `#__ticketstation_templates` VALUES("5","Invoice","<p>Beste {firstname},</p><p>Bijgaand vind je de factuur voor je bestelling <strong>{ordercode}</strong>.</p><p>Factuurnummer: {invoice_id}<br />Bedrag: {price}</p><p>Mocht je nog vragen hebben, neem dan contact met ons op via <a href=\'mailto:tickets@huibuuke.nl\'>tickets@huibuuke.nl</a>.</p><p>Met vriendelijke groet,</p><p>{company_name}<br />{company_website}</p>","Factuur voor je bestelling","tickets@huibuuke.nl","Stichting De Huibuuke");
