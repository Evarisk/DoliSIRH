# [DoliSIRH] [23.2.3] - Saisie d'un événement préservée sans tâche - Conversion PDF soumise au droit de générer le document

Description : pointer du temps sur un événement sans tâche est refusé avant l'envoi sans perdre la saisie, supprimer une feuille de temps libère ses temps pour en refaire une, la fiche ticket ne s'arrête plus sur une erreur quand DoliSIRH est installé par un lien, et la conversion en PDF depuis la fiche projet demande le droit de générer le document.

**Cette version demande Saturne 23.10.1 ou supérieur.**

**À installer avant Saturne 23.11.0, ou en même temps.** Avec une version antérieure de DoliSIRH, Saturne 23.11.0 refuserait la conversion en PDF sur la fiche projet.

## Améliorations & corrections

### Agenda et temps passé

* Un événement avec du temps passé mais sans tâche est refusé avant l'envoi, avec un message : la saisie reste à l'écran au lieu d'être perdue.

### Feuilles de temps

* Supprimer une feuille de temps libère ses temps consommés : une nouvelle feuille du même mois peut de nouveau être créée et verrouillée, y compris pour les mois déjà bloqués.
* La fiche d'une feuille de temps en brouillon n'a plus d'erreur JavaScript, et le bouton « Envoyer email » ne lève plus d'avertissement quand aucun document n'a encore été généré.

### Documents

* La conversion en PDF d'un document depuis la fiche projet demande le droit de générer ce document, comme le bouton « Générer ».

### Fiches et droits

* La fiche ticket ne s'arrête plus sur une erreur fatale quand DoliSIRH est installé par un lien symbolique.
* Un utilisateur à qui un droit DoliSIRH n'est pas accordé ne fait plus écrire d'avertissements dans le journal PHP.
* La fiche certificat n'écrit plus d'avertissement dans le journal PHP à chaque affichage.

### Affichage

* Après une mise à jour de DoliSIRH ou de Saturne, les pages chargent les nouveaux styles et scripts : plus besoin de vider le cache du navigateur (Ctrl+F5).
* Si Saturne est absent, DoliSIRH affiche un message clair au lieu d'une erreur.

## Testez les nouveautés maintenant

**Allez tester la saisie de temps depuis l'agenda sur la démo : [https://demodoli.digirisk.com/](https://demodoli.digirisk.com/)**

## Comparaison des versions [23.2.2](https://github.com/Evarisk/DoliSIRH/compare/23.2.2...23.2.3) et 23.2.3

* [#868] [TimeSheet] fix: fiche sans erreur JS en brouillon ni warning sans document [`b8b8f03`](https://github.com/Evarisk/DoliSIRH/commit/b8b8f03)
* [#547] [TimeSheet] fix: supprimer une navette libère ses temps consommés [`b12eb4c`](https://github.com/Evarisk/DoliSIRH/commit/b12eb4c)
* [#573] [Agenda] fix: temps passé sans tâche refusé avant l'envoi, la saisie de l'événement reste [`7950ce7a`](https://github.com/Evarisk/DoliSIRH/commit/7950ce7a)
* [#864] [Assets] fix: version the assets printed by hand through saturne_asset_full_url [`1f251179`](https://github.com/Evarisk/DoliSIRH/commit/1f251179)
* [Documents] fix: génération PDF manuelle soumise au droit de générer le document [`7e30ed84`](https://github.com/Evarisk/DoliSIRH/commit/7e30ed84)
* [#861] [Certificate] fix: ne plus recréer element_type sans visible sur la fiche [`7757d3bf`](https://github.com/Evarisk/DoliSIRH/commit/7757d3bf)
* [#859] [Rights] fix: lire les droits par hasRight() pour ne plus avertir quand un droit manque [`6ff738e3`](https://github.com/Evarisk/DoliSIRH/commit/6ff738e3)
* [#857] [Saturne] fix: message explicite quand l'amorçage ne trouve pas Saturne [`f331aa13`](https://github.com/Evarisk/DoliSIRH/commit/f331aa13)
* [#855] [Ticket] fix: inclure task.class.php du cœur par DOL_DOCUMENT_ROOT [`163ff727`](https://github.com/Evarisk/DoliSIRH/commit/163ff727)
