-- The Multi Seat switch is replaced by a per-seat rule: a seat linked to a child ticket
-- (seatplancoords.parent > 0) is a section seat with that child's fixed price, any other
-- seat is a free seat where the customer picks a price category. Existing charts already
-- store seats that way, so no seat data changes. seatplansettings.multi_seat is no longer
-- read and stays until a later version.
--
-- Seats a manager marked as taken without an order were blocked seats in practice; they now
-- get an explicit flag, so they don't count as capacity. booked stays 1 for them, which
-- keeps them unclickable.
ALTER TABLE `#__ticketstation_seatplancoords`
  ENGINE=InnoDB,
  CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

ALTER TABLE `#__ticketstation_seatplancoords`
  ADD COLUMN `blocked` tinyint(1) NOT NULL DEFAULT 0 AFTER `booked`,
  ADD INDEX `idx_ticketid` (`ticketid`),
  ADD INDEX `idx_parent` (`parent`),
  ADD INDEX `idx_orderid` (`orderid`);

UPDATE `#__ticketstation_seatplancoords` SET `blocked` = 1 WHERE `booked` = 1 AND `orderid` = 0;
