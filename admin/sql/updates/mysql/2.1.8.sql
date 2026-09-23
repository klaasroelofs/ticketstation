-- Adds a setting to show/hide the venue on the upcoming events list and both event (ticket) pages.
-- Defaults to 1 so existing sites keep showing the venue as before.
ALTER TABLE `#__ticketstation_config` ADD COLUMN `show_venue` tinyint(1) NOT NULL DEFAULT 1;
