# [DoliSIRH] [23.1.2] - Filtre utilisateur du temps passé enfin utilisable

Description : Version corrective. Le filtre « Utilisateur » de la liste du temps passé proposait tous les comptes de la base, désactivés et contacts de tiers compris ; il ne propose plus que ceux qui ont effectivement du temps passé.

**Cette version demande Saturne 23.2.1 ou supérieur.**

## Améliorations & corrections

### Temps passé

* **Le filtre « Utilisateur » proposait tous les comptes de la base** — soixante-deux sur notre base de test, pour vingt-trois ayant réellement du temps passé — comptes désactivés et contacts de tiers compris. La liste déroulante devenait inutilisable sur une base un peu ancienne. Elle ne propose désormais que les comptes présents dans le temps passé.
* Le critère retenu n'est pas « employé actif » mais « a du temps passé », et c'est délibéré : filtrer sur les employés actifs écartait seize des vingt-trois comptes concernés — anciens collaborateurs pour l'essentiel — dont les lignes restent affichées dans la liste. On n'aurait plus pu filtrer dessus.

## Comparaison des versions [23.1.1](https://github.com/Evarisk/DoliSIRH/compare/23.1.1...23.1.2) et 23.1.2

* #719 [TimeSpent] fix: restreindre le sélecteur utilisateur aux comptes ayant du temps passé [`1dad15b`](https://github.com/Evarisk/DoliSIRH/commit/1dad15b)
* #719 [CI] fix: le motif de baseline citait le type littéral du champ fk_user [`3239bf4`](https://github.com/Evarisk/DoliSIRH/commit/3239bf4)
