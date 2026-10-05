-- Mail to the buyers of an event: a message from the admin (template 7) and an automatic reminder
-- shortly before the event (template 8).
INSERT IGNORE INTO `#__ticketstation_templates` VALUES("7","Message to buyers of an event","<p>Hi {firstname},</p><p>{message}</p><p>Your order: <strong>{ordercode}</strong></p><p>{orderlist}</p><p>Please don't hesitate to contact us in case of any questions.</p><p>Kind regards,</p><p>{company_name}<br />{company_website}</p>","{subject}");
INSERT IGNORE INTO `#__ticketstation_templates` VALUES("8","Event reminder","<p>Hi {firstname},</p><p>This is a reminder that you have tickets for <strong>{eventname}</strong>.</p><p>When: {eventdate}, {eventtime}<br />Doors open: {doorsopen}<br />Where: {location}</p><p>{orderlist}</p><p>The tickets were sent to you earlier. Can't find them? Request them again here: <a href=\"{ticketlink}\">{ticketlink}</a>. The attached calendar file adds the event to your calendar.</p><p>We look forward to seeing you!</p><p>Kind regards,</p><p>{company_name}<br />{company_website}</p>","Reminder: {eventname}");

-- Which orders got which mail about an event. The reminder is sent once per order, event and
-- start time.
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

-- The reminder: off by default, sent this many hours before the start.
ALTER TABLE `#__ticketstation_config` ADD COLUMN `reminder_on` tinyint(1) NOT NULL DEFAULT 0 /** CAN FAIL **/;
ALTER TABLE `#__ticketstation_config` ADD COLUMN `reminder_hours` smallint(4) NOT NULL DEFAULT 24 /** CAN FAIL **/;
