-- Copyright (C) 2022 EOXIA <dev@eoxia.com>
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

ALTER TABLE llx_dolisirh_object_signature ADD INDEX idx_dolisirh_object_signature_rowid (rowid);
ALTER TABLE llx_dolisirh_object_signature  ADD CONSTRAINT llx_dolisirh_object_signature_fk_object FOREIGN KEY (fk_object) REFERENCES llx_dolisirh_object(rowid);
