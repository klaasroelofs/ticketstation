-- Availability is now capacity minus order rows, so the running counter
-- (#__ticketstation_tickets.totaltickets) is no longer read or written. The column stays
-- until a later version, so a faulty 2.5.x needs no data repair.
--
-- Whether child tickets share their parent's capacity is now chosen once, on the parent
-- (its counter_choice: 0 = shared, 1 = per child ticket), instead of per child. A parent
-- whose child tickets all had their own capacity keeps that; any other parent with child
-- tickets (including a mix) becomes shared.
UPDATE `#__ticketstation_tickets` AS `p`
INNER JOIN (
  SELECT `parent`, MIN(`counter_choice`) AS `mode`
  FROM `#__ticketstation_tickets`
  WHERE `parent` > 0
  GROUP BY `parent`
) AS `c` ON `c`.`parent` = `p`.`ticketid`
SET `p`.`counter_choice` = `c`.`mode`;

-- Availability counts order rows per ticket.
ALTER TABLE `#__ticketstation_orders` ADD INDEX `idx_ticketid` (`ticketid`);
