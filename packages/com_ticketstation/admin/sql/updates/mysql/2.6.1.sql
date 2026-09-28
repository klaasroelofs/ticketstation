-- Seats taken without an order (booked = 1, orderid = 0) that are not marked blocked. They
-- are blocked seats from before 2.5.0, which 2.5.0.1.sql already converted in the same way, or
-- claims whose order was never written. None of them is a sale; the seat plan editor treated
-- them as sold and locked them. They become blocked seats, which can be freed in the editor.
UPDATE `#__ticketstation_seatplancoords` SET `blocked` = 1 WHERE `booked` = 1 AND `orderid` = 0 AND `blocked` = 0;
