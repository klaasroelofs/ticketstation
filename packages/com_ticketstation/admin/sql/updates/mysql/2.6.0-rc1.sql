-- The new seat plan editor keeps the chart settings in the editor itself: the background image
-- now lives under images/ (the install script moves existing images), and the canvas, the image
-- position, the editor grid and the non-sellable shapes (stage, labels) are stored per chart.
ALTER TABLE `#__ticketstation_seatplansettings` ADD COLUMN `background_image` varchar(255) NOT NULL DEFAULT '' /** CAN FAIL **/;
ALTER TABLE `#__ticketstation_seatplansettings` ADD COLUMN `bg_offset_x` int(11) NOT NULL DEFAULT '0' /** CAN FAIL **/;
ALTER TABLE `#__ticketstation_seatplansettings` ADD COLUMN `bg_offset_y` int(11) NOT NULL DEFAULT '0' /** CAN FAIL **/;
ALTER TABLE `#__ticketstation_seatplansettings` ADD COLUMN `canvas_width` int(11) NOT NULL DEFAULT '0' /** CAN FAIL **/;
ALTER TABLE `#__ticketstation_seatplansettings` ADD COLUMN `canvas_height` int(11) NOT NULL DEFAULT '0' /** CAN FAIL **/;
ALTER TABLE `#__ticketstation_seatplansettings` ADD COLUMN `grid_size` int(11) NOT NULL DEFAULT '10' /** CAN FAIL **/;
ALTER TABLE `#__ticketstation_seatplansettings` ADD COLUMN `shapes` mediumtext NULL /** CAN FAIL **/;
ALTER TABLE `#__ticketstation_seatplansettings` MODIFY `seat_width` varchar(3) DEFAULT NULL;
ALTER TABLE `#__ticketstation_seatplansettings` MODIFY `seat_height` varchar(3) DEFAULT NULL;

-- The old charts drew their background image 30px below the top of the chart; keep existing
-- seats aligned with their image.
UPDATE `#__ticketstation_seatplansettings` SET `bg_offset_y` = 30;

-- Seat plan templates per venue: one or more layouts that can be loaded into a ticket's chart.
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
