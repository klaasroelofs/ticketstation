-- A coupon can be limited to certain tickets (a parent ticket includes its child tickets). Empty
-- means the whole order, as before.
ALTER TABLE `#__ticketstation_coupons` ADD COLUMN `coupon_tickets` varchar(2000) NOT NULL DEFAULT '' /** CAN FAIL **/;

-- The tickets the coupon counted for when it was applied (child tickets included), kept with the
-- order like the rest of its terms. NULL or empty: the whole order.
ALTER TABLE `#__ticketstation_ordertotals` ADD COLUMN `coupon_tickets` text DEFAULT NULL /** CAN FAIL **/;
