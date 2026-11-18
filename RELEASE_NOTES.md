# [DoliSIRH] [23.2.0] - Application mobile de pointage - Navette en PDF et verrouillage en masse

Description : Vos salariés pointent depuis leur téléphone : ils démarrent le pointage en arrivant, l'arrêtent en partant et y joignent un commentaire, une photo, un fichier ou un message vocal. La navette du mois se lit d'un coup d'œil, sort en PDF prêt à signer et se verrouille en masse depuis la liste.

**Cette version demande Saturne 23.8.0 ou supérieur.**

## Nouvelles fonctionnalités et innovations

### Application mobile de pointage

* Une **application mobile** (PWA) s'installe sur le téléphone depuis l'onglet « Application Web Progressive » de la configuration (QR code) et s'ouvre par l'entrée « Application mobile » du menu. Trois onglets : **Pointer**, **Semaine** et **Mensuel**.
* **Pointer en deux temps** : on choisit la tâche (les tâches récentes et la présence RH sont à un tap, sinon projet puis tâche), l'heure de début (maintenant par défaut), et on appuie sur « Pointer ». Le pointage en cours est gardé par le serveur : il survit à la fermeture de l'application et suit le salarié sur un autre appareil. Un seul pointage tourne à la fois.
* **Arrêter** ouvre la fin du pointage : heure de fin (elle suit l'horloge tant qu'on n'y touche pas), durées en un tap de 15 min à 7 h, alerte si le pointage en chevauche un autre du même type, **commentaire facultatif**, **photo** (prise sur place ou choisie dans le téléphone), **fichier** et **message vocal**, puis « Valider ». Un pointage de nuit est rangé au jour de son début. La corbeille abandonne le pointage sans rien enregistrer.
* Photos, fichiers et vocaux sont rangés dans les documents de la tâche et partent avec le pointage s'il est supprimé.
* Sous le formulaire, les **pointages du jour** : durée, commentaire, miniatures des photos, lien vers les fichiers, écoute des vocaux et total. Sur Dolibarr 24.0.2 et suivantes, chaque temps est enregistré avec ses heures de début et de fin ; les versions antérieures du cœur ramènent l'heure de début à midi (la durée et le jour restent justes).

<img src="https://raw.githubusercontent.com/nicolas-eoxia/DoliSIRH/refs/heads/assets/release-23.2.0/pwa-pointer.png" alt="Onglet Pointer sur téléphone : tâche récente « Pose des cloisons » choisie, projet et tâche remplis, début 17:00, bouton Pointer et pointages du jour" width="32%"> <img src="https://raw.githubusercontent.com/nicolas-eoxia/DoliSIRH/refs/heads/assets/release-23.2.0/pwa-en-cours.png" alt="Pointage en cours sur « Pose des cloisons » depuis 14:00, 3 h écoulées, boutons Arrêter et corbeille" width="32%"> <img src="https://raw.githubusercontent.com/nicolas-eoxia/DoliSIRH/refs/heads/assets/release-23.2.0/pwa-fin-du-pointage.png" alt="Fin du pointage : début 14:00, fin 17:00, durées en un tap, commentaire facultatif, boutons photo, galerie, fichier et vocal, bouton Valider 3 h" width="32%">

* **Semaine** : le salarié pointe sa présence et ses absences du jour sur les tâches du projet RH, avec le compteur du mois (pointé / prévu) et la bande des cinq jours de la semaine.
* **Mensuel** : le calendrier du mois, avec l'état de chaque jour (conforme, excédent, manque, à venir) et les badges Présent / Absent ; un jour touché s'ouvre dans l'onglet Semaine.

<img src="https://raw.githubusercontent.com/nicolas-eoxia/DoliSIRH/refs/heads/assets/release-23.2.0/pwa-semaine.png" alt="Onglet Semaine : projet RH, prévues novembre 2026 87:30 / 140:00, bande du lundi 16 au vendredi 20, bloc Présence avec Temps de travail effectif 03:30" width="32%"> <img src="https://raw.githubusercontent.com/nicolas-eoxia/DoliSIRH/refs/heads/assets/release-23.2.0/pwa-mensuel.png" alt="Onglet Mensuel : calendrier de novembre 2026, jours pointés en vert, jour férié du 11 en absence, légende OK, excédent, manque, à venir" width="32%">

### Navette : les jours pointés en un coup d'œil

* Sur la fiche navette, une **bande horizontale** montre chaque jour ouvré du mois : temps pointé, badge P (présence) ou A (absence), et couleur (vert conforme, orange excédent, rouge manque). Au survol, le détail de la journée : temps attendu, temps pointé sur les tâches RH avec leurs notes, temps des projets. Un clic ouvre le pointage du mois à cette date.
* Les heures pointées un week-end ou un jour sans horaire comptent désormais dans les totaux. La navette signale ces jours et ne se verrouille pas tant qu'ils restent.

<img src="https://raw.githubusercontent.com/nicolas-eoxia/DoliSIRH/refs/heads/assets/release-23.2.0/navette-jours.png" alt="Fiche navette d'octobre 2026 : bande des jours, 29/10 en orange avec son détail au survol (attendu 07:00, pointé 08:00, +01:00), 30/10 en rouge, bandeau « Parfait, toutes tes heures de travail ont été consommées »">

### Navette : onglet Temps pointé

* Nouvel onglet **Temps pointé** sur la navette : les tâches du projet RH sur la période, en deux blocs dépliables **Présence** et **Absence**, une colonne par jour, sous-totaux par bloc, puis heures prévues, temps attendu, heures consommées et écart. Le bouton de la fiche qui menait au pointage ouvre cet onglet.

<img src="https://raw.githubusercontent.com/nicolas-eoxia/DoliSIRH/refs/heads/assets/release-23.2.0/navette-temps-pointe.png" alt="Onglet Temps pointé de la navette d'octobre : blocs Présence (126:00) et Absence (28:00) dépliés, congés payés les 12 et 13, arrêt maladie les 23 et 26, totaux et écart par jour">

### Navette : tous les salariés du mois sur une page

* L'entrée **Navette** du menu ouvre une page avec tous les salariés actifs : pour chacun, une carte de synthèse (jours prévus et pointés, heures prévues, consommées, écart en couleur), un détail au survol (référence et état de la navette, jours pointés hors horaire) et la bande des jours du mois. On passe d'un mois à l'autre par les flèches ; la liste est dans le sous-menu **Liste des navettes**.

<img src="https://raw.githubusercontent.com/nicolas-eoxia/DoliSIRH/refs/heads/assets/release-23.2.0/navettes-du-mois.png" alt="Page Navette d'octobre 2026 : trois salariés, deux sans horaire (jours en gris) et un dont le mois est pointé (154:00 sur 154:00, 29 en excédent, 30 en manque)">

### Navette en PDF natif, avec le récapitulatif des congés et arrêts

* La navette est générée en **PDF** (modèle « Navette PDF », A4 paysage), sur le découpage du projet RH 2.0.0 : en-tête (mois, période, projet RH, salarié, responsable, jours travaillés prévus), tuiles Attendu, Présence, Absence, Pointé et Écart, blocs **Présence** et **Absence** jour par jour (week-ends et fériés grisés), synthèse attendu / pointé / écart, éléments variables de paie, note publique et signatures du salarié et du responsable.
* Un **récapitulatif des congés et arrêts maladie** liste les périodes de congés payés, congés sans solde et arrêt maladie : type, premier et dernier jour, nombre de jours pointés et durée. Les jours consécutifs sur la même tâche forment une seule période, même par-dessus un week-end : un arrêt du vendredi au lundi compte pour une période de deux jours.
* Le temps attendu suit les horaires du jour de la semaine : un jour de congé ou un jour férié se justifie par une tâche d'absence. La navette s'imprime en noir et blanc, un gris clair ne marque que les en-têtes et les jours non travaillés.
* Le modèle ODT de la navette et son gabarit sont retirés : le PDF devient le modèle par défaut à l'activation.

<img src="https://raw.githubusercontent.com/nicolas-eoxia/DoliSIRH/refs/heads/assets/release-23.2.0/navette-pdf.png" alt="Navette PDF d'octobre 2026 : tuiles Attendu 154:00, Présence 126:00, Absence 28:00, Pointé 154:00, Écart OK, blocs Présence et Absence jour par jour, synthèse">

<img src="https://raw.githubusercontent.com/nicolas-eoxia/DoliSIRH/refs/heads/assets/release-23.2.0/navette-pdf-conges.png" alt="Page 2 de la navette PDF : récapitulatif (congés payés du lundi 12 au mardi 13/10, arrêt maladie du vendredi 23 au lundi 26/10, deux jours chacun), éléments variables de paie, signatures du salarié et du responsable">

### Liste des navettes : verrouillage en masse

* Nouvelle action de masse **Verrouiller** sur la liste des navettes, avec confirmation. Seules les navettes validées, signées par tous et sans écart d'heures sont verrouillées ; les autres sont ignorées et leur nombre est indiqué. Un échec sur une navette n'arrête pas les autres.
* Le verrouillage contrôle tous les temps avant d'en lier un seul : un temps déjà lié à une autre navette ne laisse plus de liens partiels, qui bloquaient ensuite tout nouveau verrouillage. Depuis la fiche, les conditions sont vérifiées au moment de verrouiller, et plus seulement à l'affichage du bouton.

<img src="https://raw.githubusercontent.com/nicolas-eoxia/DoliSIRH/refs/heads/assets/release-23.2.0/navettes-verrouillage.png" alt="Liste des navettes : trois navettes cochées et la confirmation « Êtes-vous sûr de vouloir verrouiller les 3 navette(s) sélectionnée(s) ? Seules les navettes validées, signées par tous et sans écart d'heures seront verrouillées »">

### Pointage : se désaffecter en un clic

* Sur le pointage, une icône à côté de l'étoile des favoris **retire le salarié affiché de la tâche** ; sur l'en-tête d'un projet, du projet et de toutes ses tâches. Le tableau se recharge sur place et le temps déjà pointé est conservé. Le projet RH n'a pas d'icône sur son en-tête : ses tâches de présence et d'absence se retirent une à une.

<img src="https://raw.githubusercontent.com/nicolas-eoxia/DoliSIRH/refs/heads/assets/release-23.2.0/pointage-desaffecter.png" alt="Pointage de la semaine : icône de désaffectation sur chaque tâche et sur l'en-tête du projet « Réaménagement de l'atelier », survolée en rouge sur « Pose des cloisons »">

### Projet RH 2.0.0 : tâches de présence et d'absence séparées

* Un nouveau projet « Ressources humaines - 2.0.0 - <votre société> » est créé avec **12 tâches** : 8 d'absence (congés sans solde, congés payés, arrêt maladie, jour férié, RTT, récupération, repos compensateur, absence exceptionnelle) et 4 de présence (temps de travail effectif, formation professionnelle, déplacement professionnel, délégation CSE). Les salariés internes y sont affectés ; l'ancien projet RH et ses pointages sont conservés.
* La configuration « Projets et tâches » présente le projet RH, les tâches d'absence et les tâches de présence dans trois blocs ; le bandeau qui annonce la création du nouveau projet se ferme définitivement.

<img src="https://raw.githubusercontent.com/nicolas-eoxia/DoliSIRH/refs/heads/assets/release-23.2.0/config-projet-rh.png" alt="Configuration Projets et tâches : sommaire à gauche, projet Ressources humaines 2.0.0, huit tâches d'absence (No Work Time) et quatre tâches de présence (Work Time)">

## Améliorations & corrections

### Navette

* **Navette faisable à partir de J-4** avant sa date de fin, avec l'option « Vérifier la date fin de navette » : celle d'octobre se fait dès le 27/10 au lieu d'attendre la fin du mois. Le message d'erreur donne la date à partir de laquelle elle peut être faite, et le préremplissage propose le mois en cours dès J-4 (il ne se trompe plus de mois le 31).
* **L'utilisateur assigné ne change plus en silence** : à la création, si l'utilisateur connecté n'était pas salarié, le premier salarié de la liste était enregistré à sa place ; en modification, enregistrer pouvait réassigner la navette. L'utilisateur connecté et l'utilisateur déjà assigné restent proposés.
* « fr_FR » ne s'affiche plus à côté de la corbeille des documents de la navette et du certificat.

### Pointage

* **Ce qui est compté, avec le projet RH 2.0.0** : la navette compare les horaires au seul temps des tâches du projet RH (présence et absences), si bien que le temps pointé sur un projet client ne crée plus d'écart rouge qui bloquerait le verrouillage. Le décompte en bas du pointage et le tableau de bord, analytiques, comptent les projets et les absences, sans le temps de présence qui ferait doublon.
* **Changement d'heure** : le temps passé d'après le changement d'heure sortait de sa colonne (compté dans le total mais absent des cases du jour) et l'en-tête affichait deux fois le dimanche.
* Après un ajout de temps dans la fenêtre, le tableau n'est plus imbriqué dans lui-même.

### Horaires de travail

* **Les droits sont contrôlés à l'enregistrement**, plus seulement sur l'affichage du bouton : n'importe quel utilisateur pouvait modifier les horaires de n'importe qui en envoyant le formulaire. Un nouveau droit, « Créer/Modifier les horaires de travail de tous les utilisateurs », sert aux RH hors hiérarchie.
* La fiche des horaires reprenait le bandeau d'un tiers : retour vers la liste des tiers, et des flèches qui vidaient la grille puis faisaient refuser l'enregistrement. Elle a le bandeau des onglets utilisateur.

### Projet RH et configuration

* Le projet RH est créé au statut validé ; il restait en brouillon.
* Dolibarr 24 : la création du projet RH affecte bien les salariés à ses tâches.
* La création des produits et services de la navette ne produit plus d'avertissements PHP.
* Le champ de tâche des tickets est créé même quand le module Ticket est activé après DoliSIRH.

### Factures

* Les listes et fiches de factures ne tombent plus en erreur fatale quand un autre module (AdvancedKanban 1.5.9 par exemple) efface les types de catégories des factures.

### Sécurité et installation

* Les liens et appels AJAX portent le jeton anti-CSRF ; le bouton « Ajouter une tâche » ne renvoie plus d'erreur 403 quand le jeton est exigé partout.
* Sur un Saturne trop ancien, l'activation est refusée avec un message clair au lieu d'une erreur fatale.
* DoliSIRH fonctionne installé dans une racine alternative à `custom/` ou par lien symbolique : liens, onglets, application mobile et pictogramme retrouvent leur chemin.

### Affichage

* Les écrans DoliSIRH adoptent le **thème moderne** partagé par Saturne : grilles de temps passé plus compactes (le mois passe de 2659 à 2127 px de large, la journée et la semaine tiennent sur 1280 px), temps déjà saisi en pastille, week-ends teintés, jours fériés en ambre, statistiques des factures récurrentes avec les filtres à gauche et les graphiques à droite sur grand écran.

## Testez les nouveautés maintenant

**Allez tester l'application mobile de pointage sur la démo : [https://demodoli.digirisk.com/](https://demodoli.digirisk.com/)**

## Comparaison des versions [23.1.2](https://github.com/Evarisk/DoliSIRH/compare/23.1.2...23.2.0) et 23.2.0

* [#796] [CI] chore: build assets JS/CSS minified [`31570432`](https://github.com/Evarisk/DoliSIRH/commit/31570432)
* [#796] [PWA] feat: pointer en deux temps (début, puis fin avec commentaire facultatif, photo, fichier et vocal) [`cffde6f1`](https://github.com/Evarisk/DoliSIRH/commit/cffde6f1)
* [#794] [CI] chore: build assets JS/CSS minified [`eb203422`](https://github.com/Evarisk/DoliSIRH/commit/eb203422)
* [#794] [PWA] feat: onglet Pointer avec heure de début / fin et médiathèque Saturne [`06007dd7`](https://github.com/Evarisk/DoliSIRH/commit/06007dd7)
* [#792] [List] feat: action de masse Verrouiller sur la liste des navettes [`251ff477`](https://github.com/Evarisk/DoliSIRH/commit/251ff477)
* [#792] [TimeSheet] rework: conditions et verrouillage de la navette portés par la classe [`e38911a1`](https://github.com/Evarisk/DoliSIRH/commit/e38911a1)
* [#767] [CI] ci: retrait du workflow Generate Auto Changelog sur main [`76480ee0`](https://github.com/Evarisk/DoliSIRH/commit/76480ee0)
* [#786] [Hook] fix: contexte de la page cherché dans tout le contexte, plus dans currentcontext [`938e3725`](https://github.com/Evarisk/DoliSIRH/commit/938e3725)
* [#785] [Module] fix: chemins relatifs confiés au cœur sans custom/ [`df1b9c5f`](https://github.com/Evarisk/DoliSIRH/commit/df1b9c5f)
* [#785] [Module] fix: dol_buildpath('/custom/…') sans custom/ [`6df37776`](https://github.com/Evarisk/DoliSIRH/commit/6df37776)
* [#785] [Module] fix: URL construites avec DOL_URL_ROOT . '/custom/…' [`99f5aad6`](https://github.com/Evarisk/DoliSIRH/commit/99f5aad6)
* NEW: #783 Add mobile PWA for time tracking (HR week, project with media, monthly) [`4ab874e1`](https://github.com/Evarisk/DoliSIRH/commit/4ab874e1)
* FIX #781 Fix month navigation from October to November on timesheet page [`423c179a`](https://github.com/Evarisk/DoliSIRH/commit/423c179a)
* NEW #779 Add timesheet overview page for all employees and list submenu [`8bd71891`](https://github.com/Evarisk/DoliSIRH/commit/8bd71891)
* NEW: #777 add HR project time spent tab on timesheet [`5af696a5`](https://github.com/Evarisk/DoliSIRH/commit/5af696a5)
* FIX: #775 count all logged hours and check time outside working hours [`1b82d47a`](https://github.com/Evarisk/DoliSIRH/commit/1b82d47a)
* [#773] [TimeSheet] feat: bande horizontale des jours pointés sur la fiche navette [`1e376eba`](https://github.com/Evarisk/DoliSIRH/commit/1e376eba)
* [#771] [CI] ci: actions GitHub en Node 24 (checkout@v7, cache@v6) [`9a9ad6d6`](https://github.com/Evarisk/DoliSIRH/commit/9a9ad6d6)
* [#769] [CI] ci: contrôle « API Saturne publiée » dans la CI qualité [`03b1819a`](https://github.com/Evarisk/DoliSIRH/commit/03b1819a)
* [#767] [CI] ci: retrait du workflow Generate Auto Changelog [`38a2a472`](https://github.com/Evarisk/DoliSIRH/commit/38a2a472)
* [#765] [Mod] feat: version minimale de Saturne, activation refusee sur un Saturne trop ancien [`0a903679`](https://github.com/Evarisk/DoliSIRH/commit/0a903679)
* [#590] [WorkingHours] fix: bandeau utilisateur sur la fiche des horaires de travail [`28ee3a17`](https://github.com/Evarisk/DoliSIRH/commit/28ee3a17)
* [#738] [TimeSheetDocument] fix: jours de la navette ajoutés dans le fuseau du serveur (comme #750) [`eb71b9ba`](https://github.com/Evarisk/DoliSIRH/commit/eb71b9ba)
* [#762] [TimeSheet] fix: mois et année de la fiche navette typés en entier [`e73d66be`](https://github.com/Evarisk/DoliSIRH/commit/e73d66be)
* [#762] [TimeSheet] fix: utilisateur connecté toujours sélectionnable comme utilisateur assigné [`c8784ead`](https://github.com/Evarisk/DoliSIRH/commit/c8784ead)
* [#762] [TimeSheet] feat: navette faisable à partir de J-4 avant sa date de fin [`de5cf8dd`](https://github.com/Evarisk/DoliSIRH/commit/de5cf8dd)
* [#759] [TimeSpent] feat: se désaffecter d'une tâche ou d'un projet en un clic depuis le pointage [`10b8007f`](https://github.com/Evarisk/DoliSIRH/commit/10b8007f)
* [#758] [TimeSpent] fix: décompte du pointage sans le temps de présence, absences comprises [`b06f3684`](https://github.com/Evarisk/DoliSIRH/commit/b06f3684)
* [#756] [TimeSheet] fix: comparer les horaires au seul temps des tâches du projet RH [`b5db94e1`](https://github.com/Evarisk/DoliSIRH/commit/b5db94e1)
* [#754] [CI] ci: refuser les inclusions relatives de Saturne [`f026a740`](https://github.com/Evarisk/DoliSIRH/commit/f026a740)
* [#752] [JS] fix: replace the time spent table after a modal save instead of nesting it [`17e6a959`](https://github.com/Evarisk/DoliSIRH/commit/17e6a959)
* [#750] [TimeSpent] fix: add days in the server timezone so time spent stays in its day column after a DST change [`5d016873`](https://github.com/Evarisk/DoliSIRH/commit/5d016873)
* [#748] [Module] fix: load the ticket tables so the task extrafield exists when Ticket is enabled later [`0eb16a5c`](https://github.com/Evarisk/DoliSIRH/commit/0eb16a5c)
* [#746] [Setup] fix: USF filter on Dolibarr 24+, getDolGlobalInt() in setup.php, HR project title and closeable notice [`5d06d50d`](https://github.com/Evarisk/DoliSIRH/commit/5d06d50d)
* [#744] [TimeSheet] fix: create temp dir before writing signature images [`2e928fc5`](https://github.com/Evarisk/DoliSIRH/commit/2e928fc5)
* [#742] [HRProject] fix: create HR project with validated status instead of draft [`bd795eb7`](https://github.com/Evarisk/DoliSIRH/commit/bd795eb7)
* [#740] [TimeSheet] fix: « fr_FR » affiché à côté de la corbeille des documents [`b95336d8`](https://github.com/Evarisk/DoliSIRH/commit/b95336d8)
* [#738] [TimeSheetDocument] fix: tuiles de synthese sans barre de couleur [`b6ad8d5e`](https://github.com/Evarisk/DoliSIRH/commit/b6ad8d5e)
* [#738] [TimeSheetDocument] fix: navette en noir et blanc pour l'impression [`8c127b0a`](https://github.com/Evarisk/DoliSIRH/commit/8c127b0a)
* [#738] [TimeSheetDocument] feat: récapitulatif des dates de congés et d'arrêts maladie [`b6bc67c7`](https://github.com/Evarisk/DoliSIRH/commit/b6bc67c7)
* [#738] [TimeSheetDocument] feat: navette en PDF natif, tâches du projet RH en blocs Présence / Absence [`4b1d9664`](https://github.com/Evarisk/DoliSIRH/commit/4b1d9664)
* [#736] [HRProject] feat: separate No Work Time and Work Time tasks and create HR Project 2.0.0 [`f40ea24b`](https://github.com/Evarisk/DoliSIRH/commit/f40ea24b)
* [#734] [Header] feat: thème partagé sur les pages en llxHeader [`29c15125`](https://github.com/Evarisk/DoliSIRH/commit/29c15125)
* [#734] [SCSS] feat: thème moderne des écrans DoliSIRH [`450b56e3`](https://github.com/Evarisk/DoliSIRH/commit/450b56e3)
* [#732] [WorkingHours] fix: check permissions when saving working hours [`9adc7d87`](https://github.com/Evarisk/DoliSIRH/commit/9adc7d87)
* [#730] [Facture] fix: no fatal count() when the invoice tag types are missing [`251c0e86`](https://github.com/Evarisk/DoliSIRH/commit/251c0e86)
* [#729] [CSRF] fix: ajouter le token CSRF aux liens et appels AJAX [`ff72d2e7`](https://github.com/Evarisk/DoliSIRH/commit/ff72d2e7)
* [#724] [Saturne] fix: retirer $soc jamais défini dans la liste des documents projet [`d5d97846`](https://github.com/Evarisk/DoliSIRH/commit/d5d97846)
* [#724] [Saturne] fix: inclure Saturne par dol_include_once / dol_buildpath [`e665f85a`](https://github.com/Evarisk/DoliSIRH/commit/e665f85a)
