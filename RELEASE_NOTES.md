# [DoliSIRH] [23.0.0] - Tableau de bord enrichi - Robustesse PHP 8 - Filtre temps recherche

Description : Cette version durcit le code face à PHP 8 (corrections de fatals sur les hooks, triggers et timespent), enrichit le tableau de bord avec des noms sur les graphes, ajoute la conf `AllowDifferenceBetweenPassedAndWorkingHours` sur la timesheet, et corrige le filtre SQL des listes pour utiliser `forgeSQLFromUniversalSearchCriteria`. Saut de version 1.5.1 → 23.0.0 pour aligner sur la famille Saturne / Dolibarr 23.

## Nouvelles fonctionnalités et innovations

### Timesheet — différentiel heures travaillées

* Nouvelle conf `AllowDifferenceBetweenPassedAndWorkingHours` pour contrôler le différentiel entre heures pointées et heures travaillées.

<!-- 📸 Ajouter une screenshot ici -->

### Tableau de bord

* Noms ajoutés sur les graphes et widgets du dashboard (lisibilité).

---

## Améliorations & corrections

### Robustesse PHP 8 / fatals

* `[Triggers]` : warning PHP 8 corrigé.
* `[Triggers]` : fatal `require` sur fonction manquante corrigé.
* `[Hook]` : fatal sur fetch d'objet ticket corrigé.
* `[Hook]` : fatal de permission sur `saturne_show_documents` corrigé.
* `[Hook]` : `parseInt` manquant et check int + cast ajoutés.
* `[Timespent]` : « A non-numeric value encountered » corrigé.
* `[RecurringInvoiceStats]` : fatal corrigé.
* `[WorkingHours]` : `GETPOST` correctement utilisé.
* `[Hook]` : mise à jour du statut de tâche.

### TimeSheet

* Bouton de verrouillage de la feuille de temps : condition cassée corrigée (#656).
* `qty` manquant et `fk_user_assign` ajoutés.
* Fonction `getLabelOfUnit` manquante restaurée.

### TimespentRange

* `name` retiré des inputs pour ne pas envoyer d'inputs inutiles dans le POST.
* Code mort retiré.
* `search_user` correctement réinitialisé.

### Filtres SQL listes

* `[Index]` : passage à `forgeSQLFromUniversalSearchCriteria` (#635).
* `[Mod]` : passage à `forgeSQLFromUniversalSearchCriteria` (#633).

### Module / configuration

* Fix temporaire des extrafields task sur ticket (#643).
* Plusieurs passes de nettoyage des classes (`[Class] core: clean code`).

## Comparaison des versions [1.5.1](https://github.com/Evarisk/DoliSIRH/compare/1.5.1...23.0.0) et 23.0.0

* [#656] [TimeSheet] fix: broken lock button condition [`628dcdf`](https://github.com/Evarisk/DoliSIRH/commit/628dcdf)
* [#643] [ModDolisirh] fix: temporary fix of extrafield task on ticket [`a4e7e3f`](https://github.com/Evarisk/DoliSIRH/commit/a4e7e3f)
* [#635] [Index] fix: change sql filter for forgeSQLFromUniversalSearchCriteria [`bb756d1`](https://github.com/Evarisk/DoliSIRH/commit/bb756d1)
* [#633] [Mod] fix: change sql filter for forgeSQLFromUniversalSearchCriteria [`f3b68a4`](https://github.com/Evarisk/DoliSIRH/commit/f3b68a4)
* [#627] [TimeSheet] fix: missing function getLabelOfUnit [`069be30`](https://github.com/Evarisk/DoliSIRH/commit/069be30)
* [#626] [TimeSheet] add: conf AllowDifferenceBetweenPassedAndWorkingHours [`b9bfb1d`](https://github.com/Evarisk/DoliSIRH/commit/b9bfb1d)
* [#616] [Dashboard] add: name on graph and widgets [`6fe5a0f`](https://github.com/Evarisk/DoliSIRH/commit/6fe5a0f)
* [#613] [TimeSheet] fix: missing qty check and fk_user_assign [`b9669fe`](https://github.com/Evarisk/DoliSIRH/commit/b9669fe)
* [#611] [Hook] fix: missing parseInt and check int + cast [`2405295`](https://github.com/Evarisk/DoliSIRH/commit/2405295)
* [#609] [RecurringInvoiceStats] fix: fatal [`120c03e`](https://github.com/Evarisk/DoliSIRH/commit/120c03e)
* [#584] [TimespentRange] fix: remove input name, dead code, search_user reset [`f745e38`](https://github.com/Evarisk/DoliSIRH/commit/f745e38) [`9131873`](https://github.com/Evarisk/DoliSIRH/commit/9131873) [`59ab0ef`](https://github.com/Evarisk/DoliSIRH/commit/59ab0ef)
* [Triggers] fix: PHP 8 warning, fatal on require, status update [`261a17e`](https://github.com/Evarisk/DoliSIRH/commit/261a17e) [`22bdf36`](https://github.com/Evarisk/DoliSIRH/commit/22bdf36) [`3d8cdff`](https://github.com/Evarisk/DoliSIRH/commit/3d8cdff)
* [Hook] fix: fatal fetch ticket, permission saturne_show_documents [`a3a8c5a`](https://github.com/Evarisk/DoliSIRH/commit/a3a8c5a) [`096d696`](https://github.com/Evarisk/DoliSIRH/commit/096d696)
* [Timespent] fix: non-numeric value [`b3d65b1`](https://github.com/Evarisk/DoliSIRH/commit/b3d65b1)
* [WorkingHours] fix: GETPOST [`b0f05b4`](https://github.com/Evarisk/DoliSIRH/commit/b0f05b4)
* [Class] core: clean code (4 commits) [`9dc1534`](https://github.com/Evarisk/DoliSIRH/commit/9dc1534) [`eaefb4d`](https://github.com/Evarisk/DoliSIRH/commit/eaefb4d) [`1b41841`](https://github.com/Evarisk/DoliSIRH/commit/1b41841) [`6d94658`](https://github.com/Evarisk/DoliSIRH/commit/6d94658)
