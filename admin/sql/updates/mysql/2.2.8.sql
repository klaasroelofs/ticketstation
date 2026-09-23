-- Sender name and address are now configured once, centrally, instead of per mail template
ALTER TABLE `#__ticketstation_config` ADD COLUMN `from_name` varchar(255) NOT NULL DEFAULT '';
ALTER TABLE `#__ticketstation_config` ADD COLUMN `from_email` varchar(255) NOT NULL DEFAULT '';

-- Carry over the sender of the ticket mail template, unless it still holds the install placeholder
UPDATE `#__ticketstation_config` AS c INNER JOIN `#__ticketstation_templates` AS t ON t.mailid = 1 SET c.from_name = t.from_name WHERE t.from_name NOT IN ('', 'Sender Name');
UPDATE `#__ticketstation_config` AS c INNER JOIN `#__ticketstation_templates` AS t ON t.mailid = 1 SET c.from_email = t.from_email WHERE t.from_email NOT IN ('', 'info@yourdomain.com');

ALTER TABLE `#__ticketstation_templates` DROP COLUMN `from_name`;
ALTER TABLE `#__ticketstation_templates` DROP COLUMN `from_email`;
