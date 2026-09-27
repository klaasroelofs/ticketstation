-- Transaction costs per order (see OrderTotals). Until now they were worked out again from the
-- Configuration every time an order was shown, mailed, invoiced or paid, so changing the
-- Configuration also changed orders that were already placed. From now on every order keeps
-- the kind (0 = fixed, 1 = variable, 2 = none) and the amount or percentage it was placed with.
CREATE TABLE IF NOT EXISTS `#__ticketstation_ordertotals` (
  `ordercode` varchar(50) NOT NULL,
  `fee_type` tinyint(1) NOT NULL DEFAULT '0',
  `fee_rate` decimal(10,4) NOT NULL DEFAULT '0.0000',
  `captured` datetime NOT NULL,
  PRIMARY KEY (`ordercode`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

-- Existing orders, most reliable source first; INSERT IGNORE keeps the first one found.

-- 1. Paid (or refunded) orders paid through Mollie: the transaction costs are what the
--    customer paid minus the tickets and the discount. They are kept as a fixed amount, so the
--    order adds up to exactly that payment again.
INSERT IGNORE INTO `#__ticketstation_ordertotals` (`ordercode`, `fee_type`, `fee_rate`, `captured`)
SELECT p.`ordercode`,
       IF(p.`fees` > 0.004, 0, 2),
       IF(p.`fees` > 0.004, p.`fees`, 0),
       UTC_TIMESTAMP()
FROM (
    SELECT o.`ordercode`,
           ROUND(tr.`amount` - (o.`tickets` - CASE
               WHEN COALESCE(o.`coupon`, '') = '' THEN 0
               WHEN o.`discount_type` IS NOT NULL
                   THEN IF(o.`discount_type` = 1, ROUND(o.`tickets` * o.`discount_amount` / 100, 2), LEAST(o.`discount_amount`, o.`tickets`))
               WHEN c.`coupon_type` IS NOT NULL
                   THEN IF(c.`coupon_type` = 1, ROUND(o.`tickets` * c.`coupon_discount` / 100, 2), LEAST(c.`coupon_discount`, o.`tickets`))
               ELSE o.`row_discount`
           END), 2) AS `fees`
    FROM (
        SELECT `ordercode`,
               ROUND(SUM(`price`), 2) AS `tickets`,
               MAX(`coupon`) AS `coupon`,
               MAX(`discount_type`) AS `discount_type`,
               MAX(`discount_amount`) AS `discount_amount`,
               ROUND(SUM(COALESCE(`discount`, 0)), 2) AS `row_discount`
        FROM `#__ticketstation_orders`
        WHERE `paid` IN (1, 2)
        GROUP BY `ordercode`
    ) AS o
    JOIN (
        SELECT `orderid`, MAX(`pid`) AS `pid`
        FROM `#__ticketstation_transactions`
        GROUP BY `orderid`
    ) AS lt ON lt.`orderid` = o.`ordercode`
    JOIN `#__ticketstation_transactions` AS tr ON tr.`pid` = lt.`pid`
    LEFT JOIN `#__ticketstation_coupons` AS c ON c.`coupon_code` = o.`coupon`
) AS p;

-- 2. Other paid (or refunded) orders did not go through Mollie: bypass mode, free orders, and
--    orders completed in the Box Office or saved as a paid reservation. No transaction costs.
INSERT IGNORE INTO `#__ticketstation_ordertotals` (`ordercode`, `fee_type`, `fee_rate`, `captured`)
SELECT DISTINCT `ordercode`, 2, 0, UTC_TIMESTAMP()
FROM `#__ticketstation_orders`
WHERE `paid` IN (1, 2);

-- 3. Open orders that already have an invoice keep the transaction costs on that invoice.
INSERT IGNORE INTO `#__ticketstation_ordertotals` (`ordercode`, `fee_type`, `fee_rate`, `captured`)
SELECT i.`ordercode`, IF(i.`fees` > 0, 0, 2), i.`fees`, UTC_TIMESTAMP()
FROM `#__ticketstation_invoices` AS i
WHERE i.`invoiceid` = (SELECT MAX(i2.`invoiceid`) FROM `#__ticketstation_invoices` AS i2 WHERE i2.`ordercode` = i.`ordercode`)
  AND EXISTS (SELECT 1 FROM `#__ticketstation_orders` AS o WHERE o.`ordercode` = i.`ordercode`);

-- 4. Open backend reservations: never transaction costs. They are recognised by who created
--    them (a logged-in user rather than the website) and by never having started a payment.
INSERT IGNORE INTO `#__ticketstation_ordertotals` (`ordercode`, `fee_type`, `fee_rate`, `captured`)
SELECT DISTINCT o.`ordercode`, 2, 0, UTC_TIMESTAMP()
FROM `#__ticketstation_orders` AS o
WHERE o.`userid` > 0
  AND NOT EXISTS (SELECT 1 FROM `#__ticketstation_transactions_temp` AS tt WHERE tt.`ordercode` = o.`ordercode`)
  AND (SELECT h.`actor` FROM `#__ticketstation_history` AS h
       WHERE h.`ordercode` = o.`ordercode` AND h.`event_type` = 'order_created'
       ORDER BY h.`created` DESC, h.`id` DESC LIMIT 1) <> 'Website';

-- 5. All other orders that went through checkout (they have a customer): the current
--    Configuration, which is what they were charged until now. Carts that haven't been through
--    checkout yet get their terms when they do.
INSERT IGNORE INTO `#__ticketstation_ordertotals` (`ordercode`, `fee_type`, `fee_rate`, `captured`)
SELECT DISTINCT o.`ordercode`,
       CASE WHEN c.`variable_transcosts` IN (0, 1) THEN c.`variable_transcosts` ELSE 2 END,
       CASE WHEN c.`variable_transcosts` = 1 THEN c.`transcosts` WHEN c.`variable_transcosts` = 0 THEN c.`transactioncosts` ELSE 0 END,
       UTC_TIMESTAMP()
FROM `#__ticketstation_orders` AS o
JOIN `#__ticketstation_config` AS c ON c.`configid` = 1
WHERE o.`userid` > 0;
