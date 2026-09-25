DROP TABLE IF EXISTS `#__ticketstation_scannermap`;
CREATE TABLE IF NOT EXISTS `#__ticketstation_scannermap` (
  `id` int(10) NOT NULL AUTO_INCREMENT,
  `userid` int(11) DEFAULT '0',
  `totals_visible` tinyint(1) DEFAULT '0',
  `manual_entry` tinyint(1) DEFAULT '0',
  `apikey` varchar(255) NOT NULL DEFAULT '',
  `events` varchar(5120) NOT NULL DEFAULT '[]' COMMENT 'JSON encoded',
  `tickets` varchar(5120) NOT NULL DEFAULT '[]' COMMENT 'JSON encoded',
  PRIMARY KEY (`id`)
)  ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `#__ticketstation_mollie`;
CREATE TABLE IF NOT EXISTS `#__ticketstation_mollie` (
  `configid` int(1) NOT NULL AUTO_INCREMENT,
  `test_mode` tinyint(1) DEFAULT '0',
  `bypass_mode` tinyint(1) DEFAULT '0',
  `trans_cost` double NOT NULL,
  `api_key_test` varchar(255) NOT NULL DEFAULT '',
  `api_key` varchar(255) NOT NULL DEFAULT '',
  `description` varchar(255) DEFAULT '',
  `show_methods` tinyint(1) DEFAULT '0',
  `send_confirmation` tinyint(1) DEFAULT '0',
  `mollie_language` varchar(255) DEFAULT 'en',
  `change_payment_state` tinyint(1) DEFAULT '1',
  `send_tickets_directly` tinyint(1) DEFAULT '1',
  `send_mail_after_return` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`configid`)
)  ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `#__ticketstation_clients`;
CREATE TABLE IF NOT EXISTS `#__ticketstation_clients` (
  `clientid` int(10) NOT NULL AUTO_INCREMENT,
  `firstname` varchar(30),
  `name` varchar(255),
  `address` varchar(255) DEFAULT NULL,
  `address2` varchar(255) DEFAULT NULL,
  `address3` varchar(255) DEFAULT NULL,
  `zipcode` varchar(10) DEFAULT NULL,
  `city` varchar(255) DEFAULT NULL,
  `phonenumber` varchar(255) NOT NULL,
  `emailaddress` varchar(255) NOT NULL,
  `published` tinyint(4) NOT NULL DEFAULT '0',
  `gender` varchar(25) DEFAULT NULL,
  `birthday` date DEFAULT NULL,
  `country_id` int(11) NOT NULL DEFAULT '1',
  `ipaddress` varchar(60) NOT NULL,
  PRIMARY KEY (`clientid`)
)  ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `#__ticketstation_config`;
CREATE TABLE IF NOT EXISTS `#__ticketstation_config` (
  `configid` int(1) NOT NULL AUTO_INCREMENT,
  `show_secondaddress` tinyint(1) NOT NULL,
  `show_thirdaddress` tinyint(1) NOT NULL,
  `valuta` varchar(8) NOT NULL,
  `currencytype` int(5) NOT NULL,
  `payments_on` tinyint(1) NOT NULL,
  `paypal_valuta` varchar(4) NOT NULL,
  `transactioncosts` float NOT NULL,
  `priceformat` tinyint(2) NOT NULL,
  `persending` int(11) NOT NULL,
  `dateformat` varchar(8) NOT NULL,
  `transcosts` double NOT NULL,
  `variable_transcosts` double NOT NULL,
  `man_payment` tinyint(1) NOT NULL,
  `show_cancel` tinyint(1) NOT NULL,
  `show_available_tickets` tinyint(1) NOT NULL,
  `show_quantity_eventlist` tinyint(1) NOT NULL,
  `show_price_eventlist` tinyint(1) NOT NULL,
  `payment_email_send` tinyint(1) NOT NULL,
  `companyname` varchar(255) NOT NULL,
  `address1` varchar(255) NOT NULL,
  `address2` varchar(255) NOT NULL,
  `zipcode` varchar(10) NOT NULL,
  `city` varchar(255) NOT NULL,
  `state` varchar(255) NOT NULL,
  `phone` varchar(15) NOT NULL,
  `fax` varchar(15) NOT NULL,
  `email` varchar(255) NOT NULL,
  `website` varchar(255) NOT NULL,
  `company_logo` varchar(100) NOT NULL,
  `company_logo_position` varchar(15) NOT NULL,
  `qr_width` int(5) NOT NULL,
  `barcode_type` tinyint(1) NOT NULL,
  `barcode_2d_x` varchar(5) NOT NULL,
  `barcode_2d_y` varchar(5) NOT NULL,
  `barcode_1d_x` varchar(5) NOT NULL,
  `barcode_1d_y` varchar(5) NOT NULL,
  `redirect_after_login` tinyint(1) NOT NULL,
  `redirect_after_registration` tinyint(1) NOT NULL,
  `use_euros_in_pdf` tinyint(1) NOT NULL,
  `removal_days` tinyint(2) NOT NULL,
  `position_logo_confirmation` varchar(15) NOT NULL,
  `send_profile_mail` tinyint(1) NOT NULL,
  `tos_tpl` int(11) NOT NULL,
  `send_confirmation_pdf` tinyint(1) NOT NULL,
  `use_automatic_login` tinyint(1) NOT NULL,
  `remove_unfinished` tinyint(1) NOT NULL,
  `removal_hours` double NOT NULL,
  `pro_installed` tinyint(1) NOT NULL,
  `show_mailchimp_signup` tinyint(1) NOT NULL,
  `show_country` tinyint(1) NOT NULL,
  `show_salutation` tinyint(1) NOT NULL,
  `show_address` tinyint(1) NOT NULL,
  `show_city` tinyint(1) NOT NULL,
  `show_birthday` tinyint(1) NOT NULL,
  `auto_username` tinyint(1) NOT NULL,
  `activation_email` int(4) NOT NULL,
  `mailchimp_listid` varchar(50) NOT NULL,
  `mailchimp_api` varchar(60) NOT NULL,
  `show_mailchmp` tinyint(1) NOT NULL,
  `load_bootstrap` tinyint(1) NOT NULL,
  `load_bootstrap_tpl` tinyint(1) NOT NULL,
  `scan_api` int(6) NOT NULL,
  `use_coupons` tinyint(1) NOT NULL,
  `load_jquery` tinyint(1) NOT NULL,
  `send_pdf_tickets` tinyint(1) NOT NULL,
  `send_multi_ticket_only` tinyint(4) NOT NULL,
  `send_multi_ticket_admin` tinyint(1) NOT NULL,
  `show_remark_field` tinyint(4) NOT NULL,
  `show_waitinglist` tinyint(1) NOT NULL,
  `eventname_position` varchar(35) NOT NULL,
  `date_position` varchar(35) NOT NULL,
  `location_position` varchar(35) NOT NULL,
  `orderid_position` varchar(35) NOT NULL,
  `ordernumber_position` varchar(35) NOT NULL,
  `price_position` varchar(35) NOT NULL,
  `bar_position` varchar(35) NOT NULL,
  `name_position` varchar(35) NOT NULL,
  `free_text2_position` varchar(35) NOT NULL,
  `free_text1_position` varchar(35) NOT NULL,
  `position_seatnumber` varchar(35) NOT NULL,
  `orderdate_position` varchar(35) NOT NULL,
  `show_mailchimps` tinyint(1) NOT NULL,
  `address_format_client` text NOT NULL,
  `address_format_company` text NOT NULL,
  `show_zipcode` tinyint(1) NOT NULL,
  `show_phone` tinyint(1) NOT NULL,
  `downloadid` varchar(50) NOT NULL,
  `create_invoice` tinyint(1) NOT NULL,
  `send_invoice` tinyint(1) NOT NULL,
  `invoice_prefix` varchar(10) NOT NULL,
  `invoice_eid` varchar(11) NOT NULL,
  `hide_invoice_msg_cpanel` tinyint(1) NOT NULL,
  `time_format` varchar(10) NOT NULL DEFAULT 'H:i',
  `admin_receivers_multi_ticket` varchar(255) NOT NULL,
  `show_downoad_button_in_myorders` tinyint(1) NOT NULL,
  `next_ordercode` varchar(20) NOT NULL DEFAULT '',
  `invoice_logo` varchar(255) NOT NULL DEFAULT '',
  `show_venue` tinyint(1) NOT NULL DEFAULT 1,
  `show_venue_address` tinyint(1) NOT NULL DEFAULT 0,
  `show_venue_description` tinyint(1) NOT NULL DEFAULT 0,
  `show_venue_website` tinyint(1) NOT NULL DEFAULT 0,
  `from_name` varchar(255) NOT NULL DEFAULT '',
  `from_email` varchar(255) NOT NULL DEFAULT '',
  `terms_url` varchar(255) NOT NULL DEFAULT '',
  `privacy_url` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`configid`)
)  ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `#__ticketstation_transactions`;
CREATE TABLE IF NOT EXISTS `#__ticketstation_transactions` (
  `pid` int(10) NOT NULL AUTO_INCREMENT,
  `transid` int(10) NOT NULL,
  `userid` int(10) NOT NULL,
  `details` varchar(2500) NOT NULL,
  `amount` double NOT NULL,
  `date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `type` varchar(50) NOT NULL,
  `orderid` int(10) NOT NULL DEFAULT '0',
  PRIMARY KEY (`pid`)
)  ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `#__ticketstation_transactions_temp`;
CREATE TABLE IF NOT EXISTS `#__ticketstation_transactions_temp` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `userid` int(11) NOT NULL,
  `transaction_number` varchar(50) NOT NULL,
  `ordercode` int(10) NOT NULL,
  `return_token` varchar(64) DEFAULT NULL,
  `create_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `processed` tinyint(1) NOT NULL,
  `status` tinyint(3) DEFAULT NULL,
  `errorcode` tinyint(1) DEFAULT NULL,
  `message` varchar(255) DEFAULT NULL,
  `amounts` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_return_token` (`return_token`)
)  DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `#__ticketstation_coupons`;
CREATE TABLE IF NOT EXISTS `#__ticketstation_coupons` (
  `coupon_id` int(11) NOT NULL AUTO_INCREMENT,
  `coupon_name` varchar(100) NOT NULL DEFAULT '',
  `coupon_code` varchar(25) NOT NULL DEFAULT '',
  `coupon_limit` int(11) NOT NULL DEFAULT '0',
  `coupon_valid_to` date DEFAULT NULL,
  `coupon_added` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `coupon_type` tinyint(1) NOT NULL DEFAULT '1',
  `coupon_discount` int(11) NOT NULL DEFAULT '0',
  `coupon_used` int(11) NOT NULL DEFAULT '0',
  `published` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`coupon_id`)
)  ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `#__ticketstation_venues`;
CREATE TABLE IF NOT EXISTS `#__ticketstation_venues` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `venue` varchar(50) NOT NULL DEFAULT '',
  `street` varchar(50) DEFAULT NULL,
  `zipcode` varchar(20) DEFAULT NULL,
  `city` varchar(50) DEFAULT NULL,
  `website` varchar(255) NOT NULL,
  `contact_person` varchar(200) NOT NULL,
  `phonenumber` varchar(50) NOT NULL,
  `emailaddress` varchar(200) NOT NULL,
  `venuedescription` mediumtext NOT NULL,
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `published` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`)
)  ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `#__ticketstation_events`;
CREATE TABLE IF NOT EXISTS `#__ticketstation_events` (
  `eventid` int(10) NOT NULL AUTO_INCREMENT,
  `eventname` varchar(255) DEFAULT NULL,
  `eventdate` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `eventcode` varchar(10) DEFAULT NULL,
  `eventdescription` text DEFAULT NULL,
  `published` tinyint(1) NOT NULL DEFAULT '0',
  `automatic_change_state` tinyint(1) NOT NULL DEFAULT '0',
  `startdate` datetime DEFAULT NULL,
  `closingdate` datetime DEFAULT NULL,
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`eventid`)
)  ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `#__ticketstation_orders`;
CREATE TABLE IF NOT EXISTS `#__ticketstation_orders` (
  `orderid` int(10) AUTO_INCREMENT,
  `userid` int(10) NOT NULL DEFAULT '0',
  `ordercode` varchar(50) NOT NULL,
  `eventid` int(10) DEFAULT NULL,
  `ticketid` int(10) DEFAULT NULL,
  `paid` tinyint(1) DEFAULT '0',
  `orderdate` datetime DEFAULT NULL,
  `pdfcreated` tinyint(1) DEFAULT NULL,
  `pdfsent` tinyint(1) DEFAULT NULL,
  `downloadbuttonshown` tinyint(1) DEFAULT '0',
  `downloaded` tinyint(1) DEFAULT '0',
  `published` tinyint(1) NOT NULL DEFAULT '0',
  `remark` tinyint(4) DEFAULT NULL,
  `child` tinyint(1) DEFAULT NULL,
  `parentname` varchar(255) DEFAULT NULL,
  `checked` tinyint(1) DEFAULT NULL,
  `barcode` varchar(32) NOT NULL DEFAULT '0',
  `scanned` tinyint(1) NOT NULL DEFAULT '0',
  `scandate` datetime DEFAULT NULL,
  `scanner` int(10) NOT NULL DEFAULT '0',
  `blacklisted` tinyint(1) DEFAULT NULL,
  `seat_sector` int(11) DEFAULT NULL,
  `requires_seat` tinyint(1) DEFAULT NULL,
  `coupon` varchar(25) DEFAULT NULL,
  `ipaddress` varchar(60) DEFAULT NULL,
  `require_information` tinyint(1) DEFAULT NULL,
  `required_information` text DEFAULT NULL,
  `price` float DEFAULT NULL,
  `discount` float DEFAULT NULL,
  `fees` float DEFAULT NULL,
  `vat` float DEFAULT NULL,
  `price_excluding_vat` float DEFAULT NULL,
  `discount_type` tinyint(1) DEFAULT NULL,
  `discount_amount` float DEFAULT NULL,
  `vat_percentage` float DEFAULT NULL,
  `validation_token` varchar(64) NOT NULL,
  PRIMARY KEY (`orderid`),
  KEY `ordercode` (`ordercode`),
  KEY `idx_barcode` (`barcode`),
  UNIQUE KEY `idx_validation_token_orders` (`validation_token`)
)  AUTO_INCREMENT=10000 ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `#__ticketstation_tickets`;
CREATE TABLE IF NOT EXISTS `#__ticketstation_tickets` (
  `ticketid` int(10) NOT NULL AUTO_INCREMENT,
  `eventid` int(10) NOT NULL,
  `venue` int(11) NOT NULL,
  `parent` int(11) NOT NULL DEFAULT '0',
  `ticketname` varchar(255) NOT NULL,
  `ticketcode` varchar(5) NOT NULL,
  `freetext_1` varchar(255) DEFAULT NULL,
  `startdate` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `enddate` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ticketprice` double NOT NULL DEFAULT '0',
  `vat_percentage` double NOT NULL DEFAULT '0',
  `min_qty` int(5) NOT NULL DEFAULT '0',
  `max_qty` int(5) NOT NULL DEFAULT '0',
  `starting_total_tickets` int(11) NOT NULL DEFAULT '0',
  `totaltickets` int(11) NOT NULL DEFAULT '0',
  `counter_choice` tinyint(1) NOT NULL DEFAULT '0',
  `show_seatplans` tinyint(1) NOT NULL DEFAULT '0',
  `scans_on` tinyint(1) NOT NULL DEFAULT '1',
  `published` tinyint(1) NOT NULL DEFAULT '0',
  `use_auto_publish` tinyint(1) NOT NULL DEFAULT '0',
  `publish_date_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `use_sale_stop` tinyint(1) NOT NULL DEFAULT '0',
  `sale_stop` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ticket_size` varchar(2) NOT NULL,
  `override_ticketsize` varchar(50) NOT NULL,
  `ticket_orientation` varchar(1) NOT NULL,
  `eventname_fontcolor` varchar(6) NOT NULL,
  `eventname_fontsize` varchar(3) NOT NULL,
  `eventname_position` varchar(10) NOT NULL,
  `ticketname_fontcolor` varchar(6) NOT NULL,
  `ticketname_fontsize` varchar(3) NOT NULL,
  `ticketname_position` varchar(10) NOT NULL,
  `freetext_1_fontcolor` varchar(6) NOT NULL,
  `freetext_1_fontsize` varchar(3) NOT NULL,
  `freetext_1_position` varchar(10) NOT NULL,
  `ticketdate_fontcolor` varchar(6) NOT NULL,
  `ticketdate_fontsize` varchar(3) NOT NULL,
  `ticketdate_position` varchar(10) NOT NULL,
  `ticketprice_fontcolor` varchar(6) NOT NULL,
  `ticketprice_fontsize` varchar(3) NOT NULL,
  `ticketprice_position` varchar(10) NOT NULL,
  `orderdate_fontcolor` varchar(6) NOT NULL,
  `orderdate_fontsize` varchar(3) NOT NULL,
  `orderdate_position` varchar(10) NOT NULL,
  `client_fontcolor` varchar(6) NOT NULL,
  `client_fontsize` varchar(3) NOT NULL,
  `client_position` varchar(10) NOT NULL,
  `orderticketindex_fontcolor` varchar(6) NOT NULL,
  `orderticketindex_fontsize` varchar(3) NOT NULL,
  `orderticketindex_position` varchar(10) NOT NULL,
  `orderticketindex_prependtext_print` tinyint(1) NOT NULL DEFAULT '0',
  `orderticketindex_prependtext` varchar(20) NOT NULL,
  `ordernumber_fontcolor` varchar(6) NOT NULL,
  `ordernumber_fontsize` varchar(3) NOT NULL,
  `ordernumber_position` varchar(10) NOT NULL,
  `seatnumber_fontcolor` varchar(6) NOT NULL,
  `seatnumber_fontsize` varchar(3) NOT NULL,
  `seatnumber_position` varchar(10) NOT NULL,
  `orderreference_fontcolor` varchar(6) NOT NULL,
  `orderreference_fontsize` varchar(3) NOT NULL,
  `orderreference_position` varchar(10) NOT NULL,
  `orderreference_centered` tinyint(1) NOT NULL DEFAULT '0',
  `qrcode_position` varchar(10) NOT NULL,
  `qrcode_width` varchar(5) NOT NULL,
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`ticketid`)
)  ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `#__ticketstation_waitinglist`;
CREATE TABLE IF NOT EXISTS `#__ticketstation_waitinglist` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ticketid` int(11) NOT NULL,
  `eventid` int(11) NOT NULL,
  `userid` int(11) NOT NULL,
  `ordercode` varchar(50) NOT NULL,
  `confirmed` tinyint(1) NOT NULL,
  `date_added` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `processed` tinyint(1) NOT NULL,
  `ip_address` varchar(50) NOT NULL,
  `sent` tinyint(1) NOT NULL DEFAULT '0',
  `requires_seat` tinyint(1) NOT NULL,
  `date_sent` datetime DEFAULT NULL,
  `validation_token` varchar(64) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_validation_token_waitinglist` (`validation_token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `#__ticketstation_seatplansettings`;
CREATE TABLE IF NOT EXISTS `#__ticketstation_seatplansettings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ticketid` int(11) NOT NULL,
  `background_color` varchar(6) NOT NULL DEFAULT 'ffffff',
  `border_color` varchar(6) NOT NULL DEFAULT '000000',
  `font_color` varchar(6) NOT NULL DEFAULT '000000',
  `multi_seat` tinyint(1) NOT NULL DEFAULT '1',
  `seat_width` varchar(2) DEFAULT NULL,
  `seat_height` varchar(2) DEFAULT NULL,
  `type` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `#__ticketstation_seatplancoords`;
CREATE TABLE IF NOT EXISTS `#__ticketstation_seatplancoords` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `orderid` int(11) NOT NULL DEFAULT '0',
  `x_pos` int(4) NOT NULL,
  `y_pos` int(4) NOT NULL,
  `ticketid` int(11) NOT NULL,
  `row_name` varchar(5) DEFAULT NULL,
  `seatid` int(11) NOT NULL,
  `booked` tinyint(1) NOT NULL DEFAULT '0',
  `parent` int(11) NOT NULL DEFAULT '0',
  `type` tinyint(1) NOT NULL DEFAULT '1',
  `width` int(11) NOT NULL,
  `height` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1;

DROP TABLE IF EXISTS `#__ticketstation_remarks`;
CREATE TABLE IF NOT EXISTS `#__ticketstation_remarks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ordercode` varchar(25) NOT NULL DEFAULT '',
  `remarks` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`)
)  ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `#__ticketstation_customer_notes`;
CREATE TABLE IF NOT EXISTS `#__ticketstation_customer_notes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ordercode` int(11) NOT NULL,
  `note` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `ordercode` (`ordercode`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `#__ticketstation_history`;
CREATE TABLE IF NOT EXISTS `#__ticketstation_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ordercode` varchar(50) NOT NULL,
  `event_type` varchar(50) NOT NULL,
  `message` varchar(500) NOT NULL,
  `context` text DEFAULT NULL,
  `actor` varchar(150) DEFAULT NULL,
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ordercode` (`ordercode`)
)  ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `#__ticketstation_invoices`;
CREATE TABLE IF NOT EXISTS `#__ticketstation_invoices` (
  `invoiceid` int(11) NOT NULL AUTO_INCREMENT,
  `ordercode` varchar(50) NOT NULL,
  `userid` int(11) NOT NULL,
  `invoicedate` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `netto` decimal(10,2) NOT NULL DEFAULT '0.00',
  `bruto` decimal(10,2) NOT NULL DEFAULT '0.00',
  `vat` decimal(10,2) NOT NULL DEFAULT '0.00',
  `discount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `fees` decimal(10,2) NOT NULL DEFAULT '0.00',
  `coupon_code` varchar(50) DEFAULT NULL,
  `payment_provider` varchar(50) DEFAULT NULL,
  `sent` tinyint(1) NOT NULL DEFAULT '0',
  `date_sent` datetime DEFAULT NULL,
  PRIMARY KEY (`invoiceid`),
  KEY `ordercode` (`ordercode`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `#__ticketstation_invoice_items`;
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

DROP TABLE IF EXISTS `#__ticketstation_templates`;
CREATE TABLE IF NOT EXISTS `#__ticketstation_templates` (
  `mailid` int(10) NOT NULL AUTO_INCREMENT,
  `alias` varchar(255) NOT NULL DEFAULT '',
  `mailbody` varchar(5000) NOT NULL DEFAULT '',
  `mailsubject` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`mailid`)
)  ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `#__ticketstation_country`;
CREATE TABLE IF NOT EXISTS `#__ticketstation_country` (
  `country_id` int(11) NOT NULL AUTO_INCREMENT,
  `country` varchar(64) NOT NULL,
  `country_3_code` char(3) NOT NULL,
  `country_2_code` char(2) NOT NULL,
  `requires_vat` tinyint(1) NOT NULL,
  `published` tinyint(1) NOT NULL,
  PRIMARY KEY (`country_id`),
  KEY `idx_country_name` (`country`)
)  DEFAULT CHARSET=utf8 COMMENT='Country records';

INSERT IGNORE INTO `#__ticketstation_country` VALUES("1","Unknown","UKN","UN","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("2","Albania","ALB","AL","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("3","Algeria","DZA","DZ","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("4","American Samoa","ASM","AS","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("5","Andorra","AND","AD","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("6","Angola","AGO","AO","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("7","Anguilla","AIA","AI","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("8","Antarctica","ATA","AQ","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("9","Antigua and Barbuda","ATG","AG","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("10","Argentina","ARG","AR","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("11","Armenia","ARM","AM","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("12","Aruba","ABW","AW","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("13","Australia","AUS","AU","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("14","Austria","AUT","AT","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("15","Azerbaijan","AZE","AZ","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("16","Bahamas","BHS","BS","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("17","Bahrain","BHR","BH","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("18","Bangladesh","BGD","BD","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("19","Barbados","BRB","BB","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("20","Belarus","BLR","BY","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("21","Belgium","BEL","BE","1","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("22","Belize","BLZ","BZ","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("23","Benin","BEN","BJ","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("24","Bermuda","BMU","BM","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("25","Bhutan","BTN","BT","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("26","Bolivia","BOL","BO","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("27","Bosnia and Herzegowina","BIH","BA","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("28","Botswana","BWA","BW","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("29","Bouvet Island","BVT","BV","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("30","Brazil","BRA","BR","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("31","British Indian Ocean Territory","IOT","IO","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("32","Brunei Darussalam","BRN","BN","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("33","Bulgaria","BGR","BG","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("34","Burkina Faso","BFA","BF","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("35","Burundi","BDI","BI","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("36","Cambodia","KHM","KH","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("37","Cameroon","CMR","CM","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("38","Canada","CAN","CA","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("39","Cape Verde","CPV","CV","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("40","Cayman Islands","CYM","KY","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("41","Central African Republic","CAF","CF","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("42","Chad","TCD","TD","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("43","Chile","CHL","CL","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("44","China","CHN","CN","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("45","Christmas Island","CXR","CX","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("46","Cocos (Keeling) Islands","CCK","CC","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("47","Colombia","COL","CO","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("48","Comoros","COM","KM","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("49","Congo","COG","CG","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("50","Cook Islands","COK","CK","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("51","Costa Rica","CRI","CR","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("52","Cote D\'Ivoire","CIV","CI","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("53","Croatia","HRV","HR","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("54","Cuba","CUB","CU","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("55","Cyprus","CYP","CY","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("56","Czech Republic","CZE","CZ","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("57","Denmark","DNK","DK","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("58","Djibouti","DJI","DJ","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("59","Dominica","DMA","DM","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("60","Dominican Republic","DOM","DO","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("61","East Timor","TMP","TP","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("62","Ecuador","ECU","EC","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("63","Egypt","EGY","EG","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("64","El Salvador","SLV","SV","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("65","Equatorial Guinea","GNQ","GQ","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("66","Eritrea","ERI","ER","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("67","Estonia","EST","EE","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("68","Ethiopia","ETH","ET","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("69","Falkland Islands (Malvinas)","FLK","FK","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("70","Faroe Islands","FRO","FO","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("71","Fiji","FJI","FJ","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("72","Finland","FIN","FI","1","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("73","France","FRA","FR","1","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("74","France, Metropolitan","FXX","FX","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("75","French Guiana","GUF","GF","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("76","French Polynesia","PYF","PF","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("77","French Southern Territories","ATF","TF","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("78","Gabon","GAB","GA","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("79","Gambia","GMB","GM","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("80","Georgia","GEO","GE","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("81","Germany","DEU","DE","1","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("82","Ghana","GHA","GH","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("83","Gibraltar","GIB","GI","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("84","Greece","GRC","GR","1","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("85","Greenland","GRL","GL","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("86","Grenada","GRD","GD","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("87","Guadeloupe","GLP","GP","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("88","Guam","GUM","GU","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("89","Guatemala","GTM","GT","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("90","Guinea","GIN","GN","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("91","Guinea-bissau","GNB","GW","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("92","Guyana","GUY","GY","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("93","Haiti","HTI","HT","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("94","Heard and Mc Donald Islands","HMD","HM","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("95","Honduras","HND","HN","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("96","Hong Kong","HKG","HK","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("97","Hungary","HUN","HU","1","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("98","Iceland","ISL","IS","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("99","India","IND","IN","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("100","Indonesia","IDN","ID","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("101","Iran (Islamic Republic of)","IRN","IR","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("102","Iraq","IRQ","IQ","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("103","Ireland","IRL","IE","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("104","Israel","ISR","IL","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("105","Italy","ITA","IT","1","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("106","Jamaica","JAM","JM","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("107","Japan","JPN","JP","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("108","Jordan","JOR","JO","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("109","Kazakhstan","KAZ","KZ","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("110","Kenya","KEN","KE","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("111","Kiribati","KIR","KI","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("112","Korea, Democratic Republic of","PRK","KP","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("113","Korea, Republic of","KOR","KR","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("114","Kuwait","KWT","KW","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("115","Kyrgyzstan","KGZ","KG","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("116","Lao People\'s Democratic Republic","LAO","LA","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("117","Latvia","LVA","LV","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("118","Lebanon","LBN","LB","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("119","Lesotho","LSO","LS","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("120","Liberia","LBR","LR","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("121","Libyan Arab Jamahiriya","LBY","LY","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("122","Liechtenstein","LIE","LI","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("123","Lithuania","LTU","LT","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("124","Luxembourg","LUX","LU","1","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("125","Macau","MAC","MO","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("126","Macedonia","MKD","MK","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("127","Madagascar","MDG","MG","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("128","Malawi","MWI","MW","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("129","Malaysia","MYS","MY","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("130","Maldives","MDV","MV","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("131","Mali","MLI","ML","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("132","Malta","MLT","MT","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("133","Marshall Islands","MHL","MH","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("134","Martinique","MTQ","MQ","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("135","Mauritania","MRT","MR","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("136","Mauritius","MUS","MU","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("137","Mayotte","MYT","YT","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("138","Mexico","MEX","MX","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("139","Micronesia, Federated States of","FSM","FM","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("140","Moldova, Republic of","MDA","MD","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("141","Monaco","MCO","MC","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("142","Mongolia","MNG","MN","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("143","Montserrat","MSR","MS","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("144","Morocco","MAR","MA","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("145","Mozambique","MOZ","MZ","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("146","Myanmar","MMR","MM","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("147","Namibia","NAM","NA","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("148","Nauru","NRU","NR","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("149","Nepal","NPL","NP","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("150","Netherlands","NLD","NL","1","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("151","Netherlands Antilles","ANT","AN","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("152","New Caledonia","NCL","NC","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("153","New Zealand","NZL","NZ","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("154","Nicaragua","NIC","NI","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("155","Niger","NER","NE","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("156","Nigeria","NGA","NG","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("157","Niue","NIU","NU","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("158","Norfolk Island","NFK","NF","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("159","Northern Mariana Islands","MNP","MP","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("160","Norway","NOR","NO","1","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("161","Oman","OMN","OM","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("162","Pakistan","PAK","PK","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("163","Palau","PLW","PW","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("164","Panama","PAN","PA","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("165","Papua New Guinea","PNG","PG","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("166","Paraguay","PRY","PY","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("167","Peru","PER","PE","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("168","Philippines","PHL","PH","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("169","Pitcairn","PCN","PN","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("170","Poland","POL","PL","1","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("171","Portugal","PRT","PT","1","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("172","Puerto Rico","PRI","PR","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("173","Qatar","QAT","QA","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("174","Reunion","REU","RE","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("175","Romania","ROM","RO","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("176","Russian Federation","RUS","RU","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("177","Rwanda","RWA","RW","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("178","Saint Kitts and Nevis","KNA","KN","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("179","Saint Lucia","LCA","LC","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("180","Saint Vincent and the Grenadines","VCT","VC","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("181","Samoa","WSM","WS","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("182","San Marino","SMR","SM","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("183","Sao Tome and Principe","STP","ST","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("184","Saudi Arabia","SAU","SA","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("185","Senegal","SEN","SN","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("186","Seychelles","SYC","SC","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("187","Sierra Leone","SLE","SL","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("188","Singapore","SGP","SG","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("189","Slovakia (Slovak Republic)","SVK","SK","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("190","Slovenia","SVN","SI","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("191","Solomon Islands","SLB","SB","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("192","Somalia","SOM","SO","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("193","South Africa","ZAF","ZA","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("194","South Georgia and the South Sandwich Islands","SGS","GS","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("195","Spain","ESP","ES","1","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("196","Sri Lanka","LKA","LK","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("197","St. Helena","SHN","SH","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("198","St. Pierre and Miquelon","SPM","PM","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("199","Sudan","SDN","SD","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("200","Suriname","SUR","SR","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("201","Svalbard and Jan Mayen Islands","SJM","SJ","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("202","Swaziland","SWZ","SZ","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("203","Sweden","SWE","SE","1","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("204","Switzerland","CHE","CH","1","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("205","Syrian Arab Republic","SYR","SY","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("206","Taiwan","TWN","TW","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("207","Tajikistan","TJK","TJ","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("208","Tanzania, United Republic of","TZA","TZ","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("209","Thailand","THA","TH","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("210","Togo","TGO","TG","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("211","Tokelau","TKL","TK","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("212","Tonga","TON","TO","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("213","Trinidad and Tobago","TTO","TT","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("214","Tunisia","TUN","TN","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("215","Turkey","TUR","TR","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("216","Turkmenistan","TKM","TM","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("217","Turks and Caicos Islands","TCA","TC","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("218","Tuvalu","TUV","TV","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("219","Uganda","UGA","UG","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("220","Ukraine","UKR","UA","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("221","United Arab Emirates","ARE","AE","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("222","United Kingdom","GBR","GB","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("223","United States","USA","US","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("224","United States Minor Outlying Islands","UMI","UM","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("225","Uruguay","URY","UY","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("226","Uzbekistan","UZB","UZ","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("227","Vanuatu","VUT","VU","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("228","Vatican City State (Holy See)","VAT","VA","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("229","Venezuela","VEN","VE","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("230","Viet Nam","VNM","VN","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("231","Virgin Islands (British)","VGB","VG","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("232","Virgin Islands (U.S.)","VIR","VI","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("233","Wallis and Futuna Islands","WLF","WF","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("234","Western Sahara","ESH","EH","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("235","Yemen","YEM","YE","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("236","Serbia","SRB","RS","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("237","The Democratic Republic of Congo","DRC","DC","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("238","Zambia","ZMB","ZM","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("239","Zimbabwe","ZWE","ZW","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("240","East Timor","XET","XE","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("241","Jersey","XJE","XJ","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("242","St. Barthelemy","XSB","XB","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("243","St. Eustatius","XSE","XU","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("244","Canary Islands","XCA","XC","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("245","Montenegro","MNE","ME","0","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("249","Afghanistan","AFG","AF","0","1");

INSERT IGNORE INTO `#__ticketstation_templates` VALUES("1","sending Tickets after successful Payment","<p>Hi {firstname},</p><p>Thank you for your purchase!<br />Your order number is <strong>{ordercode}</strong>.</p><p>Please find your tickets attached to this e-mail.<br />Please don't hesitate to contact us in case of any questions.</p><p>Kind regards,</p><p>{company_name}<br />{company_website}</p>","Here are your tickets!");
INSERT IGNORE INTO `#__ticketstation_templates` VALUES("2","resending Tickets","<p>Hi {firstname},</p><p>We are hereby sending you the tickets you ordered once again.<br />Please don't hesitate to contact us in case of any questions.</p><p>Kind regards,</p><p>{company_name}<br />{company_website}</p>","Here are your tickets!");
INSERT IGNORE INTO `#__ticketstation_templates` VALUES("3","Sending Payment Link","<p>Hi {firstname},</p><p>We are sending you the payment link for the tickets you ordered:</p><p>{paymentlink}</p><p>Click on the link (or copy and paste it into your browser) to make your payment.<br/>After successful payment, the tickets will be sent directly to this email address.</p><p>Please don't hesitate to contact us in case of any questions.</p><p>Kind regards,</p><p>{company_name}<br />{company_website}</p>","Payment link for your tickets");
INSERT IGNORE INTO `#__ticketstation_templates` VALUES("4","Waiting list confirmation","<p>Hi {firstname},</p><p>You are on the waiting list for:</p><p>{orderlist}</p><p>Please confirm your spot on this waiting list. As soon as tickets become available, you will receive a separate email with a payment link — we cannot guarantee your spot on the waiting list without confirmation.</p><p>{confirmationlink}</p><p>Please don't hesitate to contact us in case of any questions.</p><p>Kind regards,</p><p>{company_name}<br />{company_website}</p>","Confirm your spot on the waiting list");
INSERT IGNORE INTO `#__ticketstation_templates` VALUES("5","Invoice","<p>Hi {firstname},</p><p>Attached you will find the invoice for your order <strong>{ordercode}</strong>.</p><p>Invoice number: {invoice_id}<br />Amount: {price}</p><p>Please don't hesitate to contact us in case of any questions.</p><p>Kind regards,</p><p>{company_name}<br />{company_website}</p>","Invoice for your ordered tickets");

INSERT IGNORE INTO `#__ticketstation_mollie` VALUES(
"1",
"0",
"0",
"0",
"",
"",
"Ordernumber:",
"0",
"0",
"en_GB",
"1",
"1",
"0");

INSERT IGNORE INTO `#__ticketstation_config` VALUES(
"1",
"0",
"0",
"€",
"0",
"0",
"EUR",
"0",
"3",
"25",
"d-m-Y",
"2",
"1",
"0",
"0",
"1",
"1",
"1",
"0",
"Company",
"My Contact Adres 12",
"NB",
"1234 AB",
"YourCity",
"",
"0123-456789",
"0123-456789",
"info@yourdomain.com",
"https://www.yourdomain.com",
"",
"",
"250",
"0",
"150",
"72",
"150",
"72",
"1",
"1",
"2",
"1",
"135-12",
"0",
"13",
"0",
"1",
"1",
"0.5",
"1",
"0",
"0",
"0",
"0",
"0",
"0",
"1",
"0",
"fae1b8d127",
"2e7bd219599560881e883e611804cbb5-us2",
"1",
"0",
"1",
"235383",
"0",
"2",
"0",
"0",
"0",
"0",
"0",
"10-100",
"10-120",
"10-10",
"10-10",
"10-10",
"10-130",
"20-80",
"10-140",
"10-10",
"10-10",
"55-150",
"55-140",
"0",
"%%SALUTATION%% %%FIRSTNAME%%	%%LASTNAME%% \n%%ADDRESS1%% \n%%ZIPCODE%% %%CITY%% \n%%COUNTRY_FULL%% (%%COUNTRY_2D%%)",
"%%COMPANY_NAME%% \n%%ADDRESS1%% \n%%ZIPCODE%% %%CITY%% \n%%EMAIL%% \n%%WEBSITE%%",
"0",
"1",
"000",
"0",
"0",
"I-",
"",
"1",
"H:i",
"test@yourdomain.com, welcome@yourdomain.com, name@yourdomain.com",
"0",
"",
"",
"1",
"0",
"0",
"0",
"",
"",
"",
"");