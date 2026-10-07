-- How the customer orders: 0 = the classic steps (cart, details, payment), 1 = the combined page
-- (the cart, the details and the payment on one page).
ALTER TABLE `#__ticketstation_config` ADD COLUMN `checkout_layout` tinyint(1) NOT NULL DEFAULT 0 /** CAN FAIL **/;
