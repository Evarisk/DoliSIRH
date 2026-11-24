# [DoliSIRH] [23.2.2] - Filtre des temps consommés sur la bonne période - Formulaire de temps sans avertissement

Description : le filtre « tâches avec temps consommé » de la saisie des temps porte de nouveau sur la semaine ou le jour affiché, et le formulaire d'ajout de temps d'une tâche n'affiche plus de colonne Navette vide. La version supprime aussi des avertissements PHP quand un autre module déclenche ses propres événements.

**Cette version demande Saturne 23.9.1 ou supérieur.**

## Améliorations & corrections

### Saisie des temps

* En vue semaine, cocher l'horloge (« tâches avec temps consommé ») montrait en 2025 et 2026 les tâches de la semaine suivante. Le filtre porte maintenant sur la semaine affichée.
* En vue jour, le même filtre ne tenait compte que du jour du mois, quel que soit le mois. Il porte maintenant sur le jour affiché.

### Formulaire d'ajout de temps d'une tâche

* Le formulaire n'affiche plus de colonne Navette : une ligne en cours de saisie n'est liée à aucune navette. Cette colonne vide laissait cinq avertissements dans le journal PHP à chaque affichage. La liste des temps garde sa colonne.

### Cohabitation avec les autres modules

* Un module qui déclenche un événement sur un objet qui n'est pas un objet Dolibarr (ExternalAccess, par exemple) ne laisse plus d'avertissements dans le journal PHP.

## Testez les nouveautés maintenant

**Allez tester la saisie des temps sur la démo : [https://demodoli.digirisk.com/](https://demodoli.digirisk.com/)**

## Comparaison des versions [23.2.1](https://github.com/Evarisk/DoliSIRH/compare/23.2.1...23.2.2) et 23.2.2

* [#850] [Trigger] fix: plus d'avertissement sur un événement dont l'objet n'est pas un CommonObject [`e0a1018a`](https://github.com/Evarisk/DoliSIRH/commit/e0a1018a)
* [#848] [TimeSpent] fix: pas de colonne Navette dans le formulaire d'ajout de temps [`90ca968e`](https://github.com/Evarisk/DoliSIRH/commit/90ca968e)
* [#846] [TimeSpent] fix: colonne de date choisie dans le if/else existant (PHPStan) [`7f5d9592`](https://github.com/Evarisk/DoliSIRH/commit/7f5d9592)
* [#846] [TimeSpent] fix: filtre « tâches avec temps consommé » sur la période affichée [`d179accb`](https://github.com/Evarisk/DoliSIRH/commit/d179accb)
