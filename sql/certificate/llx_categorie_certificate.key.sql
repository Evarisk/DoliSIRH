-- Copyright (C) 2021-2023 EVARISK <technique@evarisk.com>
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

ALTER TABLE llx_categorie_certificate ADD PRIMARY KEY pk_categorie_certificate (fk_categorie, fk_certificate);
ALTER TABLE llx_categorie_certificate ADD INDEX idx_categorie_certificate_fk_categorie (fk_categorie);
ALTER TABLE llx_categorie_certificate ADD INDEX idx_categorie_certificate_fk_certificate (fk_certificate);
ALTER TABLE llx_categorie_certificate ADD CONSTRAINT fk_categorie_certificate_categorie_rowid FOREIGN KEY (fk_categorie) REFERENCES llx_categorie (rowid);
ALTER TABLE llx_categorie_certificate ADD CONSTRAINT fk_categorie_certificate_dolisirh_certificate_rowid FOREIGN KEY (fk_certificate) REFERENCES llx_dolisirh_certificate (rowid);
