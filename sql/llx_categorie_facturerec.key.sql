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

ALTER TABLE llx_categorie_facturerec ADD PRIMARY KEY pk_categorie_facturerec (fk_categorie, fk_facturerec);
ALTER TABLE llx_categorie_facturerec ADD INDEX idx_categorie_facturerec_fk_categorie (fk_categorie);
ALTER TABLE llx_categorie_facturerec ADD INDEX idx_categorie_facturerec_fk_facturerec (fk_facturerec);
ALTER TABLE llx_categorie_facturerec ADD CONSTRAINT fk_categorie_facturerec_categorie_rowid FOREIGN KEY (fk_categorie) REFERENCES llx_categorie (rowid);
ALTER TABLE llx_categorie_facturerec ADD CONSTRAINT fk_categorie_facturerec_facture_rec_rowid FOREIGN KEY (fk_facturerec) REFERENCES llx_facture_rec (rowid);
