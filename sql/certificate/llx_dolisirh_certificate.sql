-- Copyright (C) 2023 EVARISK <dev@evarisk.com>
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

CREATE TABLE llx_dolisirh_certificate(
	rowid         integer AUTO_INCREMENT PRIMARY KEY NOT NULL,
	ref           varchar(128) DEFAULT '(PROV)' NOT NULL,
    ref_ext       varchar(128),
    entity        integer DEFAULT 1 NOT NULL,
	label         varchar(255),
	description   text,
	note_public   text,
	note_private  text,
	date_creation datetime NOT NULL,
    date_start    datetime,
    date_end      datetime,
	tms           timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	import_key    varchar(14),
	last_main_doc varchar(255),
	model_pdf     varchar(255),
	status        integer NOT NULL,
	json          text,
	sha256        text,
	element_type  text,
	fk_element    integer,
	fk_product    integer,
    fk_societe    integer,
	fk_project    integer,
	fk_user_creat integer NOT NULL,
	fk_user_modif integer
) ENGINE=innodb;
