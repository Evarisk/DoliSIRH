# [DoliSIRH] [23.1.1] - Liste du temps passé rétablie

Description : Version corrective. Elle rétablit la **liste du temps passé**, inaccessible sur toutes les installations Dolibarr 18 et au-delà, aligne les bornes de compatibilité Dolibarr du module sur la ligne réellement livrée, rend son changelog visible dans la documentation générée, et place le code sous analyse statique à chaque modification.

**Cette version demande Saturne 23.2.1 ou supérieur.**

## Améliorations & corrections

### Temps passé

* **La liste du temps passé ne s'affichait pas du tout.** La requête demandait les colonnes `task_date` et `task_duration` à la table des temps, qui ne les porte plus depuis Dolibarr 18 : la page s'arrêtait sur une erreur de base de données. Le module tenait compte de ce renommage partout — jointure, filtres, tri — sauf dans la définition des colonnes de la liste, celle qui construit précisément la requête.
* Trois défauts que cette page masquait, puisqu'elle ne s'affichait jamais, sont corrigés dans le même mouvement : le filtre du sélecteur de client affichait une erreur de syntaxe à la place de sa liste ; **les deux totaux de bas de tableau, durée et valeur, ne s'affichaient jamais** ; et la page écrivait environ 150 avertissements PHP par affichage.

### Compatibilité

* Le module déclare **Dolibarr 23 au minimum et 24 au maximum**. Il annonçait la 18 comme plancher, une ligne qu'il ne sait plus faire fonctionner : **sur un Dolibarr antérieur à la 23, il refusera désormais de s'activer** plutôt que de s'installer pour tomber en erreur ensuite.

### Documentation du module

* Le changelog reprend le nom attendu par Dolibarr, `ChangeLog.md`. Le cœur le lit pour l'injecter dans la documentation générée du module ; sous l'ancien nom, il ne le trouvait pas sur un serveur Linux.

### Intégration continue

* Les pull requests passent désormais **PHPStan**, un **lint PHP** et un contrôle de **parité des fichiers de langue** français / anglais : une clé ajoutée d'un côté et oubliée de l'autre arrête la chaîne.
* La baseline PHPStan figeait le numéro de version du trigger dans un message d'erreur : chaque release cassait la chaîne qualité. Le motif est maintenant indépendant du numéro.

## Comparaison des versions [23.1.0](https://github.com/Evarisk/DoliSIRH/compare/23.1.0...23.1.1) et 23.1.1

* #715 [TimeSpent] fix: liste du temps passé inaccessible, et ses défauts masqués [`c8096f3`](https://github.com/Evarisk/DoliSIRH/commit/c8096f3)
* #711 [Mod] fix: renommer le changelog en ChangeLog.md [`ae3e736`](https://github.com/Evarisk/DoliSIRH/commit/ae3e736)
* #708 [CI] rework: élaguer les entrées mortes de la baseline [`b2b0c77`](https://github.com/Evarisk/DoliSIRH/commit/b2b0c77)
* #705 [CI] rework: scanner le socle par dossier plutôt que l'exclure par morceaux [`5fa45aa`](https://github.com/Evarisk/DoliSIRH/commit/5fa45aa)
* #701 [CI] fix: exclure les bouchons phan de Saturne de l'analyse [`0ad14ef`](https://github.com/Evarisk/DoliSIRH/commit/0ad14ef)
* #695 [CI] fix: numéro de version du trigger figé dans la baseline, et exclusion des stubs restaurée [`7b7e2e0`](https://github.com/Evarisk/DoliSIRH/commit/7b7e2e0)
* #693 [CI] rework: aligner phpstan.neon sur le gabarit commun [`df2b21f`](https://github.com/Evarisk/DoliSIRH/commit/df2b21f)
* #691 [CI] fix: PHPStan ne scanne plus les stubs de test de Saturne [`bac8d8b`](https://github.com/Evarisk/DoliSIRH/commit/bac8d8b)
* [CI] fix: compléter les dossiers du coeur vus par PHPStan [`61de419`](https://github.com/Evarisk/DoliSIRH/commit/61de419)
* [CI] rework: aligner phpstan.neon sur le gabarit commun [`84ee9a2`](https://github.com/Evarisk/DoliSIRH/commit/84ee9a2)
* #689 [CI] feat: PHPStan, lint PHP et parité des langues [`fbef409`](https://github.com/Evarisk/DoliSIRH/commit/fbef409)
* #687 [Module] rework: bornes de version Dolibarr 23 minimum, 24 maximum [`8c8047e`](https://github.com/Evarisk/DoliSIRH/commit/8c8047e)
