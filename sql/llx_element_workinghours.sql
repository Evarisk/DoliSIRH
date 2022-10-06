-- Copyright (C) 2021 EOXIA <dev@eoxia.com>
--
-- This program is free software: you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation, either version 3 of the License, or
-- (at your option) any later version.
--
-- This program is distributed in the hope that it will be useful,
-- but WITHOUT ANY WARRANTY; without even the implied warranty of
-- MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
-- GNU General Public License for more details.
--
-- You should have received a copy of the GNU General Public License
-- along with this program.  If not, see https://www.gnu.org/licenses/.

CREATE TABLE llx_element_workinghours(
	rowid                  integer AUTO_INCREMENT PRIMARY KEY NOT NULL,
	entity                 integer DEFAULT 1 NOT NULL,
	date_creation          datetime NOT NULL,
	tms                    timestamp,
	status                 smallint,
	element_type           varchar(50),
	element_id             integer NOT NULL,
	schedule_monday        varchar(128),
	schedule_tuesday       varchar(128),
	schedule_wednesday     varchar(128),
	schedule_thursday      varchar(128),
	schedule_friday        varchar(128),
	schedule_saturday      varchar(128),
	schedule_sunday        varchar(128),
    workinghours_monday    integer,
    workinghours_tuesday   integer,
    workinghours_wednesday integer,
    workinghours_thursday  integer,
    workinghours_friday    integer,
    workinghours_saturday  integer,
    workinghours_sunday    integer,
	fk_user_creat          integer NOT NULL
) ENGINE=innodb;
