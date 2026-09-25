-- Adds per-part settings for the venue block on both event (ticket) pages: address, description and website.
-- Defaults to 0 so existing sites don't suddenly show extra venue information.
ALTER TABLE `#__ticketstation_config` ADD COLUMN `show_venue_address` tinyint(1) NOT NULL DEFAULT 0;
ALTER TABLE `#__ticketstation_config` ADD COLUMN `show_venue_description` tinyint(1) NOT NULL DEFAULT 0;
ALTER TABLE `#__ticketstation_config` ADD COLUMN `show_venue_website` tinyint(1) NOT NULL DEFAULT 0;

-- Removes config columns that were never read anywhere in the component (leftovers from the old Ticketmaster code).
ALTER TABLE `#__ticketstation_config` DROP COLUMN `show_venuebox`;
ALTER TABLE `#__ticketstation_config` DROP COLUMN `show_google_maps`;
ALTER TABLE `#__ticketstation_config` DROP COLUMN `google_maps_key`;
