-- A customer has one name field now: clients.name holds the full name. The first name moves in
-- front of the last name; the column firstname stays, unused, until it is removed later.
UPDATE `#__ticketstation_clients`
   SET `name` = TRIM(CONCAT_WS(' ', NULLIF(TRIM(`firstname`), ''), NULLIF(TRIM(`name`), '')))
 WHERE `firstname` IS NOT NULL AND TRIM(`firstname`) <> '';

-- {name} is the full name now, so a mail that greeted "{firstname} {name}" would print the first
-- name twice.
UPDATE `#__ticketstation_templates`
   SET `mailbody` = REPLACE(REPLACE(`mailbody`, '{firstname} {name}', '{name}'), '%%FIRSTNAME%% %%NAME%%', '%%NAME%%');
