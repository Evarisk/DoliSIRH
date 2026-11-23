# [DoliSIRH] [23.2.1] - Les jours fériés comptent dans le temps attendu - Navette, saisie des temps et application mobile fiabilisées

Description : à savoir avant de mettre à jour, un jour férié en semaine compte désormais dans le temps attendu de la navette et dans son verrouillage, et se justifie en pointant la tâche « Jour férié » du projet RH. Le reste de la version corrige l'onglet Temps pointé, la saisie des temps et l'application mobile de pointage.

**Cette version demande Saturne 23.9.1 ou supérieur.**

## Améliorations & corrections

### ⚠️ Changement de règle : les jours fériés comptent dans le temps attendu et dans le verrouillage

**Ce qui change chez tous les utilisateurs à la mise à jour.** Un jour férié tombant en semaine compte désormais dans le temps attendu de la fiche navette, de l'onglet Temps pointé, de la page Navettes, de la saisie des temps, des tableaux de bord et des statistiques, **et dans le verrouillage de la navette** (bouton Verrouiller de la fiche et action de masse de la liste). C'était déjà la règle de la navette PDF et de l'application mobile : tous les écrans donnent maintenant le même total.

* Exemple : en novembre 2026 (le 11 tombe un mercredi), pour un salarié à 7 h par jour, la navette attend **147:00 sur 21 jours ouvrés**, au lieu de 140:00 sur 20 jours.
* Le jour férié se justifie en pointant la tâche **« Jour férié »** du projet RH. Tant qu'elle n'est pas pointée ce jour-là, la navette affiche un manque et **ne peut pas être verrouillée**, sauf si l'option « Autoriser la différence entre les heures travaillées et les heures consommées » est cochée.
* Le jour férié reste grisé dans les grilles de saisie.
* Une navette déjà verrouillée ne change pas.
* Sans tâche « Jour férié » dans le projet RH, rien ne change : le jour férié reste retiré du temps attendu, désormais partout. La navette PDF et l'application l'attendaient jusqu'ici quand même, ce qui laissait un manque qu'aucune tâche ne pouvait combler.
* Application mobile : un jour férié garde son temps attendu, comme sur la navette PDF. Sur l'onglet Semaine, un jour férié sans présence propose la durée restante sur la tâche « Jour férié » plutôt que sur la présence.

<img src="https://raw.githubusercontent.com/nicolas-eoxia/DoliSIRH/refs/heads/assets/release-23.2.1/navette-jour-ferie.png" alt="Onglet Temps pointé de la navette de novembre 2026 : colonne du mercredi 11/11 grisée, tâche Jour férié pointée 07:00 dans le bloc Absence, heures prévues 147:00, temps attendu 07:00 et écart 00:00 ce jour-là ; écart signé +01:00 en rouge le 10/11 et -01:00 en orange le 12/11">

### Navette et onglet Temps pointé

* **La fenêtre de saisie de l'onglet Temps pointé s'ouvre de nouveau** au clic dans une cellule : elle restait fermée.
* **Jours à venir** : l'onglet Temps pointé n'attend un jour à venir qu'une fois pointé. L'écart n'affiche plus -07:00 sur chaque jour qui reste dans le mois, ni un manque total qui n'existe pas encore. Les heures prévues gardent le mois entier, comme la fiche, la navette PDF et le verrouillage.
* **Écart signé** : la ligne Écart affiche +01:00 pour un excédent et -01:00 pour un manque ; elle donnait « 01:00 » dans les deux cas.
* **Couleurs de l'écart** : la bande des jours, la page Navettes et l'application suivent les couleurs choisies dans la configuration du module (temps dépassés, non dépassés ou non pointés, parfait) au lieu de couleurs fixes, qui étaient inversées. Un week-end pointé apparaît en dépassement, et la pastille de l'infobulle de la page Navettes est remise dans le bon sens.
* **Salarié sans responsable** : à la création de sa navette, un message prévient qu'elle ne sera signée que par le salarié, sauf à ajouter le responsable dans l'onglet Participants. Avant, la navette perdait sans rien dire la signature du responsable.
* L'événement de validation d'une navette ou d'une attestation porte la référence définitive au lieu de « (PROVn) ».
* Créer une navette sans description par un script, un import ou l'API ne provoque plus d'erreur fatale.
* À l'activation du module, les fichiers des navettes validées restés dans un dossier « (PROVn) » rejoignent le dossier de leur référence définitive (avec Saturne 23.9.1).

### Saisie des temps

* **Heure de début** : la fenêtre de saisie de l'onglet Temps pointé et de la page de saisie des temps lit l'heure dans le même fuseau que la fiche Temps consommé de Dolibarr. Avec le fuseau de l'utilisateur activé (`MAIN_TZUSERINPUTKEY`), un temps saisi à 09:00 n'est plus enregistré à 10:00 ; les jours de changement d'heure, plus de décalage d'une heure.
* **Événement de l'agenda vers temps passé** : sur Dolibarr 23, un événement lié à une tâche, case « Temps passé » cochée, crée de nouveau son temps passé, et à l'heure de l'événement (un rendez-vous de 10:00 était pointé à 11:00).
* Les filtres par catégorie de la saisie des temps, des listes de navettes, de temps passés et de factures n'écrivent plus d'avertissements dans le journal PHP pour un utilisateur sans droit sur les catégories.

### Application mobile de pointage

* **Fuseau horaire** : l'application lit et affiche les heures dans le même fuseau que Dolibarr. Quand le serveur n'est pas à l'heure du téléphone, un pointage saisi de 09:00 à 10:00 n'apparaît plus à 08:00 dans Dolibarr ; l'heure proposée, le jour courant et le temps écoulé d'un pointage en cours suivent. Sur un serveur à l'heure de Paris, rien ne change.
* **Sans horaire de travail**, l'application n'invente plus 7 h par jour : le temps attendu est de 0, comme sur la navette, et un message « Aucun horaire de travail renseigné » invite à les renseigner (lien vers la fiche des horaires pour qui en a le droit) ou à les demander au responsable.
* La bande des jours de l'onglet Pointer compte la présence RH comme la navette : un jour pointé en présence ne reste plus rouge à 00:00.

### Stabilité

* Plus d'erreur fatale sur les pages qui annulent un formulaire quand DoliSIRH est actif (par exemple la fiche d'une enveloppe DoliLetter).
* Le pictogramme de DoliSIRH s'affiche dans Configuration › Modules › Déclencheurs : c'était une image cassée.

## Testez les nouveautés maintenant

**Allez tester la navette et son onglet Temps pointé sur la démo : [https://demodoli.digirisk.com/](https://demodoli.digirisk.com/)**

## Comparaison des versions [23.2.0](https://github.com/Evarisk/DoliSIRH/compare/23.2.0...23.2.1) et 23.2.1

* [#841] [TimeSheet] fix: description vide par défaut, plus de fatale à la création d'une navette sans description [`ef657844`](https://github.com/Evarisk/DoliSIRH/commit/ef657844)
* [#838] [Trigger] fix: chargement des traductions rétabli (remplacé à tort avec le picto) [`85348312`](https://github.com/Evarisk/DoliSIRH/commit/85348312)
* [#837] [Mod] fix: fichiers des objets validés restés sous (PROVn) repris à l'activation depuis Accueil > Modules [`011020af`](https://github.com/Evarisk/DoliSIRH/commit/011020af)
* [#838] [Trigger] fix: picto dolisirh_color@dolisirh, l'image dolisirh@dolisirh n'existe pas [`51a6a28a`](https://github.com/Evarisk/DoliSIRH/commit/51a6a28a)
* [#835] [Hook] fix: doActions et formObjectOptions acceptent $action nul [`6302769c`](https://github.com/Evarisk/DoliSIRH/commit/6302769c)
* [#831] [TimeSheet] fix: la fenêtre de saisie de l'onglet Temps pointé s'ouvre de nouveau (clé d'onglet distincte de son id) [`56c1a8c0`](https://github.com/Evarisk/DoliSIRH/commit/56c1a8c0)
* [#830] [TimeSheet] fix: is_day_available() sans le code mort des congés approuvés [`a6ad174a`](https://github.com/Evarisk/DoliSIRH/commit/a6ad174a)
* [#829] [TimeSpent] fix: heure de la fenêtre de saisie des temps lue dans $conf->tzuserinputkey [`bd3007a5`](https://github.com/Evarisk/DoliSIRH/commit/bd3007a5)
* [#827] [TimeSpent] fix: droit Catégories lu par hasRight() dans les filtres par catégorie [`c0531864`](https://github.com/Evarisk/DoliSIRH/commit/c0531864)
* [#819] [PWA] fix: sans horaire de travail, l'attendu de l'application est de 0 comme sur la navette, avec un message [`cdbdbe2f`](https://github.com/Evarisk/DoliSIRH/commit/cdbdbe2f)
* [#815] [PWA] fix: heures lues et affichées dans le fuseau de saisie du cœur ($conf->tzuserinputkey) [`1a50ed9c`](https://github.com/Evarisk/DoliSIRH/commit/1a50ed9c)
* [#817] [TimeSheet] fix: bande des jours, page Navettes et application aux couleurs de l'écart du réglage du module [`02468e42`](https://github.com/Evarisk/DoliSIRH/commit/02468e42)
* [#820] [TimeSheet] fix: événement de validation libellé avec la référence définitive, plus « (PROVn) » [`f77d3718`](https://github.com/Evarisk/DoliSIRH/commit/f77d3718)
* [#818] [TimeSheet] fix: onglet Temps pointé, un jour à venir n'est dû qu'une fois pointé [`4155e294`](https://github.com/Evarisk/DoliSIRH/commit/4155e294)
* [#816] [TimeSheet] fix: un jour férié compte dans l'attendu du back-office et du verrouillage, comme sur la navette PDF [`db80f14f`](https://github.com/Evarisk/DoliSIRH/commit/db80f14f)
* [#809] [Trigger] fix: événement vers temps passé déclenché sur Dolibarr 23, à l'heure de l'événement [`8dcf93a3`](https://github.com/Evarisk/DoliSIRH/commit/8dcf93a3)
* [#808] [TimeSheet] fix: navette d'un salarié sans responsable, plus d'appel avec null et un avertissement [`fcb95a4f`](https://github.com/Evarisk/DoliSIRH/commit/fcb95a4f)
* [#806] [TimeSheet] fix: ligne Écart de l'onglet Temps pointé signée, constantes lues par getDolGlobal [`69c57106`](https://github.com/Evarisk/DoliSIRH/commit/69c57106)
* [#807] [PWA] fix: la bande de l'onglet Pointer compte la présence RH, comme la navette [`71073451`](https://github.com/Evarisk/DoliSIRH/commit/71073451)
* [#805] [PWA] fix: un jour férié garde son temps attendu, comme sur la navette PDF [`4fdf12b3`](https://github.com/Evarisk/DoliSIRH/commit/4fdf12b3)
