# [DoliSIRH] [23.1.1] - Compatibilité déclarée et chaîne qualité

Description : Version de maintenance. Elle aligne les bornes de compatibilité Dolibarr du module sur la ligne réellement livrée, rend son changelog visible dans la documentation générée, et place le code sous analyse statique à chaque modification.

**Cette version demande Saturne 23.2.1 ou supérieur.**

## Améliorations & corrections

### Compatibilité

* Le module déclare **Dolibarr 23 au minimum et 24 au maximum**. Il annonçait la 18 comme plancher, une ligne qu'il ne sait plus faire fonctionner : **sur un Dolibarr antérieur à la 23, il refusera désormais de s'activer** plutôt que de s'installer pour tomber en erreur ensuite.

### Documentation du module

* Le changelog reprend le nom attendu par Dolibarr, `ChangeLog.md`. Le cœur le lit pour l'injecter dans la documentation générée du module ; sous l'ancien nom, il ne le trouvait pas sur un serveur Linux.

### Intégration continue

* Les pull requests passent désormais **PHPStan**, un **lint PHP** et un contrôle de **parité des fichiers de langue** français / anglais : une clé ajoutée d'un côté et oubliée de l'autre arrête la chaîne.
* La baseline PHPStan figeait le numéro de version du trigger dans un message d'erreur : chaque release cassait la chaîne qualité. Le motif est maintenant indépendant du numéro.

## Comparaison des versions [23.1.0](https://github.com/Evarisk/DoliSIRH/compare/23.1.0...23.1.1) et 23.1.1

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
