-- Switch for the schema.org event data (JSON-LD) on the ticket pages. On by default; a test or
-- staging site switches it off so its events don't reach search engines.
ALTER TABLE `#__ticketstation_config` ADD COLUMN `show_jsonld` tinyint(1) NOT NULL DEFAULT 1 /** CAN FAIL **/;
