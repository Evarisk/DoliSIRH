# [DoliSIRH] [23.1.0] - Temps passé réparé - Facturation d'une ligne de service

Description : Cette version répare le **temps passé**, dont la liste tombait en erreur fatale et dont l'accueil produisait des centaines d'avertissements. Elle permet d'affecter du temps passé à une **ligne de service de facture** qui dispose encore de temps disponible, et rétablit la génération de documents sur Dolibarr 24.

**Cette version demande Saturne 23.2.0 ou supérieur.**

## Nouvelles fonctionnalités et innovations

### Temps passé

* Le temps passé s'affecte à une **ligne de service de facture** qui dispose encore de temps disponible.

## Améliorations & corrections

### Temps passé

* **La liste du temps passé tombait en erreur fatale.** La jointure sur la table des temps utilisait un alias de table inexistant, sur les installations Dolibarr 18 et au-delà : la requête échouait et la page s'arrêtait net.
* **L'accueil et la vue mensuelle écrivaient des centaines d'avertissements** : les heures travaillées n'étaient lues que sous leur forme objet, alors qu'elles arrivent parfois sous forme de tableau.
* Les colonnes dont la clé porte déjà son préfixe de table ne déclenchent plus d'avertissement, en en-tête comme dans la requête.
* Incrémentation du temps passé par jour, jours disponibles sans horodatage, clé `day` au lieu de `mday`, tâche sans temps passé, liste de valeurs des horaires de travail : autant d'accès à des clés absentes corrigés.
* Les préférences utilisateur étaient lues sans vérifier leur présence.

### Statistiques des factures récurrentes

* **La page tombait en erreur fatale** : les paramètres typés `int` du cœur recevaient des chaînes venues de la requête.

### Génération de documents

* **Dolibarr 24 : la génération de documents est réparée** — le cœur y refuse les modèles livrés avec le module et ne transmet plus ses paramètres au générateur.
* Le répertoire des documents d'un projet retombe sur l'entité courante quand celle de l'objet n'est pas disponible.

### Feuilles de temps

* Commentaire de temps passé échappé dans l'infobulle.
* La colonne figée à gauche ne laisse plus transparaître les heures.
* Plus d'avertissement « headers already sent » à l'activation du module.

### Listes

* Le filtre du `get_full_tree` utilise la syntaxe SQL brute attendue.

### Intégration continue

* Les assets sont compilés par la chaîne du socle, vérifiés à chaque poussée et sur les pull requests ; le gulpfile local est supprimé.

## Comparaison des versions [23.0.0](https://github.com/Evarisk/DoliSIRH/compare/23.0.0...23.1.0) et 23.1.0
