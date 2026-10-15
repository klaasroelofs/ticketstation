-- Customer portal, tickets of an order sent or downloaded in part: a switch for sending tickets to
-- someone else (on by default), and the mail that person gets (template 10).
ALTER TABLE `#__ticketstation_config` ADD COLUMN `portal_share` tinyint(1) NOT NULL DEFAULT 1 /** CAN FAIL **/;

INSERT IGNORE INTO `#__ticketstation_templates` VALUES("10","Tickets shared by a customer","<p>Hi,</p><p>{sendername} has shared tickets with you:</p><p>{ticketlist}</p>{note}<p>The tickets are attached to this mail as a PDF file. Show the QR code on the ticket at the entrance.</p><p>Kind regards,</p><p>{company_name}<br />{company_website}</p>","{sendername} has shared tickets with you");

-- The first text of the link mail (template 9) mentioned the wallet, which not every site has on. Only an
-- unchanged text is replaced.
UPDATE `#__ticketstation_templates` SET `mailbody` = REPLACE(`mailbody`, 'download your tickets, add them to your wallet or calendar, or get your invoice', 'download your tickets or get your invoice') WHERE `mailid` = 9;
