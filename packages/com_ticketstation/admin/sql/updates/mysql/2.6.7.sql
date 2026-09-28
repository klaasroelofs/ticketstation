-- Deleting an order in the Box Office now deletes its history as well. Clear the history that
-- earlier deletions left behind: every ordercode without an order, except the orders the
-- ticketcleaner removed (latest entry 'order_removed_auto'), which stay in the Box Office as
-- "Removed" until they are deleted there. GROUP BY makes MySQL build the ghost list before it
-- deletes from the same table.
DELETE h FROM `#__ticketstation_history` AS h
LEFT JOIN `#__ticketstation_orders` AS o ON o.`ordercode` = h.`ordercode`
LEFT JOIN (
  SELECT g.`ordercode` FROM `#__ticketstation_history` AS g
  WHERE g.`event_type` = 'order_removed_auto'
    AND NOT EXISTS (SELECT 1 FROM `#__ticketstation_history` AS g2
                    WHERE g2.`ordercode` = g.`ordercode` AND g2.`created` > g.`created`)
  GROUP BY g.`ordercode`
) AS ghost ON ghost.`ordercode` = h.`ordercode`
WHERE o.`ordercode` IS NULL
  AND ghost.`ordercode` IS NULL;
