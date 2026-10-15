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

DROP TABLE IF EXISTS `#__ticketstation_clients`;
CREATE TABLE IF NOT EXISTS `#__ticketstation_clients` (
  `clientid` int(10) NOT NULL AUTO_INCREMENT,
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
  `test` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`clientid`)
)  ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `#__ticketstation_config`;
CREATE TABLE IF NOT EXISTS `#__ticketstation_config` (
  `configid` int(1) NOT NULL AUTO_INCREMENT,
  `show_secondaddress` tinyint(1) NOT NULL,
  `show_thirdaddress` tinyint(1) NOT NULL,
  `valuta` varchar(8) NOT NULL,
  `payments_on` tinyint(1) NOT NULL,
  `transactioncosts` float NOT NULL,
  `priceformat` tinyint(2) NOT NULL,
  `persending` int(11) NOT NULL,
  `dateformat` varchar(32) NOT NULL,
  `transcosts` double NOT NULL,
  `variable_transcosts` double NOT NULL,
  `man_payment` tinyint(1) NOT NULL,
  `show_cancel` tinyint(1) NOT NULL,
  `show_available_tickets` tinyint(1) NOT NULL,
  `show_quantity_eventlist` tinyint(1) NOT NULL,
  `show_price_eventlist` tinyint(1) NOT NULL,
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
  `qr_width` int(5) NOT NULL,
  `removal_days` tinyint(2) NOT NULL,
  `send_profile_mail` tinyint(1) NOT NULL,
  `tos_tpl` int(11) NOT NULL,
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
  `load_bootstrap` tinyint(1) NOT NULL,
  `load_bootstrap_tpl` tinyint(1) NOT NULL,
  `use_coupons` tinyint(1) NOT NULL,
  `load_jquery` tinyint(1) NOT NULL,
  `show_remark_field` tinyint(4) NOT NULL,
  `show_waitinglist` tinyint(1) NOT NULL,
  `eventname_position` varchar(35) NOT NULL,
  `ordernumber_position` varchar(35) NOT NULL,
  `orderdate_position` varchar(35) NOT NULL,
  `show_mailchimps` tinyint(1) NOT NULL,
  `address_format_client` text NOT NULL,
  `address_format_company` text NOT NULL,
  `show_zipcode` tinyint(1) NOT NULL,
  `show_phone` tinyint(1) NOT NULL,
  `send_invoice` tinyint(1) NOT NULL,
  `invoice_prefix` varchar(10) NOT NULL,
  `time_format` varchar(32) NOT NULL DEFAULT 'H:i',
  `next_ordercode` varchar(20) NOT NULL DEFAULT '',
  `show_venue` tinyint(1) NOT NULL DEFAULT 1,
  `show_venue_address` tinyint(1) NOT NULL DEFAULT 0,
  `show_venue_description` tinyint(1) NOT NULL DEFAULT 0,
  `show_venue_website` tinyint(1) NOT NULL DEFAULT 0,
  `show_jsonld` tinyint(1) NOT NULL DEFAULT 1,
  `reminder_on` tinyint(1) NOT NULL DEFAULT 0,
  `reminder_hours` smallint(4) NOT NULL DEFAULT 24,
  `from_name` varchar(255) NOT NULL DEFAULT '',
  `from_email` varchar(255) NOT NULL DEFAULT '',
  `terms_url` varchar(255) NOT NULL DEFAULT '',
  `privacy_url` varchar(255) NOT NULL DEFAULT '',
  `send_tickets_directly` tinyint(1) NOT NULL DEFAULT 1,
  `wallet_apple` tinyint(1) NOT NULL DEFAULT 0,
  `wallet_apple_cert` text DEFAULT NULL,
  `wallet_apple_key` text DEFAULT NULL,
  `wallet_apple_pending_key` text DEFAULT NULL,
  `wallet_google` tinyint(1) NOT NULL DEFAULT 0,
  `wallet_google_issuer_id` varchar(30) NOT NULL DEFAULT '',
  `wallet_google_key` text DEFAULT NULL,
  `wallet_google_updates` tinyint(1) NOT NULL DEFAULT 0,
  `wallet_apple_updates` tinyint(1) NOT NULL DEFAULT 0,
  `wallet_logo` varchar(255) NOT NULL DEFAULT '',
  `wallet_bg_color` varchar(7) NOT NULL DEFAULT '#1f2937',
  `wallet_fg_color` varchar(7) NOT NULL DEFAULT '#ffffff',
  `price_decimals` tinyint(2) NOT NULL DEFAULT 2,
  `price_decimal_sep` varchar(4) NOT NULL DEFAULT ',',
  `price_thousands_sep` varchar(4) NOT NULL DEFAULT '',
  `price_symbol_after` tinyint(1) NOT NULL DEFAULT 0,
  `payment_provider` varchar(50) NOT NULL DEFAULT '',
  `payment_currency` varchar(3) NOT NULL DEFAULT 'EUR',
  `test_mode` tinyint(1) NOT NULL DEFAULT 0,
  `checkout_field_map` varchar(1000) NOT NULL DEFAULT '',
  `checkout_layout` tinyint(1) NOT NULL DEFAULT 0,
  `invoice_counter_live` int(11) NOT NULL DEFAULT 0,
  `invoice_counter_test` int(11) NOT NULL DEFAULT 0,
  `company_color` varchar(7) NOT NULL DEFAULT '',
  `portal_mode` tinyint(1) NOT NULL DEFAULT 0,
  `portal_past_months` smallint(4) NOT NULL DEFAULT 0,
  `portal_share` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`configid`)
)  ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `#__ticketstation_transactions`;
CREATE TABLE IF NOT EXISTS `#__ticketstation_transactions` (
  `pid` int(10) NOT NULL AUTO_INCREMENT,
  `transid` int(10) NOT NULL,
  `userid` int(10) NOT NULL,
  `details` text NOT NULL,
  `amount` double NOT NULL,
  `date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `type` varchar(50) NOT NULL,
  `provider` varchar(50) NOT NULL DEFAULT '',
  `orderid` int(10) NOT NULL DEFAULT '0',
  `test` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`pid`)
)  ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `#__ticketstation_transactions_temp`;
CREATE TABLE IF NOT EXISTS `#__ticketstation_transactions_temp` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `userid` int(11) NOT NULL,
  `transaction_number` varchar(50) NOT NULL,
  `provider` varchar(50) NOT NULL DEFAULT '',
  `provider_payment_id` varchar(100) NOT NULL DEFAULT '',
  `ordercode` int(10) NOT NULL,
  `return_token` varchar(64) DEFAULT NULL,
  `create_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `processed` tinyint(1) NOT NULL,
  `status` tinyint(3) DEFAULT NULL,
  `errorcode` tinyint(1) DEFAULT NULL,
  `message` varchar(255) DEFAULT NULL,
  `amounts` varchar(255) DEFAULT NULL,
  `test` tinyint(1) NOT NULL DEFAULT 0,
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
  `coupon_tickets` varchar(2000) NOT NULL DEFAULT '',
  `coupon_events` varchar(2000) NOT NULL DEFAULT '',
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
  `presale_display` tinyint(1) NOT NULL DEFAULT '1',
  `waitinglist` tinyint(1) NOT NULL DEFAULT '0',
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
  `payment_requested` datetime DEFAULT NULL,
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
  `ipaddress` varchar(60) DEFAULT NULL,
  `require_information` tinyint(1) DEFAULT NULL,
  `required_information` text DEFAULT NULL,
  `price` float DEFAULT NULL,
  `discount` float DEFAULT NULL,
  `vat` float DEFAULT NULL,
  `price_excluding_vat` float DEFAULT NULL,
  `vat_percentage` float DEFAULT NULL,
  `validation_token` varchar(64) NOT NULL,
  `refund_state` tinyint(1) NOT NULL DEFAULT '0',
  `refund_id` int(11) NOT NULL DEFAULT '0',
  `test` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`orderid`),
  KEY `ordercode` (`ordercode`),
  KEY `idx_barcode` (`barcode`),
  KEY `idx_ticketid` (`ticketid`),
  UNIQUE KEY `idx_validation_token_orders` (`validation_token`)
)  AUTO_INCREMENT=10000 ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `#__ticketstation_ordertotals`;
CREATE TABLE IF NOT EXISTS `#__ticketstation_ordertotals` (
  `ordercode` varchar(50) NOT NULL,
  `fee_type` tinyint(1) DEFAULT NULL,
  `fee_rate` decimal(10,4) DEFAULT NULL,
  `fee_fixed` decimal(10,4) DEFAULT NULL,
  `captured` datetime DEFAULT NULL,
  `coupon` varchar(25) DEFAULT NULL,
  `discount_type` tinyint(1) DEFAULT NULL,
  `discount_amount` float DEFAULT NULL,
  `coupon_tickets` text DEFAULT NULL,
  PRIMARY KEY (`ordercode`),
  KEY `coupon` (`coupon`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

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
  `doors_open` varchar(5) NOT NULL DEFAULT '',
  `ticketprice` double NOT NULL DEFAULT '0',
  `vat_percentage` double NOT NULL DEFAULT '0',
  `min_qty` int(5) NOT NULL DEFAULT '0',
  `max_qty` int(5) NOT NULL DEFAULT '0',
  `starting_total_tickets` int(11) NOT NULL DEFAULT '0',
  `counter_choice` tinyint(1) NOT NULL DEFAULT '0',
  `show_seatplans` tinyint(1) NOT NULL DEFAULT '0',
  `scans_on` tinyint(1) NOT NULL DEFAULT '1',
  `published` tinyint(1) NOT NULL DEFAULT '0',
  `use_auto_publish` tinyint(1) NOT NULL DEFAULT '0',
  `publish_date_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `presale_display` tinyint(1) NOT NULL DEFAULT '0',
  `use_sale_stop` tinyint(1) NOT NULL DEFAULT '0',
  `sale_stop` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ticket_size` varchar(2) NOT NULL,
  `override_ticketsize` varchar(50) NOT NULL,
  `ticket_orientation` varchar(1) NOT NULL,
  `ticket_font` varchar(20) NOT NULL DEFAULT 'raleway',
  `ticket_textalign` varchar(1) NOT NULL DEFAULT 'L',
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
  `doors_open_fontcolor` varchar(6) NOT NULL DEFAULT '',
  `doors_open_fontsize` varchar(3) NOT NULL DEFAULT '',
  `doors_open_position` varchar(10) NOT NULL DEFAULT '',
  `venue_fontcolor` varchar(6) NOT NULL DEFAULT '',
  `venue_fontsize` varchar(3) NOT NULL DEFAULT '',
  `venue_position` varchar(10) NOT NULL DEFAULT '',
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
  `test` tinyint(1) NOT NULL DEFAULT 0,
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
  `seat_width` varchar(3) DEFAULT NULL,
  `seat_height` varchar(3) DEFAULT NULL,
  `type` tinyint(1) NOT NULL DEFAULT '1',
  `background_image` varchar(255) NOT NULL DEFAULT '',
  `bg_offset_x` int(11) NOT NULL DEFAULT '0',
  `bg_offset_y` int(11) NOT NULL DEFAULT '0',
  `canvas_width` int(11) NOT NULL DEFAULT '0',
  `canvas_height` int(11) NOT NULL DEFAULT '0',
  `grid_size` int(11) NOT NULL DEFAULT '10',
  `prevent_orphans` tinyint(1) NOT NULL DEFAULT '0',
  `shapes` mediumtext NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_ticketid` (`ticketid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `#__ticketstation_seatplantemplates`;
CREATE TABLE IF NOT EXISTS `#__ticketstation_seatplantemplates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `venue_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL DEFAULT '',
  `layout` mediumtext NOT NULL,
  `created` datetime NOT NULL,
  `modified` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_venue_id` (`venue_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `#__ticketstation_seatplancoords`;
CREATE TABLE IF NOT EXISTS `#__ticketstation_seatplancoords` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `orderid` int(11) NOT NULL DEFAULT '0',
  `x_pos` int(4) NOT NULL,
  `y_pos` int(4) NOT NULL,
  `ticketid` int(11) NOT NULL,
  `row_name` varchar(5) DEFAULT NULL,
  `seatid` varchar(10) NOT NULL DEFAULT '0',
  `booked` tinyint(1) NOT NULL DEFAULT '0',
  `blocked` tinyint(1) NOT NULL DEFAULT '0',
  `parent` int(11) NOT NULL DEFAULT '0',
  `type` tinyint(1) NOT NULL DEFAULT '1',
  `width` int(11) NOT NULL,
  `height` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_ticketid` (`ticketid`),
  KEY `idx_parent` (`parent`),
  KEY `idx_orderid` (`orderid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `#__ticketstation_order_references`;
CREATE TABLE IF NOT EXISTS `#__ticketstation_order_references` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ordercode` varchar(25) NOT NULL DEFAULT '',
  `order_reference` varchar(255) NOT NULL DEFAULT '',
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

DROP TABLE IF EXISTS `#__ticketstation_event_mails`;
CREATE TABLE IF NOT EXISTS `#__ticketstation_event_mails` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `eventid` int(11) NOT NULL,
  `ordercode` int(11) NOT NULL,
  `kind` varchar(20) NOT NULL,
  `startdate` datetime DEFAULT NULL,
  `sent` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_event_mails` (`ordercode`, `eventid`, `kind`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `#__ticketstation_portal_requests`;
CREATE TABLE IF NOT EXISTS `#__ticketstation_portal_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `emailhash` char(64) NOT NULL,
  `ip` varchar(60) NOT NULL DEFAULT '',
  `created` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_portal_email` (`emailhash`, `created`),
  KEY `idx_portal_ip` (`ip`, `created`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `#__ticketstation_portal_links`;
CREATE TABLE IF NOT EXISTS `#__ticketstation_portal_links` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `userid` int(11) NOT NULL,
  `emailaddress` varchar(255) NOT NULL,
  `created` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_portal_link` (`userid`, `emailaddress`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `#__ticketstation_wallet_passes`;
CREATE TABLE IF NOT EXISTS `#__ticketstation_wallet_passes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ordercode` varchar(50) NOT NULL,
  `orderid` int(11) NOT NULL,
  `wallet` varchar(10) NOT NULL,
  `pass_id` varchar(255) NOT NULL,
  `class_id` varchar(255) NOT NULL DEFAULT '',
  `created` datetime NOT NULL,
  `update_pending` tinyint(1) NOT NULL DEFAULT 0,
  `message_pending` int(11) NOT NULL DEFAULT 0,
  `updated` datetime DEFAULT NULL,
  `update_error` varchar(255) NOT NULL DEFAULT '',
  `updated_at` bigint(20) NOT NULL DEFAULT 0,
  `message_shown` int(11) NOT NULL DEFAULT 0,
  `removed` tinyint(1) NOT NULL DEFAULT 0,
  `snapshot` mediumtext DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_wallet_pass` (`wallet`, `pass_id`),
  KEY `idx_orderid` (`orderid`),
  KEY `idx_ordercode` (`ordercode`),
  KEY `idx_pending` (`update_pending`, `message_pending`)
)  ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `#__ticketstation_wallet_devices`;
CREATE TABLE IF NOT EXISTS `#__ticketstation_wallet_devices` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `device_id` varchar(64) NOT NULL,
  `pass_row` int(11) NOT NULL,
  `push_token` varchar(255) NOT NULL DEFAULT '',
  `created` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_device_pass` (`device_id`, `pass_row`),
  KEY `idx_pass_row` (`pass_row`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `#__ticketstation_wallet_messages`;
CREATE TABLE IF NOT EXISTS `#__ticketstation_wallet_messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `eventid` int(11) NOT NULL,
  `header` varchar(100) NOT NULL DEFAULT '',
  `body` text NOT NULL,
  `created` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `#__ticketstation_refunds`;
CREATE TABLE IF NOT EXISTS `#__ticketstation_refunds` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ordercode` int(11) NOT NULL,
  `type` varchar(20) NOT NULL DEFAULT 'refund',
  `provider` varchar(50) NOT NULL DEFAULT '',
  `provider_refund_id` varchar(50) DEFAULT NULL,
  `provider_payment_id` varchar(50) NOT NULL DEFAULT '',
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
  `treatments` text DEFAULT NULL,
  `applied` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_provider_refund_id` (`provider_refund_id`),
  KEY `ordercode` (`ordercode`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

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
  `test` tinyint(1) NOT NULL DEFAULT 0,
  `invoice_no` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`invoiceid`),
  UNIQUE KEY `idx_invoice_no` (`test`, `invoice_no`),
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
  `published` tinyint(1) NOT NULL,
  PRIMARY KEY (`country_id`),
  KEY `idx_country_name` (`country`)
)  DEFAULT CHARSET=utf8 COMMENT='Country records';

INSERT IGNORE INTO `#__ticketstation_country` VALUES("1","Unknown","UKN","UN","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("2","Albania","ALB","AL","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("3","Algeria","DZA","DZ","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("4","American Samoa","ASM","AS","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("5","Andorra","AND","AD","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("6","Angola","AGO","AO","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("7","Anguilla","AIA","AI","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("8","Antarctica","ATA","AQ","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("9","Antigua and Barbuda","ATG","AG","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("10","Argentina","ARG","AR","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("11","Armenia","ARM","AM","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("12","Aruba","ABW","AW","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("13","Australia","AUS","AU","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("14","Austria","AUT","AT","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("15","Azerbaijan","AZE","AZ","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("16","Bahamas","BHS","BS","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("17","Bahrain","BHR","BH","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("18","Bangladesh","BGD","BD","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("19","Barbados","BRB","BB","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("20","Belarus","BLR","BY","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("21","Belgium","BEL","BE","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("22","Belize","BLZ","BZ","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("23","Benin","BEN","BJ","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("24","Bermuda","BMU","BM","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("25","Bhutan","BTN","BT","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("26","Bolivia","BOL","BO","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("27","Bosnia and Herzegowina","BIH","BA","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("28","Botswana","BWA","BW","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("29","Bouvet Island","BVT","BV","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("30","Brazil","BRA","BR","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("31","British Indian Ocean Territory","IOT","IO","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("32","Brunei Darussalam","BRN","BN","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("33","Bulgaria","BGR","BG","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("34","Burkina Faso","BFA","BF","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("35","Burundi","BDI","BI","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("36","Cambodia","KHM","KH","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("37","Cameroon","CMR","CM","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("38","Canada","CAN","CA","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("39","Cape Verde","CPV","CV","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("40","Cayman Islands","CYM","KY","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("41","Central African Republic","CAF","CF","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("42","Chad","TCD","TD","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("43","Chile","CHL","CL","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("44","China","CHN","CN","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("45","Christmas Island","CXR","CX","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("46","Cocos (Keeling) Islands","CCK","CC","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("47","Colombia","COL","CO","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("48","Comoros","COM","KM","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("49","Congo","COG","CG","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("50","Cook Islands","COK","CK","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("51","Costa Rica","CRI","CR","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("52","Cote D\'Ivoire","CIV","CI","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("53","Croatia","HRV","HR","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("54","Cuba","CUB","CU","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("55","Cyprus","CYP","CY","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("56","Czech Republic","CZE","CZ","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("57","Denmark","DNK","DK","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("58","Djibouti","DJI","DJ","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("59","Dominica","DMA","DM","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("60","Dominican Republic","DOM","DO","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("61","East Timor","TMP","TP","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("62","Ecuador","ECU","EC","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("63","Egypt","EGY","EG","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("64","El Salvador","SLV","SV","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("65","Equatorial Guinea","GNQ","GQ","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("66","Eritrea","ERI","ER","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("67","Estonia","EST","EE","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("68","Ethiopia","ETH","ET","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("69","Falkland Islands (Malvinas)","FLK","FK","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("70","Faroe Islands","FRO","FO","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("71","Fiji","FJI","FJ","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("72","Finland","FIN","FI","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("73","France","FRA","FR","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("74","France, Metropolitan","FXX","FX","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("75","French Guiana","GUF","GF","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("76","French Polynesia","PYF","PF","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("77","French Southern Territories","ATF","TF","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("78","Gabon","GAB","GA","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("79","Gambia","GMB","GM","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("80","Georgia","GEO","GE","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("81","Germany","DEU","DE","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("82","Ghana","GHA","GH","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("83","Gibraltar","GIB","GI","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("84","Greece","GRC","GR","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("85","Greenland","GRL","GL","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("86","Grenada","GRD","GD","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("87","Guadeloupe","GLP","GP","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("88","Guam","GUM","GU","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("89","Guatemala","GTM","GT","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("90","Guinea","GIN","GN","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("91","Guinea-bissau","GNB","GW","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("92","Guyana","GUY","GY","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("93","Haiti","HTI","HT","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("94","Heard and Mc Donald Islands","HMD","HM","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("95","Honduras","HND","HN","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("96","Hong Kong","HKG","HK","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("97","Hungary","HUN","HU","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("98","Iceland","ISL","IS","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("99","India","IND","IN","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("100","Indonesia","IDN","ID","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("101","Iran (Islamic Republic of)","IRN","IR","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("102","Iraq","IRQ","IQ","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("103","Ireland","IRL","IE","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("104","Israel","ISR","IL","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("105","Italy","ITA","IT","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("106","Jamaica","JAM","JM","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("107","Japan","JPN","JP","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("108","Jordan","JOR","JO","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("109","Kazakhstan","KAZ","KZ","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("110","Kenya","KEN","KE","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("111","Kiribati","KIR","KI","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("112","Korea, Democratic Republic of","PRK","KP","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("113","Korea, Republic of","KOR","KR","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("114","Kuwait","KWT","KW","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("115","Kyrgyzstan","KGZ","KG","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("116","Lao People\'s Democratic Republic","LAO","LA","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("117","Latvia","LVA","LV","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("118","Lebanon","LBN","LB","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("119","Lesotho","LSO","LS","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("120","Liberia","LBR","LR","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("121","Libyan Arab Jamahiriya","LBY","LY","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("122","Liechtenstein","LIE","LI","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("123","Lithuania","LTU","LT","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("124","Luxembourg","LUX","LU","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("125","Macau","MAC","MO","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("126","Macedonia","MKD","MK","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("127","Madagascar","MDG","MG","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("128","Malawi","MWI","MW","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("129","Malaysia","MYS","MY","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("130","Maldives","MDV","MV","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("131","Mali","MLI","ML","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("132","Malta","MLT","MT","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("133","Marshall Islands","MHL","MH","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("134","Martinique","MTQ","MQ","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("135","Mauritania","MRT","MR","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("136","Mauritius","MUS","MU","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("137","Mayotte","MYT","YT","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("138","Mexico","MEX","MX","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("139","Micronesia, Federated States of","FSM","FM","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("140","Moldova, Republic of","MDA","MD","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("141","Monaco","MCO","MC","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("142","Mongolia","MNG","MN","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("143","Montserrat","MSR","MS","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("144","Morocco","MAR","MA","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("145","Mozambique","MOZ","MZ","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("146","Myanmar","MMR","MM","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("147","Namibia","NAM","NA","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("148","Nauru","NRU","NR","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("149","Nepal","NPL","NP","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("150","Netherlands","NLD","NL","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("151","Netherlands Antilles","ANT","AN","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("152","New Caledonia","NCL","NC","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("153","New Zealand","NZL","NZ","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("154","Nicaragua","NIC","NI","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("155","Niger","NER","NE","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("156","Nigeria","NGA","NG","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("157","Niue","NIU","NU","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("158","Norfolk Island","NFK","NF","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("159","Northern Mariana Islands","MNP","MP","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("160","Norway","NOR","NO","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("161","Oman","OMN","OM","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("162","Pakistan","PAK","PK","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("163","Palau","PLW","PW","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("164","Panama","PAN","PA","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("165","Papua New Guinea","PNG","PG","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("166","Paraguay","PRY","PY","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("167","Peru","PER","PE","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("168","Philippines","PHL","PH","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("169","Pitcairn","PCN","PN","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("170","Poland","POL","PL","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("171","Portugal","PRT","PT","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("172","Puerto Rico","PRI","PR","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("173","Qatar","QAT","QA","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("174","Reunion","REU","RE","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("175","Romania","ROM","RO","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("176","Russian Federation","RUS","RU","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("177","Rwanda","RWA","RW","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("178","Saint Kitts and Nevis","KNA","KN","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("179","Saint Lucia","LCA","LC","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("180","Saint Vincent and the Grenadines","VCT","VC","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("181","Samoa","WSM","WS","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("182","San Marino","SMR","SM","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("183","Sao Tome and Principe","STP","ST","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("184","Saudi Arabia","SAU","SA","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("185","Senegal","SEN","SN","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("186","Seychelles","SYC","SC","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("187","Sierra Leone","SLE","SL","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("188","Singapore","SGP","SG","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("189","Slovakia (Slovak Republic)","SVK","SK","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("190","Slovenia","SVN","SI","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("191","Solomon Islands","SLB","SB","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("192","Somalia","SOM","SO","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("193","South Africa","ZAF","ZA","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("194","South Georgia and the South Sandwich Islands","SGS","GS","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("195","Spain","ESP","ES","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("196","Sri Lanka","LKA","LK","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("197","St. Helena","SHN","SH","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("198","St. Pierre and Miquelon","SPM","PM","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("199","Sudan","SDN","SD","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("200","Suriname","SUR","SR","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("201","Svalbard and Jan Mayen Islands","SJM","SJ","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("202","Swaziland","SWZ","SZ","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("203","Sweden","SWE","SE","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("204","Switzerland","CHE","CH","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("205","Syrian Arab Republic","SYR","SY","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("206","Taiwan","TWN","TW","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("207","Tajikistan","TJK","TJ","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("208","Tanzania, United Republic of","TZA","TZ","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("209","Thailand","THA","TH","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("210","Togo","TGO","TG","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("211","Tokelau","TKL","TK","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("212","Tonga","TON","TO","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("213","Trinidad and Tobago","TTO","TT","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("214","Tunisia","TUN","TN","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("215","Turkey","TUR","TR","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("216","Turkmenistan","TKM","TM","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("217","Turks and Caicos Islands","TCA","TC","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("218","Tuvalu","TUV","TV","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("219","Uganda","UGA","UG","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("220","Ukraine","UKR","UA","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("221","United Arab Emirates","ARE","AE","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("222","United Kingdom","GBR","GB","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("223","United States","USA","US","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("224","United States Minor Outlying Islands","UMI","UM","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("225","Uruguay","URY","UY","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("226","Uzbekistan","UZB","UZ","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("227","Vanuatu","VUT","VU","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("228","Vatican City State (Holy See)","VAT","VA","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("229","Venezuela","VEN","VE","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("230","Viet Nam","VNM","VN","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("231","Virgin Islands (British)","VGB","VG","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("232","Virgin Islands (U.S.)","VIR","VI","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("233","Wallis and Futuna Islands","WLF","WF","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("234","Western Sahara","ESH","EH","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("235","Yemen","YEM","YE","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("236","Serbia","SRB","RS","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("237","The Democratic Republic of Congo","DRC","DC","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("238","Zambia","ZMB","ZM","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("239","Zimbabwe","ZWE","ZW","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("240","East Timor","XET","XE","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("241","Jersey","XJE","XJ","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("242","St. Barthelemy","XSB","XB","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("243","St. Eustatius","XSE","XU","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("244","Canary Islands","XCA","XC","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("245","Montenegro","MNE","ME","1");
INSERT IGNORE INTO `#__ticketstation_country` VALUES("249","Afghanistan","AFG","AF","1");

INSERT IGNORE INTO `#__ticketstation_templates` VALUES("1","sending Tickets after successful Payment","<p>Hi {firstname},</p><p>Thank you for your purchase!<br />Your order number is <strong>{ordercode}</strong>.</p><p>Please find your tickets attached to this e-mail.<br />Please don't hesitate to contact us in case of any questions.</p><p>Kind regards,</p><p>{company_name}<br />{company_website}</p>","Here are your tickets!");
INSERT IGNORE INTO `#__ticketstation_templates` VALUES("2","resending Tickets","<p>Hi {firstname},</p><p>We are hereby sending you the tickets you ordered once again.<br />Please don't hesitate to contact us in case of any questions.</p><p>Kind regards,</p><p>{company_name}<br />{company_website}</p>","Here are your tickets!");
INSERT IGNORE INTO `#__ticketstation_templates` VALUES("3","Sending Payment Link","<p>Hi {firstname},</p><p>We are sending you the payment link for the tickets you ordered:</p><p>{paymentlink}</p><p>Click on the link (or copy and paste it into your browser) to make your payment.<br/>After successful payment, the tickets will be sent directly to this email address.</p><p>Please don't hesitate to contact us in case of any questions.</p><p>Kind regards,</p><p>{company_name}<br />{company_website}</p>","Payment link for your tickets");
INSERT IGNORE INTO `#__ticketstation_templates` VALUES("4","Waiting list confirmation","<p>Hi {firstname},</p><p>You are on the waiting list for:</p><p>{orderlist}</p><p>Please confirm your spot on this waiting list. As soon as tickets become available, you will receive a separate email with a payment link — we cannot guarantee your spot on the waiting list without confirmation.</p><p>{confirmationlink}</p><p>Please don't hesitate to contact us in case of any questions.</p><p>Kind regards,</p><p>{company_name}<br />{company_website}</p>","Confirm your spot on the waiting list");
INSERT IGNORE INTO `#__ticketstation_templates` VALUES("5","Invoice","<p>Hi {firstname},</p><p>Attached you will find the invoice for your order <strong>{ordercode}</strong>.</p><p>Invoice number: {invoice_id}<br />Amount: {price}</p><p>Please don't hesitate to contact us in case of any questions.</p><p>Kind regards,</p><p>{company_name}<br />{company_website}</p>","Invoice for your ordered tickets");
INSERT IGNORE INTO `#__ticketstation_templates` VALUES("6","Waiting list payment link","<p>Hi {firstname},</p><p>Good news: tickets have become available for {eventname}, which you were waiting for:</p><p>{orderlist}</p><p>Use the link below to pay within {removal_days} days; after that the link expires and the tickets are released again:</p><p>{paymentlink}</p><p>After successful payment, the tickets will be sent directly to this email address.</p><p>Please don't hesitate to contact us in case of any questions.</p><p>Kind regards,</p><p>{company_name}<br />{company_website}</p>","Tickets are available: payment link");
INSERT IGNORE INTO `#__ticketstation_templates` VALUES("7","Message to buyers of an event","<p>Hi {firstname},</p><p>{message}</p><p>Your order: <strong>{ordercode}</strong></p><p>{orderlist}</p><p>Please don't hesitate to contact us in case of any questions.</p><p>Kind regards,</p><p>{company_name}<br />{company_website}</p>","{subject}");
INSERT IGNORE INTO `#__ticketstation_templates` VALUES("8","Event reminder","<p>Hi {firstname},</p><p>This is a reminder that you have tickets for <strong>{eventname}</strong>.</p><p>When: {eventdate}, {eventtime}<br />Doors open: {doorsopen}<br />Where: {location}</p><p>{orderlist}</p><p>The tickets were sent to you earlier. Can't find them? Request them again here: <a href=\"{ticketlink}\">{ticketlink}</a>. The attached calendar file adds the event to your calendar.</p><p>We look forward to seeing you!</p><p>Kind regards,</p><p>{company_name}<br />{company_website}</p>","Reminder: {eventname}");
INSERT IGNORE INTO `#__ticketstation_templates` VALUES("9","Link to My orders","<p>Hi {firstname},</p><p>Use the link below to see your orders: download your tickets or get your invoice. The link works for {minutes} minutes.</p><p>{portallink}</p><p>Did you not ask for this? Then you can ignore this mail; nothing changes.</p><p>Kind regards,</p><p>{company_name}<br />{company_website}</p>","Your orders");
INSERT IGNORE INTO `#__ticketstation_templates` VALUES("10","Tickets shared by a customer","<p>Hi,</p><p>{sendername} has shared tickets with you:</p><p>{ticketlist}</p>{note}<p>The tickets are attached to this mail as a PDF file. Show the QR code on the ticket at the entrance.</p><p>Kind regards,</p><p>{company_name}<br />{company_website}</p>","{sendername} has shared tickets with you");


INSERT IGNORE INTO `#__ticketstation_config` (`configid`, `show_secondaddress`, `show_thirdaddress`, `valuta`, `payments_on`, `transactioncosts`, `priceformat`, `persending`, `dateformat`, `transcosts`, `variable_transcosts`, `man_payment`, `show_cancel`, `show_available_tickets`, `show_quantity_eventlist`, `show_price_eventlist`, `companyname`, `address1`, `address2`, `zipcode`, `city`, `state`, `phone`, `fax`, `email`, `website`, `company_logo`, `qr_width`, `removal_days`, `send_profile_mail`, `tos_tpl`, `use_automatic_login`, `remove_unfinished`, `removal_hours`, `pro_installed`, `show_mailchimp_signup`, `show_country`, `show_salutation`, `show_address`, `show_city`, `show_birthday`, `auto_username`, `activation_email`, `load_bootstrap`, `load_bootstrap_tpl`, `use_coupons`, `load_jquery`, `show_remark_field`, `show_waitinglist`, `eventname_position`, `ordernumber_position`, `orderdate_position`, `show_mailchimps`, `address_format_client`, `address_format_company`, `show_zipcode`, `show_phone`, `send_invoice`, `invoice_prefix`, `time_format`, `next_ordercode`, `show_venue`, `show_venue_address`, `show_venue_description`, `show_venue_website`, `show_jsonld`, `reminder_on`, `reminder_hours`, `from_name`, `from_email`, `terms_url`, `privacy_url`, `send_tickets_directly`, `wallet_apple`, `wallet_apple_cert`, `wallet_apple_key`, `wallet_apple_pending_key`, `wallet_google`, `wallet_google_issuer_id`, `wallet_google_key`, `wallet_google_updates`, `wallet_apple_updates`, `wallet_logo`, `wallet_bg_color`, `wallet_fg_color`, `price_decimals`, `price_decimal_sep`, `price_thousands_sep`, `price_symbol_after`, `payment_provider`, `payment_currency`, `test_mode`, `checkout_field_map`, `checkout_layout`, `invoice_counter_live`, `invoice_counter_test`) VALUES(
"1",
"0",
"0",
"€",
"0",
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
"250",
"1",
"0",
"13",
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
"0",
"1",
"0",
"2",
"0",
"0",
"10-100",
"10-10",
"55-140",
"0",
"%%SALUTATION%% %%FULLNAME%% \n%%ADDRESS1%% \n%%ZIPCODE%% %%CITY%% \n%%COUNTRY_FULL%% (%%COUNTRY_2D%%)",
"%%COMPANY_NAME%% \n%%ADDRESS1%% \n%%ZIPCODE%% %%CITY%% \n%%EMAIL%% \n%%WEBSITE%%",
"0",
"1",
"0",
"I-",
"H:i",
"",
"1",
"0",
"0",
"0",
"1",
"0",
"24",
"",
"",
"",
"",
"1",
"0",
NULL,
NULL,
NULL,
"0",
"",
NULL,
"0",
"0",
"",
"#1f2937",
"#ffffff",
"2",
",",
"",
"0",
"mollie",
"EUR",
"0",
"",
"0",
"0",
"0");
