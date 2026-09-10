---
title: sqlguard
created: 2026-09-10
updated: 2026-09-10
---

# PRD : sqlguard
*Nom **retenu** par l'auteur le 2026-09-10. Paquet `sqlguard` libre sur Packagist (vérifié). Antériorité de marque non vérifiée — voir Q-1, à lever avant l'annonce publique, pas avant le code.*

## Décisions engagées

Ce PRD est **antérieur au code**. Il porte quatre décisions déjà arbitrées :

1. **Rien ne se code avant la porte K-1** (§19), fixée à T+6 semaines et franchie par des entretiens, pas par du développement. Décision par défaut si elle échoue : abandon du produit.
2. **Une seule revendication, falsifiable** (§3.4) : « Sur un corpus figé de code PHP legacy non typé, `sqlguard` en configuration par défaut détecte au moins autant de chaînes de propagation vers PDO que Psalm et Semgrep en configuration par défaut, avec un nombre de faux positifs par 10 kLOC inférieur ou égal, et produit pour chaque alerte une chaîne de propagation reproductible que ni l'un ni l'autre ne fournit. »
3. **La V1 tient à un seul sink : PDO** (§9.1). `mysqli` et `wpdb` sont des incréments ultérieurs. Une promesse tenue vaut mieux que dix annoncées.
4. **SARIF est rétrogradé en livrable secondaire** (§7.3). L'ingestion dans l'onglet Security de GitHub est payante sur dépôt privé, or la cible travaille en dépôt privé. Le livrable primaire est le commentaire de PR, doublé d'un rapport autoportant.

La prémisse « le marché de la vérification est vide » a été **retirée** : elle est fausse (§3). Aucun des 8 jobs du §5.1 n'est validé à ce jour ; tous sont des hypothèses (§21).

## Sommaire

- [0. Objet du document](#0-objet-du-document)
- [1. Vision](#1-vision)
- [2. Pourquoi maintenant](#2-pourquoi-maintenant)
- [3. Carte concurrentielle et revendication de différenciation](#3-carte-concurrentielle-et-revendication-de-differenciation)
- [4. Actifs, droit à gagner et stratégie de crédibilité substitutive](#4-actifs-droit-a-gagner-et-strategie-de-credibilite-substitutive)
- [5. Utilisateur cible](#5-utilisateur-cible)
- [6. Glossaire](#6-glossaire)
- [7. Fonctionnalités](#7-fonctionnalites)
- [8. Non-objectifs (explicites)](#8-non-objectifs-explicites)
- [9. Périmètre MVP](#9-perimetre-mvp)
- [10. Exigences non fonctionnelles transverses](#10-exigences-non-fonctionnelles-transverses)
- [11. Contraintes et garde-fous](#11-contraintes-et-garde-fous)
- [12. Modèle économique et frontière open/commercial](#12-modele-economique-et-frontiere-opencommercial)
- [13. Porte de validation avant le code](#13-porte-de-validation-avant-le-code)
- [14. Contrats d'API et surface publique](#14-contrats-dapi-et-surface-publique)
- [15. Versionnage et politique de dépréciation](#15-versionnage-et-politique-de-depreciation)
- [16. Budgets de performance](#16-budgets-de-performance)
- [17. Cibles de langage/runtime et politique de dépendances](#17-cibles-de-langageruntime-et-politique-de-dependances)
- [18. Métriques de succès](#18-metriques-de-succes)
- [19. Critères d'arrêt — pré-enregistrés](#19-criteres-darret-pre-enregistres)
- [20. Feuille de route](#20-feuille-de-route)
- [21. Registre des incertitudes](#21-registre-des-incertitudes)

---

## 0. Objet du document

Ce PRD est destiné au mainteneur-fondateur (auteur unique aujourd'hui), aux contributeurs qu'il cherchera à recruter, et aux workflows aval (`bmad-architecture`, `bmad-create-epics-and-stories`). Il est **post-critique** : il consolide la direction issue de `_bmad-output/brainstorming/brainstorm-avenir-outil-open-source-2026-09-10/brainstorm-intent.md` **après** absorption des 18 objections de `_bmad-output/planning-artifacts/adversarial-review-concept.md`. Là où les deux documents divergent, la revue tranche, et ce PRD écrit la version corrigée sans renvoyer le lecteur à l'original. Trois divergences majeures sont assumées ici : la prémisse « marché vide » est retirée (§3), le périmètre V1 passe de trois sinks à **un seul** (§9), et SARIF est rétrogradé en artefact secondaire (§7.3). Le vocabulaire est fixé au §6 (Glossaire) et utilisé verbatim partout ailleurs. Chaque exigence fonctionnelle porte un identifiant stable `FR-n` et est testable. Les hypothèses sont marquées `[HYP-n]` en ligne et indexées au §21 ; ce document repose massivement sur des hypothèses non validées, et c'est volontairement visible. Aucun document UX n'existe à ce stade.

---

## 1. Vision

`sqlguard` est un moteur d'analyse statique qui **juge** le code d'accès base de données PHP au lieu de l'écrire. Il répond à une question qu'un développeur ne peut aujourd'hui pas trancher sans payer un auditeur : *dans ce dépôt, existe-t-il une chaîne de propagation par laquelle une source non fiable atteint une requête SQL sans être paramétrée, et peut-on le démontrer ?* La réponse n'est pas un score abstrait : c'est une liste de **chaînes de propagation** concrètes, chacune reliant une entrée HTTP à une ligne d'exécution SQL, chacune accompagnée d'un **témoin** reproductible.

La douleur visée n'est pas d'écrire du SQL — ce marché est une commodité saturée (Doctrine, Eloquent, PDO natif). La douleur est la **confiance dans le SQL déjà écrit** : par un prestataire parti, par un legacy de dix ans, par un agent de codage qui a produit trente requêtes en une session que personne n'a relues ligne à ligne. `[HYP-1]`

La contrainte fondatrice est un refus : ne jamais entrer en concurrence avec un ORM, un query builder ou une fonctionnalité intégrée de framework. En cessant d'écrire du SQL, l'outil devient complémentaire de tout l'écosystème au lieu d'en être le énième concurrent. Le modèle de référence est PHPStan / Rector / Composer : les outils PHP massivement installés ne vivent pas dans le code applicatif, ils le jugent ou le transforment. Cette contrainte est rendue **opérationnellement testable** au §11.4, et le garde-fou d'exécution (cluster C) y est soumis par écrit.

Le projet n'a, à la date de ce document, **aucun actif de crédibilité en sécurité** et pour seule provenance un query builder dont `escape_data()` livrait un échappement no-op. Le §4 traite ce point de front : la crédibilité sera substituée par des artefacts mesurables et publics, pas revendiquée.

---

## 2. Pourquoi maintenant

Quatre éléments de calendrier, dont deux seulement sont solides.

| Élément | Solidité | Conséquence produit |
|---|---|---|
| Génération de code d'accès base par agents de codage, à un volume qui excède la capacité de relecture humaine | **Hypothèse non mesurée** `[HYP-2]` — la formulation initiale « une part majeure du code d'accès base est générée par IA » n'était pas sourcée et est ici rétrogradée en hypothèse à valider par les entretiens (§13) | Justifie la voie 2027 « vérification du SQL généré » (§7.6), pas le produit lui-même |
| L'outillage statique PHP est devenu socialement obligatoire (PHPStan, Psalm, Rector, PHP-CS-Fixer installés par défaut) | **Solide** — créneau d'installation ouvert et documenté | Le coût d'adoption d'un nouvel outil de type linter est historiquement bas : `require --dev` + une ligne de CI |
| SARIF et l'onglet Security de GitHub ont normalisé le format de sortie | **Solide sur le format, faux sur la gratuité** — l'ingestion SARIF n'est gratuite que sur dépôt public ; en dépôt privé elle relève de GitHub Advanced Security, payant `[HYP-3]` (à reconfirmer contre la tarification GitHub en vigueur, Q-4) | SARIF est un artefact **secondaire** ; le livrable primaire est le commentaire de PR + le rapport autoportant (§7.3) |
| Aggravation réglementaire de la pression sur la chaîne logicielle | **Non instruit** — hors périmètre de ce PRD | Aucune décision n'en dépend |

**Contre-argument à ne pas dissimuler :** l'injection est passée du rang 1 au **rang 3** de l'OWASP Top 10 2021. La tendance de fond est à la baisse de la classe de vulnérabilité, parce que les frameworks paramètrent par défaut. Le pari de `sqlguard` n'est donc pas « l'injection augmente » mais « le stock de code PHP non-framework qui concatène reste massif et non mesuré ». Ce pari est **directement mesurable** et cette mesure est le premier livrable de crédibilité (FR-19).

---

## 3. Carte concurrentielle et revendication de différenciation

Ce que ce chapitre tranche : qui sont les vrais concurrents, et la seule revendication de différenciation que la V1 est autorisée à porter.

La prémisse initiale est retirée. Le document d'intention affirmait que « le marché de la vérification de l'accès base en PHP est vide ». **C'est faux** et cette affirmation ne doit apparaître nulle part dans la communication du projet.

### 3.1 Faits établis

| Acteur | Ce qu'il fait réellement | Conditions d'usage | Traction |
|---|---|---|---|
| **Psalm** | Taint analysis embarquée, avec un **type de taint `sql` natif**, `runTaintAnalysis` **actif par défaut** ; sinks personnalisables par annotation ; limites d'échappement documentées | Exige un code **typé** et une configuration ; les résultats se dégradent fortement sur du legacy non annoté | Massive dans l'écosystème PHP moderne |
| **Semgrep** | Règles SQLi PHP (registre communautaire), analyse de flux | Exige la sélection/configuration d'un jeu de règles ; sensibilité au pattern plutôt qu'au flux interprocédural profond | Massive, multi-langage |
| **SonarQube** | Règles SQLi PHP dans le moteur commercial/CE | Serveur à opérer ou offre SaaS ; ciblage entreprise | Massive en entreprise |
| **Scanners PHP SQLi dédiés** | Une recherche GitHub des scanners d'injection SQL PHP dédiés renvoie **cinq dépôts**, le plus gros à **15 étoiles**, dont un `legacy-php-security-scanner` à **0 étoile** | — | **Nulle** |

### 3.2 Lecture honnête

Ni « marché vide » ni « marché occupé ». La formulation exacte, la seule que le projet est autorisé à défendre :

> **Aucun outil dédié n'a de traction, et le job est partiellement couvert par des généralistes qui exigent une configuration et un code typé.**

Le corollaire est désagréable et doit être traité comme tel : **une tentative dédiée a déjà échoué à 0 étoile.** La niche vide est un signal, pas un espace libre. L'explication par défaut à réfuter est « il n'y a pas de demande » ; c'est précisément l'objet de la porte de validation (§13).

### 3.3 Deux concurrents distincts, à ne pas confondre

La formule « le concurrent réel, c'est le consultant en audit facturé à la journée » est une **erreur de catégorie** : un audit est acheté pour une signature qui engage une responsabilité professionnelle, ce qu'un outil open source ne peut pas fournir.

| Axe | Concurrent | Nature de la compétition |
|---|---|---|
| **Créneau d'installation** (`composer require --dev` + une ligne de CI) | Psalm, Semgrep, SonarQube | Concurrence réelle, frontale, sur le budget d'attention d'outillage d'une équipe |
| **Achat d'audit de sécurité** | Cabinet / consultant | **Voie de distribution, pas concurrent.** `sqlguard` peut fournir l'artefact préparatoire qu'un auditeur consomme ; il ne remplace pas sa signature |

### 3.4 Revendication de différenciation — étroite et testable

> **Première mesure, 2026-09-10.** La comparaison a été exécutée
> (`docs/comparaison-mesuree.md`, données dans `corpus/comparaison.json`) :
> Psalm 6.17.0 en configuration minimale détecte **0 sur 10** injections vers PDO, parce que
> l'extension PDO chargée masque son propre stub annoté — silence, pas erreur. Configuré, il
> monte à 8/10 sans faux positif, soit le niveau de sqlguard sur ce corpus avec des années de
> maturité en plus. Semgrep 1.176.1 : 8/10 avec 3 faux positifs.
>
> **La revendication tient donc, mais uniquement dans sa forme étroite** : rendement en
> configuration par défaut. Toute formulation suggérant une supériorité analytique est fausse et
> interdite. Le 100 % de sqlguard porte sur un corpus que j'ai écrit : il ne démontre que sa
> cohérence interne, et ne doit jamais être cité seul.
>
> La comparaison a par ailleurs **corrigé deux défauts de sqlguard** : `addslashes` traité à tort
> comme assainisseur, et `$_SERVER` marqué en totalité. Les deux étaient invisibles pour le
> corpus seul.

Une seule revendication est autorisée en V1, formulée de façon falsifiable :

> **Sur un corpus figé de code PHP legacy non typé, `sqlguard` en configuration par défaut détecte au moins autant de chaînes de propagation vers PDO que Psalm et Semgrep en configuration par défaut, avec un nombre de faux positifs par 10 kLOC inférieur ou égal, et produit pour chaque alerte une chaîne de propagation reproductible que ni l'un ni l'autre ne fournit.**

Trois différenciateurs seulement, chacun rattaché à une exigence :

| Différenciateur | Exigence | Falsifiable par |
|---|---|---|
| Rendement en configuration par défaut sur code **non typé** | FR-16, NFR-2 | La comparaison mesurée FR-13 / SM-1 |
| **Témoin** obligatoire pour toute alerte publiée | FR-5, FR-7 | Un seul contre-exemple d'alerte sans témoin |
| Moteur **réutilisable** par les consommateurs aval (vérification de SQL généré, codemod) | FR-18 | L'existence d'un second consommateur du moteur sans fork |

Si la comparaison mesurée du SM-1 échoue, la revendication tombe et le projet est en état d'arrêt (§19, K-1). Ce n'est pas une clause de style.

---

## 4. Actifs, droit à gagner et stratégie de crédibilité substitutive

Ce que ce chapitre tranche : ce que le projet possède réellement pour gagner ici — la réponse honnête étant « presque rien » — et comment compenser cette absence.

### 4.1 Inventaire honnête des actifs

| Catégorie d'actif | État réel |
|---|---|
| Notoriété en sécurité applicative | **Aucune** |
| Publications, CVE, contributions à un outil de sécurité connu | **Aucune** |
| Audience, liste, communauté | **Aucune** |
| Financement, temps salarié dédié | **Aucun** `[HYP-4]` |
| Corpus de données propriétaire | **Aucun** |
| Base d'utilisateurs existante | **Aucune** |
| Provenance technique | Un query builder `db_query` dont la fonction `escape_data()` livrait un **échappement no-op** |

**Il n'y a pas d'avantage injuste.** Aucun élément de ce PRD ne doit être lu comme s'il en existait un.

### 4.2 Le passif de provenance, et la décision de le divulguer

Le seul antécédent du projet en matière de sécurité SQL est un défaut de sécurité SQL. Deux stratégies étaient possibles : enterrer l'historique, ou le publier. **Décision : le publier** (FR-20). Raisons :

1. C'est trouvable. Un mainteneur qui évalue un outil de sécurité lit le dépôt d'origine ; découvrir le no-op sans divulgation détruit la confiance de façon irréversible.
2. Un outil de sécurité SQL dont le fondateur a écrit puis diagnostiqué un échappement no-op raconte, s'il est assumé, exactement la thèse du produit : *l'intuition ne suffit pas, il faut un témoin*.
3. C'est le seul actif de crédibilité disponible à coût zéro et immédiatement.

### 4.3 Stratégie de crédibilité substitutive

La crédibilité sera **fabriquée par artefacts**, dans cet ordre, avant toute annonce large :

| # | Artefact | Exigence | Pourquoi il substitue de la crédibilité |
|---|---|---|---|
| A-1 | **Corpus de référence annoté, figé, public** et harnais de mesure reproductible | FR-13 | Permet à un tiers hostile de vérifier les chiffres au lieu de les croire |
| A-2 | **Comparaison mesurée contre Psalm et Semgrep**, protocole et résultats publiés, y compris les cas où `sqlguard` perd | FR-13, SM-1 | Publier ses défaites est le signal de sérieux le moins imitable |
| A-3 | **Étude de mesure directe** : 200 dépôts PHP publics échantillonnés, comptage mécanique des concaténations vers un sink base | FR-19 | Produit le premier chiffre sourcé du domaine et dimensionne le marché à la place des 70 %/40 % non sourcés |
| A-4 | **Page de provenance et de divulgation** de l'historique `escape_data()` | FR-20 | Voir §4.2 |
| A-5 | **Suite de tests de sécurité publique et reproductible**, exécutable par n'importe qui en une commande | FR-13 | Transforme « faites-moi confiance » en « lancez-le » |

### 4.4 Capacité du mainteneur et succession

**Capacité déclarée par l'auteur (2026-09-10) : 10 h/semaine sur 36 mois** `[HYP-5]`, soit **≈ 43 h/mois** et **≈ 1 560 h au total**. `Q-2` est close.

Conséquence directe, et elle est inconfortable : la capacité est suffisante en volume mais **les dates de K-2 et K-3 étaient calibrées sur une hypothèse de 6 mois et ne tiennent pas**. Elles sont recalculées au §19 à partir des 43 h/mois, et non l'inverse. Le volume total autorise en revanche les étapes S3 et S4, qu'un budget de 6 mois excluait.

| Étape | Contenu | Effort estimé | Calendrier à 43 h/mois |
|---|---|---|---|
| **S0** | Entretiens, preuve de demande passive, étude FR-19 | ~55 h | mois 0-1,5 |
| **S1** | Banc de performance, **corpus de référence** annoté et figé, harnais de mesure, comparaison publiée | ~130 h | mois 1,5-4,5 |
| **S2** | V1 : moteur interprocédural, sink base PDO, témoin niveau 1, CLI, action GitHub, rapport autoportant, SARIF | ~400 h | mois 4,5-14 |
| **S3-S4** | Sinks `mysqli` puis `wpdb`, niveaux progressifs, étude d'équivalence du codemod | ~500 h | mois 14-26 |
| **Réserve** | Support, correctifs de sécurité, dette | ~475 h | continu |

L'annotation du corpus de référence (S1) est du travail manuel non compressible : c'est elle, et non le moteur, qui fixe le plancher de S1.

**Tension à assumer :** SM-7 engage une médiane de première réponse ≤ 72 h. Le support se prélève sur les mêmes 10 h/semaine que le développement. À partir de S2, une adoption réussie **ralentit** la feuille de route — c'est le mode d'échec normal des outils solos, et non un imprévu.

**Réserve de qualification :** 10 h/semaine tenues 36 mois est une intention, pas un fait établi. `[HYP-5]` reste une hypothèse déclarée, révisable à chaque jalon K ; elle n'est pas promue en donnée.

Le **facteur bus est 1**. Pour un outil de sécurité, c'est une objection d'adoption, pas seulement un risque de projet : une équipe n'installe pas en CI un scanner de sécurité maintenu par une seule personne sans plan de succession. Exigence de crédibilité : un **co-mainteneur nommé** ou, à défaut, une **politique de succession publiée** (droits Packagist/GitHub délégués à un second détenteur, procédure d'archivage annoncée si le mainteneur devient inactif 90 jours) avant l'annonce publique. Voir Q-8.

---

## 5. Utilisateur cible

Ce que ce chapitre tranche : pour qui l'outil est fait, pour qui il ne l'est pas, et le statut de validation de chaque job.

### 5.1 Jobs to be done — statut de validation

Job commun : **transformer une inquiétude non mesurable en un résultat vérifiable.**

**Aucun de ces jobs n'est validé aujourd'hui.** Les 8 lignes sont analytiques : elles proviennent du raisonnement de l'auteur avec lui-même, pas d'entretiens. La colonne « Statut » est contraignante : elle passe à `validé par N entretiens` uniquement après la porte de validation §13, et un job resté `hypothèse` ne peut porter aucune FR de la V1.

| # | Utilisateur | Job | Ce qu'il achète | Statut | Cible V1 ? |
|---|---|---|---|---|---|
| J-1 | Lead technique legacy | « prouver que notre app n'est pas injectable » | une preuve, un rapport | **hypothèse** | **Oui — persona primaire** |
| J-2 | Équipe en préparation d'audit | « l'auditeur arrive dans deux semaines » | un rapport signable par un tiers | **hypothèse** | **Oui — persona primaire** |
| J-3 | Dev qui relit du code produit par un agent | « mon agent a écrit cette requête, est-elle sûre ? » | un vérificateur de code généré | **hypothèse** | Non — voie 2027 (§7.6) |
| J-4 | Mainteneur WordPress | « savoir si mes plugins/thèmes maison sont injectables » | un diagnostic sans expertise sécurité | **hypothèse** | Non — sink `wpdb` post-V1 |
| J-5 | Dev junior | « écrire une requête sans me faire pirater » | de la tranquillité, pas une API fluide | **hypothèse** | Non — c'est un job d'écriture, pas de jugement |
| J-6 | RSSI | « inventorier les points d'accès base et leur exposition » | une cartographie | **hypothèse** | Non — V2 |
| J-7 | Ops | « qu'aucun DELETE sans WHERE n'atteigne la prod » | un garde-fou d'exécution | **hypothèse** | Non — cluster C, sous condition §11.4 |
| J-8 | DPO | « quelles requêtes lisent des colonnes PII » | de la conformité | **hypothèse** | Non — exige le schéma, hors périmètre |

**Arbitrage produit conservé :** un artefact unique doit être lisible à la fois par le développeur (zéro friction) et par l'auditeur (preuve). C'est la contrainte de conception du rapport (FR-10).

### 5.2 Non-utilisateurs (V1)

- **Projets neufs sur Laravel/Symfony avec Eloquent/Doctrine exclusivement** — le framework paramètre déjà par défaut ; le rendement de l'outil y est structurellement faible.
- **Quiconque cherche un query builder ou une API d'écriture de SQL** — refus définitif, pas un report.
- **Équipes déjà outillées avec Psalm en code strictement typé et taint activée** — le gain marginal n'est pas démontré ; c'est précisément ce que SM-1 doit mesurer avant de prétendre le contraire.
- **Codebases non-PHP.**
- **Utilisateurs de `mysqli` ou de `wpdb` en V1** — non couverts, dit explicitement, sans faux espoir (§9.2).

### 5.3 Parcours utilisateurs clés

- **UJ-1. Karim doit dire « non injectable » à son directeur, avec une pièce jointe.**
  Karim, lead technique sur une application de facturation PHP 7.4 de 240 kLOC reprise à un prestataire disparu, dépôt **privé** sur GitHub. Il lance `composer require --dev sqlguard/sqlguard` puis `vendor/bin/sqlguard scan .` sans écrire un octet de configuration. La CLI affiche, sur un écran, 11 chaînes de propagation classées par gravité, chacune avec fichier:ligne de la source, les sauts intermédiaires et la ligne du sink base PDO. Il ouvre `sqlguard-report.html`, autoportant, et l'envoie à son directeur. **Climax :** il possède une liste finie et vérifiable là où il n'avait qu'une inquiétude. **Résolution :** il ouvre 11 tickets. **Cas limite :** 40 % du code est du templating où l'analyse ne peut pas conclure ; ces cas n'apparaissent **pas** dans les alertes — ils sont dans le canal `suspicions`, désactivé par défaut, que Karim n'a pas encore ouvert.

- **UJ-2. Nadia empêche la régression sur la PR, sans onglet Security.**
  Nadia a intégré l'action GitHub `sqlguard` sur un monorepo privé. Un contributeur ouvre une PR qui concatène `$_GET['sort']` dans un `ORDER BY`. L'action commente la PR avec la chaîne de propagation et la ligne exacte, et sort en code d'échec parce que le seuil configuré est « aucune alerte de gravité haute nouvelle ». **Climax :** le défaut est arrêté avant la fusion, sans GitHub Advanced Security, sans serveur. **Résolution :** le contributeur passe en requête préparée avec allowlist de colonnes ; le commentaire suivant est vert. **Cas limite :** l'analyse dépasse le budget de temps de la CI → l'action publie un commentaire d'exécution partielle avec le périmètre analysé, et ne sort **jamais** en échec sur une analyse incomplète (FR-12).

- **UJ-3. Sonia, auditrice, consomme le rapport au lieu de repartir de zéro.**
  Sonia, consultante en audit, reçoit avant mission le rapport HTML de son client. Elle y trouve le périmètre analysé, la version de l'outil, le hash du commit, les chaînes de propagation, et la liste explicite des **limites d'analyse** (ce que l'outil n'a pas pu suivre). Elle ne reçoit **aucune charge utile armée**. **Climax :** elle commence sa mission sur les zones que l'outil déclare ne pas couvrir, et non sur celles qu'il a déjà couvertes. **Résolution :** son rapport signé cite `sqlguard` comme travail préparatoire ; la signature de responsabilité reste la sienne.

---

## 6. Glossaire

Ces termes sont utilisés verbatim dans tout le document. Aucun synonyme n'est autorisé ailleurs.

- **Source non fiable** — Toute expression dont la valeur peut être contrôlée par un tiers : superglobales PHP (`$_GET`, `$_POST`, `$_REQUEST`, `$_COOKIE`, `$_SERVER` partiellement, `$_FILES`), corps de requête, en-têtes, arguments CLI, contenu de fichier, et valeur lue depuis la base (taint de second ordre). Un ensemble de sources est déclaré par un paquet de règles.
- **Sink base** — Point d'exécution où une chaîne de caractères est interprétée comme du SQL par un pilote de base. En V1, un seul sink base est couvert : PDO (`PDO::query`, `PDO::exec`, `PDO::prepare` avec argument construit dynamiquement, `PDOStatement` associés).
- **Assainisseur** — Opération qui rompt la propagation d'une source non fiable vers un sink base : liaison de paramètre (`bindValue`/`bindParam`/tableau d'exécution), cast numérique, comparaison à une allowlist fermée, ou fonction déclarée assainissante par un paquet de règles.
- **Chaîne de propagation** — Séquence ordonnée et non vide de positions `fichier:ligne` allant d'une source non fiable à un sink base, chaque saut étant justifié par une affectation, un passage d'argument, un retour de fonction ou une concaténation. C'est l'unité d'information atomique d'une alerte.
- **Témoin** — Preuve attachée à une alerte. Deux niveaux, jamais confondus : témoin de propagation (niveau 1, par défaut) = la chaîne de propagation reproductible, sans charge utile ; témoin exploitable (niveau 2, opt-in explicite) = une entrée concrète qui modifie la structure du SQL produit, générée dans une sortie séparée.
- **Alerte** — Constat publié par `sqlguard`, obligatoirement porteur d'un témoin de niveau 1 au minimum. Une alerte a une gravité, un sink, une chaîne, et un identifiant stable.
- **Suspicion** — Constat pour lequel l'analyse n'a pas pu produire de témoin. Émise dans un canal `suspicions` distinct, désactivé par défaut, jamais comptée dans les métriques de faux positifs des alertes.
- **Corpus de référence** — Ensemble figé et versionné de fichiers PHP dont chaque chaîne de propagation vraie est annotée à la main, servant de vérité de terrain au calcul de rappel et de faux positifs.
- **Faux positif** — Alerte dont la chaîne de propagation n'existe pas dans le corpus de référence ou est rompue par un assainisseur non reconnu. Mesuré en FP/10 kLOC.
- **Rappel** — Proportion des chaînes de propagation annotées du corpus de référence effectivement rapportées comme alertes.
- **Paquet de règles** — Unité de configuration déclarative et versionnée définissant sources, sinks, assainisseurs pour un écosystème (générique, WordPress, Symfony, Laravel).
- **Résumé de fonction** — Représentation persistée du comportement de propagation d'une fonction (quels paramètres atteignent quels retours/sinks), permettant l'analyse incrémentale.
- **Moteur** — La bibliothèque d'analyse de flux, sans interface. Consommée par la CLI, l'action GitHub, et les consommateurs aval (vérification de SQL généré, codemod).
- **Rapport autoportant** — Fichier unique HTML (ou PDF dérivé) lisible sans réseau, sans serveur et sans dépendance externe, contenant les alertes, le périmètre analysé et les limites d'analyse.
- **Limites d'analyse** — Liste explicite, publiée dans chaque rapport autoportant, des constructions que l'analyse n'a pas su suivre (appels dynamiques, `eval`, réflexion, includes conditionnels).

---

## 7. Fonctionnalités

Ce que ce chapitre tranche : les exigences fonctionnelles de la V1, chacune sous un identifiant stable `FR-n` et assortie de conséquences testables.

**Index des exigences.** Les identifiants `FR-n` sont stables et cités depuis d'autres chapitres ; ils n'apparaissent donc pas dans l'ordre de lecture. Cet index est la porte d'entrée du chapitre.

| `FR-n` | Intitulé | Bloc | Dans la V1 ? |
|---|---|---|---|
| **FR-1** | Identification des sources non fiables | Moteur | oui |
| **FR-2** | Propagation interprocédurale | Moteur | oui |
| **FR-3** | Sink base unique — PDO | Moteur | oui |
| **FR-4** | Reconnaissance des assainisseurs | Moteur | oui |
| **FR-5** | Témoin de propagation (niveau 1, par défaut) | Témoin | oui |
| **FR-6** | Témoin exploitable (niveau 2, opt-in) | Témoin | oui |
| **FR-7** | Invariant « pas d'alerte sans témoin » | Témoin | oui |
| **FR-8** | CLI zéro-configuration | Sortie | oui |
| **FR-9** | Commentaire de PR via action GitHub — livrable primaire | Sortie | oui |
| **FR-10** | Rapport autoportant | Sortie | oui |
| **FR-11** | SARIF — artefact secondaire | Sortie | oui |
| **FR-12** | Code de sortie et politique de seuil | Sortie | oui |
| **FR-13** | Corpus de référence figé, harnais de mesure et comparaison publiée | Mesure | oui |
| **FR-14** | Banc de performance en semaine 1 | Performance | oui |
| **FR-15** | Cache de résumés de fonction persisté | Performance | conditionnelle |
| **FR-16** | Configuration optionnelle et paquets de règles | Sortie | oui |
| **FR-17** | Suppression d'alerte traçable | Sortie | oui |
| **FR-18** | Surface programmatique du moteur | Moteur | oui |
| **FR-19** | Étude de mesure directe du stock de dette SQL PHP | Mesure | oui |
| **FR-20** | Page de provenance et de divulgation | Crédibilité | oui |

> Ce qui borne la V1 : **un seul sink base, PDO** (FR-3).

### 7.1 Moteur d'analyse de flux (taint) — cœur agnostique du dialecte

**Description :** Le **moteur** construit, à partir de l'AST fourni par `nikic/php-parser`, un graphe de propagation des **sources non fiables** vers les **sinks base**, interprocédural, avec reconnaissance des **assainisseurs**. Correction explicite de la direction initiale : **la détection taint-vers-sink est très largement agnostique du dialecte SQL**. Le dialecte (MySQL/MariaDB) ne borne que deux choses : la génération de **témoin exploitable** et les règles spécifiques au dialecte. Le cœur n'est donc **pas** restreint à MySQL. **Ce qui borne la V1, c'est le nombre de sinks — un seul.** Réalise UJ-1, UJ-2.

**Exigences fonctionnelles :**

#### FR-1 : Identification des sources non fiables
Le **moteur** identifie les **sources non fiables** déclarées par le **paquet de règles** générique, sans configuration utilisateur.
**Conséquences (testables) :**
- Pour chaque superglobale du **paquet de règles** générique, une lecture directe est marquée comme **source non fiable** ; le **corpus de référence** contient au moins un cas par superglobale.
- Une valeur lue depuis un **sink base** et réinjectée dans un autre **sink base** est marquée comme **source non fiable** (taint de second ordre) ; au moins 3 cas annotés au **corpus de référence**.
- `$_SERVER` est traité par clé : les clés contrôlables par le client (`HTTP_*`, `QUERY_STRING`, `REQUEST_URI`, `PATH_INFO`) sont des sources ; les autres non. Cas positifs et négatifs au **corpus de référence**.

#### FR-2 : Propagation interprocédurale
Le **moteur** propage la marque à travers affectations, concaténations, interpolations de chaîne, éléments de tableau, arguments et valeurs de retour de fonctions et de méthodes définies dans le périmètre analysé.
**Conséquences (testables) :**
- Une **source non fiable** passée en argument à une fonction dont le retour atteint un **sink base** produit une **chaîne de propagation** contenant les deux positions et le saut intermédiaire.
- Profondeur d'appel supportée ≥ 5 sauts, vérifiée par un cas dédié du **corpus de référence**.
- `sprintf`, `implode`, `str_replace`, `.` et l'interpolation `"{$x}"` propagent la marque ; chacun a un cas au **corpus de référence**.
- Toute construction non suivie (appel dynamique, `eval`, réflexion, `include` à chemin calculé) est enregistrée dans les **limites d'analyse** et **ne produit jamais d'alerte silencieuse**.
**Hors périmètre :** analyse inter-dépôts, résolution des appels au travers d'un conteneur d'injection de dépendances (→ **limites d'analyse**).

#### FR-3 : Sink base unique — PDO
Le **moteur** reconnaît PDO comme unique **sink base** de la V1.
**Justification du choix de PDO** (et non `mysqli` ou `wpdb`) : (a) c'est l'API base native de PHP, présente sans dépendance ; (b) `mysqli` et `wpdb` sont, en pratique, atteints eux-mêmes majoritairement via des constructions équivalentes, ce qui rend l'extension ultérieure incrémentale et non structurelle ; (c) PDO est le point de passage commun sur lequel repose aussi l'instrumentation d'exécution éventuelle ; (d) **surtout : un sink livré et mesuré vaut mieux que trois annoncés** — c'est la leçon Medoo, tirée puis ignorée dans le document d'intention, ici appliquée.
**Conséquences (testables) :**
- `PDO::query`, `PDO::exec`, et `PDO::prepare` dont l'argument SQL est une expression construite dynamiquement sont des **sinks base**.
- `PDO::prepare` avec littéral SQL constant suivi d'une liaison de paramètres n'est **jamais** un **sink base** atteint : 0 **alerte** sur les cas correctement paramétrés du **corpus de référence**.
- Un appel à `mysqli_query` ou `$wpdb->query` ne produit **aucune alerte** en V1 ; l'outil affiche à la fin du scan une ligne indiquant le nombre d'appels non couverts détectés et le lien vers le ticket de suivi. Pas de silence trompeur.

#### FR-4 : Reconnaissance des assainisseurs
Le **moteur** rompt la propagation sur tout **assainisseur** déclaré.
**Conséquences (testables) :**
- Liaison par `bindValue`, `bindParam`, ou tableau passé à `execute()` : propagation rompue, 0 **alerte**.
- Cast `(int)`, `intval()`, `+0` : propagation rompue pour un usage numérique.
- Comparaison à une **allowlist** fermée (`in_array` sur tableau littéral, `match`/`switch` à cas littéraux) avant usage : propagation rompue. Au moins 5 formes syntaxiques au **corpus de référence**.
- `PDO::quote` est reconnu comme **assainisseur partiel** : la propagation est rompue pour un contexte de valeur entre quotes, **pas** pour un identifiant ou un fragment de structure (`ORDER BY`, nom de table). Les deux cas sont au **corpus de référence**, avec verdicts opposés.
- `addslashes`, `mysql_real_escape_string`, et tout échappement no-op ne sont **pas** des **assainisseurs**. Cas dédié au **corpus de référence**, en écho direct à §4.2.

#### FR-5 : Témoin de propagation (niveau 1, par défaut)
Toute **alerte** publiée porte un **témoin de propagation** : la **chaîne de propagation** complète, reproductible, **sans charge utile**.
**Conséquences (testables) :**
- Chaque **alerte** de la sortie contient ≥ 1 position source, ≥ 1 position sink, et la liste ordonnée des sauts.
- Deux exécutions successives sur le même commit produisent des **chaînes de propagation** identiques (déterminisme) — vérifié par un test de non-régression byte-à-byte sur la sortie normalisée.
- Aucune sortie de niveau 1 ne contient de littéral d'injection (`' OR 1=1`, `UNION SELECT`, `;--`) : test automatisé qui recherche ces littéraux dans les artefacts de niveau 1 et échoue s'il en trouve un.

#### FR-6 : Témoin exploitable (niveau 2, opt-in)
Sur activation explicite, le **moteur** génère un **témoin exploitable** dans une **sortie séparée**.
**Conséquences (testables) :**
- Nécessite un drapeau explicite (`--witness=exploitable`) **et** une confirmation dans un fichier de configuration local. L'un sans l'autre échoue avec un message expliquant la responsabilité encourue.
- La sortie de niveau 2 est écrite dans un fichier distinct, **jamais** dans le rapport autoportant, **jamais** dans le commentaire de PR, **jamais** dans SARIF.
- Le fichier produit porte un en-tête d'avertissement et est ajouté automatiquement au `.gitignore` du dépôt analysé s'il n'y figure pas.
- L'action GitHub **refuse** l'activation du niveau 2 : un test vérifie que l'action échoue au démarrage si la configuration demande `exploitable`. Motivation : un artefact de CI contenant des charges armées contre du code tiers est un passif de responsabilité (objection 18).
**Hors périmètre :** exécution du témoin, connexion à une base réelle, `sqlmap`-like.

#### FR-7 : Invariant « pas d'alerte sans témoin »
Aucun constat sans **témoin** de niveau 1 n'est publié comme **alerte**.
**Conséquences (testables) :**
- Un constat sans **chaîne de propagation** complète est émis dans le canal **`suspicions`**, **désactivé par défaut** (`--include-suspicions` pour l'activer).
- Les **suspicions** n'entrent ni dans le score, ni dans le code de sortie, ni dans le calcul de **FP/10 kLOC**.
- Test d'invariant dans la suite CI du projet : toute **alerte** sans chaîne fait échouer la construction de `sqlguard` lui-même.

#### FR-18 : Surface programmatique du moteur
Le **moteur** est consommable comme bibliothèque, indépendamment de la CLI.
**Conséquences (testables) :**
- La CLI, l'action GitHub et un consommateur de test tiers utilisent la même surface publique, sans duplication de logique d'analyse (vérifié par absence de dépendance du moteur vers les paquets d'interface).
- La surface accepte un périmètre de fichiers, un **paquet de règles**, et retourne une collection d'**alertes** et de **suspicions** structurées.
- Contrat détaillé au §14.

### 7.2 Mesure : corpus de référence, comparaison et budget de faux positifs

**Description :** La variable de survie d'un linter de sécurité n'est pas la détection, c'est le taux de faux positifs — et le zéro-configuration est précisément le réglage qui les maximise. Cette fonctionnalité n'est pas un outil de développement interne : c'est un **livrable public** (A-1, A-2, A-5) et la condition de la revendication du §3.4.

#### FR-13 : Corpus de référence figé, harnais de mesure et comparaison publiée
Le projet publie un **corpus de référence** annoté et figé, et un harnais qui calcule **rappel** et **FP/10 kLOC** pour `sqlguard`, Psalm et Semgrep dans leurs configurations par défaut respectives.
**Conséquences (testables) :**
- Le **corpus de référence** est versionné, contient ≥ 150 cas annotés dont ≥ 40 % de cas **négatifs** (code sûr qui ne doit produire aucune **alerte**), et inclut du legacy non typé réel sous licence permissive.
- Le **corpus de référence** est **gelé** : toute modification exige un numéro de version et une entrée de journal justifiant le changement. Interdiction explicite d'ajouter un cas pour faire passer une mesure.
- Une commande unique (`make bench-quality`) recalcule les trois colonnes de résultats et les écrit dans un fichier de résultats versionné.
- Les résultats sont publiés **y compris les cas où `sqlguard` perd** contre Psalm ou Semgrep.
- Seuils contraignants : ceux de NFR-2, appliqués sans reformulation ni dérogation ; SM-1 les mesure et en fait une porte de publication.
**Hors périmètre :** comparaison contre SonarQube (nécessite une instance ; reporté, Q-6).

#### FR-19 : Étude de mesure directe du stock de dette SQL PHP
Le projet publie une étude mécanique sur un échantillon de **200 dépôts PHP publics** comptant les chaînes de propagation par concaténation vers un **sink base**.
**Conséquences (testables) :**
- La méthode d'échantillonnage, le code de comptage et les données brutes sont publiés et rejouables.
- Le résultat remplace, dans toute communication du projet, les chiffres non sourcés « ≈70 % du web », « WordPress ≈40 % », « 200 k lignes », « part majeure générée par IA ».
- L'étude est publiée **avant ou avec** la première annonce publique de l'outil ; c'est l'actif A-3.
**Note :** cette étude est aussi la seule façon honnête de dimensionner le marché. Si elle montre un stock faible, c'est un critère d'arrêt (§19, K-3), pas un résultat à réinterpréter.

#### FR-20 : Page de provenance et de divulgation
Le dépôt contient une page de provenance divulguant l'historique du query builder `db_query` et du no-op `escape_data()`.
**Conséquences (testables) :**
- Lien depuis le README, au-dessus de la ligne de flottaison (visible sans défilement), non enfoui dans un sous-dossier.
- Contient : ce qu'était le défaut, comment il a été découvert, et le cas de test du **corpus de référence** qui le couvre désormais (FR-4).

### 7.3 Livrables de sortie — hiérarchie corrigée

**Description :** La direction initiale plaçait SARIF comme artefact du jour 1 pour l'affichage dans l'onglet Security de GitHub. **C'est un livrable inadapté à la cible.** L'ingestion SARIF dans l'onglet Security est gratuite sur dépôt **public** ; sur dépôt **privé** — soit exactement le cas du persona primaire J-1 — elle relève de GitHub Advanced Security, payant `[HYP-3]`. Hiérarchie corrigée : **(1) commentaire de PR via action GitHub, (2) rapport autoportant, (3) CLI, (4) SARIF en secondaire.** Réalise UJ-1, UJ-2, UJ-3.

#### FR-8 : CLI zéro-configuration
Un développeur exécute `sqlguard scan <chemin>` et obtient un verdict sans avoir écrit de configuration.
**Conséquences (testables) :**
- Aucune option obligatoire ; aucun fichier de configuration requis pour un premier scan.
- Sortie tenant sur un écran de 40 lignes par défaut : compte d'**alertes** par gravité, les N plus graves avec leur **chaîne de propagation** abrégée, périmètre analysé, et compte des **limites d'analyse**.
- `--format=json` produit une sortie stable et documentée (§14).
- Aucune connexion réseau n'est effectuée pendant un scan (test : exécution en environnement sans réseau réussit à l'identique).

#### FR-9 : Commentaire de PR via action GitHub — livrable primaire
Une action GitHub publie sur la pull request un commentaire listant les **alertes** introduites par le diff.
**Conséquences (testables) :**
- Fonctionne sur dépôt **privé** sans GitHub Advanced Security, avec le seul `GITHUB_TOKEN` par défaut et la permission `pull-requests: write`.
- Un commentaire unique, mis à jour en place à chaque push (pas d'empilement).
- Chaque **alerte** du commentaire porte un lien permanent vers la ligne de code du sink et de la source.
- Le commentaire ne contient **jamais** de **témoin exploitable** (FR-6).
- Sur analyse incomplète ou dépassement du budget de temps : commentaire d'exécution partielle avec périmètre couvert, et **pas** de sortie en échec (FR-12).

#### FR-10 : Rapport autoportant
`sqlguard` produit un **rapport autoportant** HTML unique, et un PDF dérivé.
**Conséquences (testables) :**
- Fichier unique, ouvert sans réseau, sans CDN, sans serveur : test d'ouverture en environnement isolé.
- Contient : version de l'outil, version du **paquet de règles**, hash du commit analysé, périmètre analysé (fichiers inclus/exclus, lignes comptées), **alertes** avec **chaînes de propagation**, et la section **limites d'analyse** en évidence — pas en annexe.
- Satisfait le double lectorat de §5.1 : un développeur y trouve fichier:ligne cliquable, un auditeur y trouve périmètre, version et limites (UJ-3).
- Ne contient **jamais** de **témoin exploitable**.

#### FR-11 : SARIF — artefact secondaire
`sqlguard` peut émettre du SARIF valide.
**Conséquences (testables) :**
- Sortie conforme au schéma SARIF 2.1.0, validée par un validateur de schéma en CI.
- Documentée comme **secondaire**, avec un avertissement explicite dans la documentation : l'affichage dans l'onglet Security de GitHub sur dépôt privé requiert GitHub Advanced Security.
- Aucune fonctionnalité du produit ne dépend de l'ingestion SARIF.
**Note :** `[HYP-3]` à reconfirmer contre la tarification GitHub en vigueur avant publication de la documentation (Q-4).

#### FR-12 : Code de sortie et politique de seuil
La CLI et l'action expriment le verdict par un code de sortie configurable.
**Conséquences (testables) :**
- Par défaut : code 0 si aucune **alerte** de gravité haute ; code 1 sinon. Les **suspicions** n'influencent jamais le code de sortie.
- Mode `--baseline=<fichier>` : n'échoue que sur les **alertes** absentes de la référence, permettant l'adoption sur un legacy déjà en dette.
- Une analyse incomplète (limite de temps, limite de mémoire, erreur de parsing) produit un code de sortie **distinct** (2) qui n'est pas interprétable comme « code sûr ». Test dédié.

#### FR-16 : Configuration optionnelle et paquets de règles
La configuration existe mais n'est jamais obligatoire.
**Conséquences (testables) :**
- Un fichier optionnel permet : exclusions de chemins, sélection de **paquet de règles**, déclaration de **sources non fiables** et d'**assainisseurs** supplémentaires, seuil de code de sortie.
- Le **paquet de règles** générique est actif par défaut ; les paquets d'écosystème sont opt-in.
- Toute mesure publiée du §7.2 est faite en configuration **par défaut** — c'est le sens de la revendication du §3.4.

#### FR-17 : Suppression d'alerte traçable
Un utilisateur peut supprimer une **alerte** individuelle.
**Conséquences (testables) :**
- Suppression par annotation en ligne portant un identifiant d'**alerte** stable et un motif obligatoire ; une suppression sans motif est refusée.
- Les suppressions sont comptées et affichées dans le **rapport autoportant** (une suppression invisible est un mensonge d'audit).
- L'identifiant d'**alerte** est stable au déplacement de lignes (fondé sur un hash structurel, pas sur le numéro de ligne). Test de non-régression sur un fichier reformaté.

### 7.4 Performance : mesure d'abord, budget ensuite

**Description :** Les cibles « < 30 s sur un dépôt inconnu » et « < 2 s en pre-commit » du document d'intention ont été posées **sans aucune mesure**. Elles sont retirées. De plus, une analyse taint **interprocédurale n'est pas incrémentale** sans **résumés de fonction** persistés : un scan sur diff seul doit malgré tout reconstruire le contexte appelant. Conséquence directe et non négociable : **ou bien le cache de résumés est une exigence V1, ou bien le mode pre-commit n'est pas annoncé.**

#### FR-14 : Banc de performance en semaine 1
Avant tout arbitrage de conception lié à la performance, le projet publie des temps observés sur trois dépôts de référence.
**Conséquences (testables) :**
- Trois cibles d'ordre de grandeur : ~20 k, ~200 k, ~2 M lignes de PHP, nommées et publiquement accessibles.
- Métriques relevées : temps mural, pic de mémoire, nombre de fichiers analysés, nombre de **limites d'analyse**.
- Résultats versionnés et rejouables par une commande unique.
- Les budgets du §16 sont écrits **après** cette publication. Un budget écrit avant est un défaut de processus.
**Engagement de processus :** en semaine 1 du développement, avant tout arbitrage de performance, l'auteur exécute le banc sur les trois dépôts de référence (~20 k, ~200 k, ~2 M lignes) et publie les temps observés. Mesurer avant de promettre est la règle ; les budgets de performance du §16 sont écrits **après** cette mesure, jamais avant.

#### FR-15 : Cache de résumés de fonction persisté
Le **moteur** persiste des **résumés de fonction** permettant une réanalyse partielle.
**Conséquences (testables) :**
- Un second scan sans modification de source réutilise le cache : temps mural réduit d'un facteur mesuré et publié (cible à fixer après FR-14).
- Le cache est invalidé par changement de version d'outil, de version de **paquet de règles**, ou de contenu de fichier.
- Un cache corrompu ou de version incompatible provoque un scan complet, **jamais** un résultat partiel silencieux.
**Décision de portée :** si FR-15 n'est pas livré dans la V1, **le mode pre-commit n'est pas annoncé** et seul le mode CI est communiqué. Cette alternative est explicite et acceptable ; ce qui n'est pas acceptable est d'annoncer un pre-commit rapide sans le cache.

### 7.6 Vérification du SQL généré par un agent de codage — voie de distribution, pas produit

**Statut : voie de distribution du moteur, pas produit.** Le hook qui refuse une requête non sûre au moment où un agent l'écrit distribue le **moteur** ; il n'est pas une fonctionnalité vendable en propre. Trois mécanismes d'érosion, tous issus de la revue : (a) il dépend d'API de hook tierces, non contractuelles, modifiables sans préavis ; (b) les éditeurs de CLI de codage internaliseront la fonctionnalité ; (c) les modèles paramètrent déjà de plus en plus par défaut, ce qui érode la valeur avec le temps. Le séquencement est tranché au §20, qui retire par écrit le classement NUF.

**Ce qui reste à `sqlguard` si un fournisseur de CLI livre la fonctionnalité nativement :** le **moteur**, le **corpus de référence**, la mesure comparative, le **témoin**, le mode CI, le **rapport autoportant** et le **débouché d'audit** (UJ-3). Autrement dit : la totalité du périmètre V1. Aucune exigence de la V1 ne dépend de cette voie. C'est la condition qui rend l'incertitude sur cette voie supportable.

---

## 8. Non-objectifs (explicites)

- **Ne pas être un query builder ni une API d'écriture de SQL.** Refus définitif, pas un report. `db_query` cesse d'être le produit et devient au mieux une cible de migration ultérieure.
- **Ne pas concurrencer Doctrine, Eloquent, ni une fonctionnalité intégrée de framework** — contrainte rendue testable au §11.4.
- **Ne pas écrire de parser SQL maison.** Appui sur `nikic/php-parser` pour PHP et sur un parser SQL existant si nécessaire.
- **Ne pas restreindre le cœur à MySQL.** La restriction dialectale était une erreur d'analyse : le taint est agnostique du dialecte. Ce qui borne la V1, ce sont les **sinks**.
- **Ne pas exécuter le code analysé**, ne pas se connecter à une base, ne pas se comporter comme un scanner dynamique.
- **Ne pas livrer de charges utiles armées dans un artefact de CI** (FR-6).
- **Ne pas publier de classement nommé de plugins ou de thèmes tiers** (§11.5).
- **Ne pas devenir un service hébergé, un tableau de bord SaaS ou une plateforme** en V1.
- **Ne pas prétendre remplacer un audit signé.** L'outil produit un travail préparatoire ; la responsabilité professionnelle reste celle de l'auditeur.
- **Ne pas revendiquer que le marché de la vérification est vide.** Interdit dans toute communication.

---

## 9. Périmètre MVP

Ce que ce chapitre tranche : la frontière exacte de la V1, et ce qui en est explicitement exclu.

### 9.1 Dans le périmètre

- **Un seul sink base : PDO** (FR-3). Décision centrale du rétrécissement : trois sinks annoncés (PDO, `mysqli`, `wpdb`) deviennent **un**. Une promesse tenue vaut mieux que dix annoncées.
- **Moteur** de propagation interprocédurale agnostique du dialecte, avec reconnaissance des **assainisseurs** (FR-1, FR-2, FR-4).
- **Témoin de propagation** obligatoire ; **témoin exploitable** en opt-in, sortie séparée, interdit en CI (FR-5, FR-6).
- Invariant « pas d'**alerte** sans **témoin** » + canal **`suspicions`** désactivé par défaut (FR-7).
- **Corpus de référence** figé, harnais de mesure, comparaison publiée contre Psalm et Semgrep (FR-13).
- CLI zéro-configuration (FR-8), **action GitHub avec commentaire de PR** (FR-9, livrable primaire), **rapport autoportant** HTML/PDF (FR-10), SARIF secondaire (FR-11).
- Codes de sortie, baseline, suppression traçable (FR-12, FR-17).
- Banc de performance publié en semaine 1 (FR-14) ; **cache de résumés** (FR-15) **ou** abandon annoncé du mode pre-commit.
- Surface programmatique du **moteur** (FR-18).
- Étude de mesure sur 200 dépôts (FR-19) et page de provenance (FR-20).

### 9.2 Hors périmètre MVP

| Élément | Raison | Échéance |
|---|---|---|
| Sink `mysqli` | Rétrécissement à un sink ; extension incrémentale une fois le socle mesuré | V1.1 |
| Sink `wpdb` (WordPress) | Idem, plus la nécessité d'un **paquet de règles** WordPress | V1.2 |
| Codemod concaténation → requête préparée | L'équivalence sémantique sur du SQL dynamique est le point de faisabilité le plus faible du programme ; exige une stratégie de preuve d'équivalence et des patterns strictement bornés | V2, sous étude préalable |
| Hook de vérification du SQL généré par un agent | Voie de distribution, pas produit ; dépend d'API tierces non contractuelles (§7.6) | Opportuniste, sans engagement |
| Garde-fou d'exécution + politique déclarative (cluster C) | **Conditionné** au test du §11.4, pas seulement reporté | Conditionnel |
| Cartographie PII | Exige la connaissance du schéma de base, indisponible par analyse statique seule ; mécanisme d'acquisition non défini | Non planifié |
| Observatoire WordPress | Passif conditionnel, terrain déjà occupé (§11.5) | Non planifié |
| Niveaux de sécurité progressifs (à la PHPStan) | Utile, mais sans valeur avant qu'un niveau unique soit mesuré et fiable | V1.1 |
| Badge README de sécurité | Porteur pour la viralité, mais un badge posé sur un outil dont le taux de faux positifs n'est pas encore mesuré est un risque de réputation. À revoir après SM-1 |
| Multi-SGBD explicite, PostgreSQL/SQLite dialectal | Le cœur est déjà agnostique ; seules les règles dialectales et la génération de témoin sont concernées | V1.1+ |
| Comparaison contre SonarQube | Nécessite d'opérer une instance | Q-6 |

---

## 10. Exigences non fonctionnelles transverses

| ID | Exigence | Critère de vérification |
|---|---|---|
| **NFR-1** | **Déterminisme.** Deux exécutions sur le même commit, même version d'outil et même **paquet de règles** produisent une sortie identique après normalisation. | Test de non-régression byte-à-byte en CI |
| **NFR-2** | **Budget de faux positifs (contraignant) — énoncé normatif unique du seuil.** En configuration par défaut sur le **corpus de référence** figé : **rappel ≥ 85 % des chaînes de propagation annotées atteignant le sink base PDO, et ≤ 3 FP/10 kLOC**. Toute autre mention du seuil dans ce document renvoie à NFR-2 et ne le reformule pas. Une version qui viole ce seuil **n'est pas publiée**. | `make bench-quality` en porte de publication |
| **NFR-3** | **Invariant du témoin.** Aucune **alerte** publiée sans **témoin** de niveau 1. | Test d'invariant faisant échouer la construction |
| **NFR-4** | **Aucune sortie réseau.** Un scan n'émet aucune requête réseau. | Exécution en environnement isolé du réseau |
| **NFR-5** | **Aucune exécution du code analysé.** Ni `eval`, ni `include`, ni instanciation du code cible. | Revue + test d'intégration sur un fichier à effet de bord observable |
| **NFR-6** | **Échec explicite plutôt que silence.** Toute analyse incomplète produit un code de sortie distinct et une mention dans les **limites d'analyse**. | Tests sur dépassement de temps, de mémoire, et erreur de parsing |
| **NFR-7** | **Observabilité.** `--verbose` expose la file d'analyse, les fonctions résumées, les constructions non suivies et les temps par phase. | Inspection manuelle documentée + test de présence des champs |
| **NFR-8** | **Reproductibilité des mesures publiées.** Toute figure publiée est régénérable par un tiers avec une commande unique. | Rejeu par un contributeur externe avant l'annonce |
| **NFR-9** | **Installation.** `composer require --dev` puis une commande, sans service, sans base, sans démon. | Test d'installation en conteneur vierge |
| **NFR-10** | **Budgets de performance** — voir §16. Aucun chiffre avant FR-14. | — |

---

## 11. Contraintes et garde-fous

Ce que ce chapitre tranche : les limites de sûreté, de vie privée et de coût, et le test opérationnel qui rend la contrainte fondatrice vérifiable.

### 11.1 Sûreté (safety) et responsabilité

- Deux niveaux de **témoin** strictement séparés (FR-5, FR-6). Le niveau 1 est le défaut ; le niveau 2 exige double opt-in, sort dans un fichier distinct, est ignoré par le rapport et le commentaire de PR, et est **refusé par l'action GitHub**.
- Motif : un artefact de CI contenant des charges armées contre du code tiers est archivé, partagé, indexé et potentiellement réutilisé de façon offensive. Le projet ne veut pas être la source de cet artefact.
- La documentation ne fournit aucun mode d'emploi d'exploitation ; elle décrit des chaînes de propagation et des correctifs.
- Aucune divulgation par le projet de vulnérabilité trouvée dans du code tiers sans le consentement explicite du propriétaire. Politique de divulgation à écrire avant la première publication (Q-7).

### 11.2 Vie privée

- Le code analysé **ne quitte jamais la machine** (NFR-4). Pas de télémétrie en V1, y compris anonyme — un outil de sécurité qui phone home a un problème d'adoption structurel.
- Conséquence assumée : le projet ne disposera pas de données d'usage. L'anti-métrique du §19 en tient compte.
- Le **rapport autoportant** contient du code source extrait du dépôt analysé : la documentation avertit qu'il hérite du niveau de confidentialité du code, et l'outil ne le transmet nulle part.

### 11.3 Coût

- Coût d'exécution utilisateur : zéro service, zéro dépendance payante. Fonctionne dans les minutes CI déjà payées.
- **Contrainte de coût structurante :** le livrable primaire ne doit pas dépendre de GitHub Advanced Security (§7.3).
- Coût projet : temps du mainteneur uniquement `[HYP-5]`. Aucun budget d'infrastructure n'est supposé disponible ; toute fonctionnalité exigeant un service hébergé est hors périmètre par contrainte de coût, pas par choix produit.

### 11.4 Contrainte fondatrice, rendue testable — et le cas du garde-fou d'exécution

La contrainte « ne jamais concurrencer une fonctionnalité intégrée de framework » était inopérante car non testable. Test opérationnel désormais applicable à toute fonctionnalité candidate :

> **Une fonctionnalité candidate est exclue si un développeur peut obtenir 80 % de son résultat avec une fonctionnalité documentée de Laravel ou de Symfony en moins de 50 lignes de code applicatif.**

Application aux clusters :

| Fonctionnalité | Équivalent framework | Verdict |
|---|---|---|
| Analyse statique taint vers sink base PDO | Aucun | **Passe** — retenue |
| **Garde-fou d'exécution / proxy PDO + politique déclarative (cluster C)** | Middleware et écouteur de requêtes Laravel ; écouteur Doctrine/Symfony. Un « refuser tout `DELETE` sans `WHERE` » s'écrit en écouteur, largement sous 50 lignes | **Échoue le test** |

**Cas du garde-fou d'exécution (cluster C).** Un proxy PDO doublé d'une politique déclarative interceptant les requêtes à l'exécution recoupe des fonctionnalités **déjà intégrées aux frameworks** : middleware et écouteurs de requêtes Laravel, écouteurs Doctrine/Symfony. La direction initiale plaçait ce cluster en 2028 tout en posant comme contrainte fondatrice de « ne jamais concurrencer une fonctionnalité intégrée de framework » : contradiction réelle, tranchée ici par le test ci-dessus. Le cluster C n'est pas une fonctionnalité de la V1 et ne porte aucune FR ; son statut est **hors périmètre V1 et conditionné**, pas simplement reporté.

**Décision :** le cluster C est **retiré de la feuille de route engagée**. Il n'est réintroduit que si l'une de ces deux conditions est écrite et démontrée : (1) une variante hors-framework (legacy PHP sans framework, où aucun écouteur n'existe) est explicitement circonscrite, et le test ci-dessus lui est réappliqué ; ou (2) la fusion des traces statique et d'exécution démontre un gain de rappel mesuré sur le **corpus de référence** qu'aucun écouteur de framework ne procure. Sans l'une des deux, le cluster reste hors périmètre. La contradiction du document d'intention est ainsi tranchée, pas contournée.

### 11.5 Observatoire WordPress — passif conditionnel

Reclassé d'« actif futur » en **passif conditionnel**. Le terrain est déjà occupé par Patchstack, WPScan et Wordfence, qui disposent d'équipes, de flux CVE et de relations avec les éditeurs de plugins. Publier un classement nommé de sécurité de plugins tiers expose à des contestations juridiques et de réputation sans capacité de défense.

**Version dégradée, seule autorisée :** **statistiques agrégées anonymes** (par exemple « X % des plugins de l'échantillon présentent au moins une chaîne de propagation vers un sink base »), **jamais** de classement ni de nommage.

L'observatoire n'est pas à la feuille de route et ne figure dans aucune communication comme un plan. Ses six préconditions cumulatives, toutes requises, sont listées au registre des incertitudes (§21, Q-12).

---

## 12. Modèle économique et frontière open/commercial

Le document d'intention n'en nommait aucun, alors qu'il identifiait des personas solvables (RSSI, DPO, équipe en audit). Ne pas tracer la frontière maintenant conduit à un changement de licence rétroactif, qui est la manière la plus fiable de détruire une communauté naissante.

**Modèle nommé, V1 : aucun revenu. Projet de portefeuille, budget = temps du mainteneur, 10 h/semaine sur 36 mois** `[HYP-4]` `[HYP-5]`. Aucune monétisation n'est planifiée, aucune promesse de gratuité perpétuelle au-delà de la frontière ci-dessous n'est faite.

**Frontière tracée maintenant, engagée :**

| Zone | Contenu | Licence / engagement |
|---|---|---|
| **Ouvert, définitivement** | **Moteur**, **paquets de règles**, CLI, action GitHub, **rapport autoportant**, format SARIF, **corpus de référence**, harnais de mesure | Licence permissive (MIT ou Apache-2.0, Q-5), sans clause de réciprocité, sans CLA de cession permettant une relicence unilatérale |
| **Zone commerciale possible, jamais rétroactive** | Agrégation multi-dépôts hébergée, tableau de bord d'organisation, support contractuel, formation, paquets de règles propriétaires par écosystème | Rien de ce qui a été publié sous licence ouverte ne peut y migrer |
| **Interdit** | Relicencier une fonctionnalité déjà publiée en ouvert ; dégrader le **moteur** ou le budget de faux positifs pour vendre une édition supérieure ; conditionner la sécurité par défaut à un paiement | Engagement écrit dans le dépôt |

Choix de licence à arrêter avant le premier commit public (Q-5) — Apache-2.0 offre une clause de brevet explicite, ce qui est pertinent pour un outil de sécurité.

---

## 13. Porte de validation avant le code

Le document d'intention comportait **zéro validation externe** : les jobs du §5.1 sont analytiques, produits par l'auteur en dialogue avec lui-même. Cette porte est **bloquante** : elle précède l'écriture du **moteur**.

### 13.1 Entretiens — 8 à 12, composition imposée

| Segment | Nombre | Question de décision à trancher |
|---|---|---|
| Leads techniques sur PHP legacy | 2 | Existe-t-il une occasion réelle et datée où ils ont eu besoin de prouver l'absence d'injection ? Qu'ont-ils fait à la place ? |
| Mainteneurs WordPress | 2 | Le sink `wpdb` est-il la condition d'entrée, ou PDO suffit-il à démarrer ? |
| Auditeurs / consultants en sécurité | 2 | Un **rapport autoportant** réduit-il leur temps de mission, ou crée-t-il du travail de vérification supplémentaire ? Quelles **limites d'analyse** exigent-ils de voir ? |
| Utilisateurs d'agents de codage | 2 | Relisent-ils réellement le SQL généré ? La voie §7.6 est-elle un besoin ou une projection ? |
| Optionnels (RSSI, DPO) | 0-4 | Uniquement si les 8 premiers font émerger un signal |

**Règle contraignante :** un job du §5.1 ne peut porter une FR de la V1 qu'après passage de `hypothèse` à `validé par N entretiens`, avec N ≥ 2. Si à l'issue de la porte J-1 et J-2 restent en `hypothèse`, la V1 n'est pas lancée dans sa forme actuelle.

### 13.2 Preuve de demande passive

Au moins **une** preuve non déclarative, obtenue sans démarchage :

- Page de projet décrivant le problème et la revendication du §3.4, avec inscription à un avis de disponibilité — seuil : **≥ 40 inscriptions en 30 jours** sans achat d'audience `[HYP-6]`.
- **ou** publication de l'étude FR-19 seule et mesure de la reprise organique (issues, mentions, demandes de l'outil).
- **ou** ≥ 3 demandes spontanées non sollicitées d'accès anticipé.

Une preuve déclarative (« oui, ce serait utile » en entretien) **ne compte pas** comme preuve de demande passive.

---

## 14. Contrats d'API et surface publique

Le versionnage sémantique n'a de sens que si la surface est nommée. Quatre surfaces publiques, et rien d'autre.

| Surface | Contenu | Stabilité |
|---|---|---|
| **S-1 — CLI** | Noms de commandes, options documentées, **codes de sortie**, format lisible | Codes de sortie et options : **stables**, changement = majeure. Mise en forme du texte lisible : **non stable** |
| **S-2 — Sortie machine JSON** | Schéma versionné : `alerts[]` (id, severity, sink, chain[], rule_pack, suppressed), `suspicions[]` (uniquement si activé), `analysis_limits[]`, `scope`, `tool_version`, `rule_pack_version`, `commit` | **Stable**, schéma publié et validé en CI. Ajout de champ = mineure ; retrait/renommage/changement de sémantique = majeure |
| **S-3 — SARIF** | SARIF 2.1.0 ; identifiants de règles stables | Stable, borné par le schéma SARIF. Secondaire (FR-11) |
| **S-4 — Bibliothèque du moteur (PHP)** | Classes et interfaces marquées `@api` uniquement. Périmètre : point d'entrée d'analyse, définition de **paquet de règles**, objets **alerte**/**suspicion**/**chaîne de propagation** | Seuls les éléments `@api` sont publics. Tout le reste est `@internal` et modifiable en version mineure |

**Règles de contrat, engagées :**
- Un identifiant d'**alerte** (FR-17) est stable pour un même défaut au travers des versions mineures. Le rendre instable est une rupture, parce que les suppressions des utilisateurs en dépendent.
- La sévérité d'une règle existante ne change pas en version mineure.
- **Rendre une règle plus sensible (donc générer plus d'alertes) est traité comme une rupture** : une CI qui passait au vert échouerait. Ces changements sortent en majeure, ou derrière un opt-in.
- Le **paquet de règles** générique est versionné indépendamment de l'outil, et sa version figure dans toute sortie.

---

## 15. Versionnage et politique de dépréciation

- **SemVer**, appliqué aux surfaces S-1 à S-4 telles que définies au §14 — pas au comportement interne du **moteur**.
- **0.x** jusqu'au passage de NFR-2 sur trois versions consécutives. Pas de 1.0.0 avant que le budget de faux positifs soit tenu de façon reproductible : un outil de sécurité en 1.0 avec un taux de faux positifs non mesuré est une promesse fausse.
- **Dépréciation :** annonce dans les notes de version + avertissement en exécution ; retrait au plus tôt à la majeure suivante, et jamais moins de **6 mois** après l'annonce.
- **Paquets de règles :** une nouvelle règle arrive désactivée par défaut, est mesurée sur le **corpus de référence**, puis activée par défaut au passage de version du paquet — jamais dans un correctif.
- **Politique de support :** la dernière majeure reçoit fonctionnalités et correctifs ; la précédente reçoit les correctifs de sécurité pendant 6 mois `[HYP-5]` — engagement à réviser à la baisse si la capacité du mainteneur est inférieure à l'hypothèse.
- Journal des changements tenu par entrée, avec mention explicite de tout changement affectant le nombre d'**alertes** produites.

---

## 16. Budgets de performance

**Aucun budget n'est fixé dans ce document.** C'est une décision, pas un oubli. Les cibles « < 30 s » et « < 2 s » ont été retirées faute de mesure.

**Processus contraignant :**

1. **Semaine 1** — FR-14 : mesurer et publier les temps observés sur ~20 k / ~200 k / ~2 M lignes.
2. **Semaine 2** — écrire les budgets `PB-1..PB-n` dans ce document, dérivés des temps observés et non d'une intuition. Forme attendue : `PB-1 : scan complet ≤ <valeur mesurée × marge> sur le dépôt de référence 200 kLOC, en mémoire ≤ <valeur>`.
3. **Ensuite** — toute régression au-delà du budget fait échouer la CI du projet.

| Élément | Statut |
|---|---|
| Temps de scan complet, 3 tailles de dépôt | **À mesurer** (FR-14), budget écrit après |
| Pic de mémoire, 3 tailles | **À mesurer** (FR-14) |
| Gain du **cache de résumés de fonction** | **À mesurer** après FR-15 |
| Scan incrémental sur diff / mode pre-commit | **Conditionnel à FR-15.** Sans cache de résumés persistés, l'analyse interprocédurale n'est pas incrémentale : le mode pre-commit est alors **abandonné et non annoncé**, et seul le mode CI est communiqué |
| Budget de temps CI | L'action ne doit **jamais** échouer pour dépassement de temps : elle produit un résultat partiel explicite (FR-9, FR-12, NFR-6) |

---

## 17. Cibles de langage/runtime et politique de dépendances

| Élément | Décision |
|---|---|
| **Runtime de l'outil** | PHP 8.1+ pour exécuter `sqlguard` (fonctionnalités de langage et écosystème d'outillage) `[HYP-7]` |
| **Code analysé** | PHP **5.6 à 8.x**. Non négociable : la cible est le legacy. Un analyseur qui ne parse pas du PHP 5.6 rate son marché. Le **corpus de référence** contient des cas 5.6 et 7.x |
| **Dépendances de production** | Minimales et justifiées une par une. Attendues : `nikic/php-parser` (parsing PHP), un composant de console. Tout ajout exige une justification écrite dans le journal des décisions |
| **Parser SQL** | **Aucun en V1** si possible : la détection taint-vers-sink ne l'exige pas. Un parser SQL n'est requis que pour les règles dialectales et la génération de **témoin exploitable** — dans ce cas, un parser existant, jamais un parser maison |
| **Politique de dépendance transitive** | Pas de framework complet en dépendance de production. Un outil d'analyse installé en `--dev` chez autrui ne doit pas provoquer de conflit de résolution Composer |
| **Distribution** | Paquet Composer `--dev` **et** PHAR autoportant. Le PHAR est requis pour le legacy dont les contraintes Composer sont irréconciliables — cas fréquent chez la cible primaire `[HYP-8]` |
| **Plateformes** | Linux et macOS pris en charge et testés en CI ; Windows au mieux, non testé en V1 |
| **Politique de sécurité des dépendances** | Versions épinglées pour le PHAR publié ; construction reproductible et vérifiable |

---

## 18. Métriques de succès

**Primaires**

- **SM-1 — Qualité de détection en configuration par défaut.** Sur le **corpus de référence** figé : **le seuil de NFR-2 est tenu** (énoncé normatif unique, non reformulé ici), **et** résultat au moins égal à Psalm et Semgrep en configuration par défaut sur le sous-corpus de code non typé. Mesuré par `make bench-quality`, publié. Valide FR-1, FR-2, FR-4, FR-13 ; conditionne NFR-2. **Porte de publication : une version qui échoue n'est pas publiée.**
- **SM-2 — Couverture par témoin : 100 %.** Part des **alertes** publiées portant un **témoin** de niveau 1. Cible : **100 %, sans exception.** Valide FR-5, FR-7.
- **SM-3 — Adoption réelle, non les téléchargements.** Nombre de dépôts distincts exécutant `sqlguard` **en CI** avec une politique de code de sortie active, attesté par des preuves observables (fichiers de workflow publics, issues, témoignages nominatifs) : **≥ 10 à 6 mois**, dont **≥ 3 sur du legacy non typé de plus de 100 kLOC**. Valide FR-8, FR-9, FR-12.
- **SM-4 — Porte de validation franchie.** 8 à 12 entretiens réalisés selon la composition du §13.1, **et** J-1 et J-2 passés à `validé par N ≥ 2 entretiens`, **et** ≥ 1 preuve de demande passive obtenue. Cible : franchi **avant** la première ligne du **moteur**. Valide la légitimité de tout le §7.

**Secondaires**

- **SM-5 — Confiance dans l'artefact d'audit.** ≥ 2 auditeurs déclarant, après lecture d'un **rapport autoportant** réel, qu'il réduit leur temps de mission, et nommant les **limites d'analyse** qu'ils exigent. Valide FR-10, UJ-3.
- **SM-6 — Reproductibilité par un tiers.** ≥ 1 contributeur externe régénère les chiffres publiés du SM-1 sans assistance. Valide NFR-8, FR-13.
- **SM-7 — Vitesse de réponse communautaire.** Médiane de première réponse aux issues ≤ 72 h sur les 3 premiers mois. Traité comme un engagement opérationnel (l'échec de Propel est attribué à ce facteur) et non comme un détail. Contraint par `[HYP-5]`.
- **SM-8 — Réutilisation du moteur.** Un second consommateur du **moteur** existe sans dupliquer la logique d'analyse. Valide FR-18.
- **SM-9 — Actif de crédibilité publié.** L'étude FR-19 est publiée et citée ≥ 3 fois par des tiers. Valide FR-19.

**Contre-métriques (à ne pas optimiser)**

- **SM-C1 — Téléchargements Packagist.** **Les téléchargements ne sont pas de l'adoption.** Les miroirs de CI, les caches et la curiosité les gonflent sans aucun dépôt protégé. Contrebalance SM-3 : si les téléchargements montent et que SM-3 stagne, le signal est négatif, pas positif. Interdiction de citer un chiffre de téléchargements comme preuve d'adoption dans une communication du projet.
- **SM-C2 — Nombre d'alertes produites.** Un scanner qui trouve plus n'est pas meilleur. Contrebalance SM-1 : toute hausse du volume d'alertes doit être accompagnée de sa mesure de faux positifs, ou elle est traitée comme une régression.
- **SM-C3 — Étoiles GitHub.** Non corrélées à l'usage en CI. Contrebalance SM-3.
- **SM-C4 — Nombre de règles / de sinks.** Le rétrécissement à un sink est délibéré (§9.1). Compter les sinks incite à réintroduire la dispersion. Contrebalance SM-8.
- **SM-C5 — Volume de suspicions.** Le canal **`suspicions`** ne doit jamais servir à gonfler la valeur perçue ; il est désactivé par défaut et hors métriques.

---

## 19. Critères d'arrêt — pré-enregistrés

> ### ⚠ K-1 LEVÉE PAR DÉCISION DE L'AUTEUR — 2026-09-10
>
> Stéphane a instruit d'ignorer l'exigence d'entretiens humains et de poursuivre le
> développement. **K-1 n'est donc pas franchie : elle est levée.** La distinction est
> importante et doit survivre dans ce document :
>
> - **Aucun entretien n'a eu lieu.** Les 8 jobs du §5.1 restent `hypothèse`, sans exception.
> - La règle contraignante du §13.1 (« un job ne peut porter une FR de la V1 qu'après passage
>   à `validé par N entretiens` ») est **suspendue**, non satisfaite.
> - Le risque que le constat 9 de la revue adverse décrivait — construire pour un marché non
>   validé — est **accepté sciemment**, pas éliminé.
> - **K-2 et K-3 restent actifs.** Ce sont désormais les seuls garde-fous : si la comparaison
>   mesurée contre Psalm et Semgrep (SM-1) échoue, la revendication du §3.4 tombe et l'issue
>   prévue s'applique.
> - Le protocole d'entretien (`docs/validation/protocole-entretiens.md`) reste figé et
>   utilisable si la validation est reprise plus tard.
>
> Aucune communication publique ne doit présenter la V1 comme validée par des utilisateurs.

> **Recalibrage du 2026-09-10.** K-2 et K-3 portaient T+4 et T+9 mois, calculés sous l'hypothèse d'un budget de 6 mois. La capacité réelle déclarée au §4.4 est de **10 h/semaine**, soit ≈ 43 h/mois : à ce rythme, les 4 premiers mois n'offrent que ≈ 170 h, dont ≈ 55 h consommées par S0 — insuffisant pour livrer S1 puis S2, dont l'effort estimé cumulé est de ≈ 530 h. Les échéances passent donc à **T+14** et **T+20 mois**.
>
> Ce report est un **desserrement d'échéance, pas un affaiblissement de seuil** : aucun des trois seuils falsifiables n'est modifié, et K-1 reste à T+6 semaines puisqu'il ne dépend que d'entretiens. Une date tenable est une date qui peut réellement déclencher un abandon ; une date intenable se renégocie au lieu de trancher, ce qui est exactement le défaut que ces critères existent pour empêcher.

Trois seuils datés et falsifiables, écrits **avant** l'investissement pour ne pas être réinterprétés après. Chacun porte une décision explicite d'**abandon ou de pivot**, pas une invitation à « ajuster la stratégie ».

| ID | Date butoir | Seuil falsifiable | Décision si non atteint |
|---|---|---|---|
| **K-1** | **T+6 semaines** (avant l'écriture du moteur) | SM-4 franchi : 8 entretiens minimum, J-1 **et** J-2 validés par ≥ 2 entretiens chacun, **et** ≥ 1 preuve de demande passive | **Abandon du produit.** Pivot autorisé : publier l'étude FR-19 comme travail isolé et clore le projet. Aucune V1 |
| **K-2** | **T+14 mois** (publication de la V1) | SM-1 atteint **et** au moins égal à Psalm et Semgrep en configuration par défaut sur le sous-corpus non typé | **Abandon en tant que produit autonome.** Pivot autorisé : publier le **corpus de référence** et le harnais comme contribution ouverte, et proposer les règles en amont à Psalm/Semgrep plutôt que maintenir un outil concurrent inférieur |
| **K-3** | **T+20 mois** | SM-3 atteint : ≥ 10 dépôts distincts en CI avec politique de code de sortie active, dont ≥ 3 sur du legacy > 100 kLOC — **preuves observables exigées, téléchargements explicitement exclus** | **Arrêt du développement de fonctionnalités.** Passage en maintenance de sécurité seule, archivage annoncé à 12 mois si SM-3 reste sous le seuil. Aucun sink supplémentaire, aucun codemod, aucun cluster C |

**Anti-métrique gouvernant les trois :** aucun de ces seuils ne peut être déclaré atteint sur la base de téléchargements, d'étoiles, de trafic ou d'impressions (SM-C1, SM-C3). Seules des preuves d'usage observables comptent.

**Règle de procédure :** franchir un critère d'arrêt exige une décision écrite et datée dans le dépôt. Reporter une date butoir est autorisé **une seule fois**, de 4 semaines au maximum, avec un motif écrit. Au-delà, le critère est considéré non atteint.

---

## 20. Feuille de route

La justification par le score NUF est **retirée** : elle contredisait le classement qu'elle invoquait (le « gagnant » était placé en année 2 tandis que la V1 était un cluster moins bien noté). Le critère réel est unique et suffit :

> **A est le moteur. Tous les autres clusters sont des consommateurs ou des extensions du moteur. Donc A est premier, et rien ne se lance avant qu'il soit mesuré.**

| Séquence | Contenu | Condition d'entrée |
|---|---|---|
| **S0 — Validation** | §13 : entretiens + preuve de demande passive ; étude FR-19 | — |
| **S1 — Mesure** | FR-14 (banc), FR-13 (corpus de référence + comparaison) | **K-1 franchi** |
| **S2 — V1** | Un sink base (PDO), témoin niveau 1, CLI, action GitHub + commentaire de PR, rapport autoportant, SARIF secondaire | SM-1 atteint sur le corpus de référence |
| **S3 — V1.1 / V1.2** | Sink `mysqli`, puis sink `wpdb` + paquet de règles WordPress ; niveaux de sécurité progressifs | **K-3 franchi** ; NFR-2 tenu sur 3 versions |
| **S4 — Étude codemod** | Étude d'équivalence sémantique sur un jeu de patterns strictement borné, **avant** tout engagement de livraison | S3 stabilisé |
| **Opportuniste, sans engagement** | Voie de vérification du SQL généré par un agent (§7.6) | Ne bloque et ne conditionne rien |
| **Conditionnel** | Garde-fou d'exécution (cluster C) — seulement si §11.4 est satisfait par écrit | Test de §11.4 |
| **Non planifié** | Cartographie PII ; observatoire WordPress (§11.5, 6 préconditions cumulatives) | — |

La capacité est désormais renseignée — **10 h/semaine sur 36 mois** (§4.4) — et le calendrier indicatif par étape en découle. Les étapes restent néanmoins **conditionnées par jalons, pas par dates** : une condition d'entrée non satisfaite reporte l'étape, quel que soit le calendrier. Les seules dates engageantes du document sont les trois jalons K du §19. Le volume total (≈ 1 560 h) rend S3 et S4 atteignables, ce qu'un budget de 6 mois excluait ; il ne les garantit pas.

---

## 21. Registre des incertitudes

Registre unique des questions ouvertes et des hypothèses. Ce document repose principalement sur des hypothèses non validées : aucune ne doit être traitée comme un fait avant la porte du §13, et la plus grande partie de ce PRD repose sur des hypothèses non validées, ce qui est volontairement visible.

| ID | Type | Origine | Énoncé | Comment on la lève | Échéance |
|---|---|---|---|---|---|
| **Q-1** | Question | En-tête, §0 | **Marque.** Nom `sqlguard` **acté** (2026-09-10) ; disponibilité Packagist vérifiée. Reste ouvert : **antériorité de marque** | Recherche INPI/EUIPO/USPTO et vérification npm/PyPI/GitHub. Ne bloque ni K-1 ni le développement ; bloque uniquement la communication externe et le dépôt du paquet | Avant l'annonce publique |
| **Q-2** | Question | §4.4 | **Capacité du mainteneur. ~~Ouverte~~ → CLOSE (2026-09-10).** 10 h/semaine sur 36 mois | Close par déclaration de l'auteur ; le tableau d'effort du §4.4 et les dates recalculées du §19 en découlent. Voir HYP-5, qui reste ouverte | Close (2026-09-10) |
| **Q-3** | Question | §4.4 | **Co-mainteneur / succession.** Facteur bus = 1, objection d'adoption pour un outil de sécurité | Identifier un co-mainteneur ou publier une politique de succession (§4.4) | Avant l'annonce publique |
| **Q-4** | Question | §2, §7.3, FR-11 | **Tarification GitHub pour l'ingestion SARIF sur dépôt privé.** `[HYP-3]` à reconfirmer contre la tarification en vigueur | Vérifier la documentation GitHub ; la réponse ne change pas la hiérarchie du §7.3 mais change la phrase exacte à écrire. Lève HYP-3 | Avant publication de la documentation FR-11 |
| **Q-5** | Question | §12 | **Licence.** MIT ou Apache-2.0. Apache-2.0 apporte une clause de brevet explicite, pertinente pour un outil de sécurité | Arbitrage écrit de l'auteur (§12) | Avant le premier commit public |
| **Q-6** | Question | §7.2, FR-13 | **Comparaison contre SonarQube.** Exige d'opérer une instance | Décider après SM-1 si le coût est justifié ; en attendant, la revendication du §3.4 ne mentionne que Psalm et Semgrep | Après SM-1 |
| **Q-7** | Question | §11.1 | **Politique de divulgation responsable.** Que fait le projet quand son propre scan, exécuté sur du code tiers, trouve une vulnérabilité réelle ? | Écrire la politique (§11.1) | Avant toute exécution sur du code tiers, et a fortiori avant FR-19 |
| **Q-8** | Question | §13.1, FR-3 | **Le sink `wpdb` est-il la véritable condition d'entrée ?** Si les entretiens montrent que le marché legacy accessible est majoritairement WordPress, le choix de PDO comme sink base unique (FR-3) doit être rejugé | Question explicite aux 2 mainteneurs WordPress du §13.1. Lève partiellement HYP-9 | Réponse avant S2 |
| **Q-9** | Question | FR-15 | **Le cache de résumés est-il livrable dans la V1 ?** L'alternative est binaire et déjà écrite (FR-15) : cache livré, ou pre-commit non annoncé | Trancher après FR-14 | Après FR-14 |
| **Q-10** | Question | §3.2 | **Quelle explication du zéro-traction de la niche ?** Cinq dépôts, le plus gros à 15 étoiles, un à 0. L'hypothèse par défaut est l'absence de demande | Poser la question directement en entretien ; contacter si possible l'auteur du dépôt à 0 étoile — c'est l'information la moins chère et la plus décisive du projet. Lève HYP-11 | Porte §13 (K-1) |
| **Q-11** | Question | FR-3 | **Le reste de la couverture de sink en V1 est-il acceptable pour le persona primaire ?** Un dépôt legacy peut mélanger PDO et `mysqli` ; un scan qui ne couvre qu'un sink base peut être perçu comme une fausse assurance | FR-3 impose déjà l'affichage du nombre d'appels non couverts ; valider en entretien que cette mention suffit à ne pas induire en erreur. Lève partiellement HYP-9 | Porte §13 (K-1) |
| **Q-12** | Question | §11.5 | **Observatoire WordPress : avis juridique et préconditions cumulatives.** Les six préconditions, toutes requises avant que l'observatoire figure dans une communication comme un plan : (1) NFR-2 tenu sur trois versions consécutives ; (2) sink `wpdb` livré et mesuré (V1.2) ; (3) politique de divulgation publiée et éprouvée sur ≥ 10 cas réels (Q-7) ; (4) co-mainteneur ou plan de succession en place (§4.4) ; (5) avis juridique écrit sur la publication de statistiques agrégées — non instruit ; (6) un financement ou un temps dédié identifié, l'échelle de l'index WordPress n'étant pas soutenable en heures résiduelles | Hors périmètre jusqu'à ce que les préconditions 1 à 4 soient réunies ; l'avis juridique (précondition 5) est alors commandé | Non planifié |
| **HYP-1** | Hypothèse | §1 | La douleur dominante porte sur la confiance dans le SQL déjà écrit, non sur son écriture. Non validée en externe (les 8 jobs du §5.1 sont analytiques) | Entretiens §13.1 ; SM-4 | Porte §13 (K-1) |
| **HYP-2** | Hypothèse | §2 | Le volume de code d'accès base généré par un agent excède la capacité de relecture. Rétrogradée depuis « une part majeure du code est générée par IA », non sourcée | Entretiens (2 utilisateurs d'agents) ; aucune décision V1 n'en dépend | Porte §13 (K-1) |
| **HYP-3** | Hypothèse | §2, §7.3, FR-11 | L'ingestion SARIF dans l'onglet Security est gratuite sur dépôt public et payante (GHAS) sur dépôt privé | Q-4, vérification de la tarification en vigueur | Avant publication de la documentation FR-11 |
| **HYP-4** | Hypothèse | §4.1, §12 | Aucun financement, aucun temps salarié dédié ; projet de portefeuille | Confirmation de l'auteur | Continu |
| **HYP-5** | Hypothèse | §4.4, §11.3, §12, §15, §19, SM-7 | Capacité du mainteneur : **10 h/semaine sur 36 mois** (≈ 43 h/mois, ≈ 1 560 h), déclarée le 2026-09-10. Intention, non fait établi | Révision à chaque jalon K ; un écart soutenu impose un nouveau rétrécissement du périmètre. Q-2 est close sur la déclaration, l'hypothèse reste ouverte sur sa tenue | Chaque jalon K |
| **HYP-6** | Hypothèse | §13.2 | Le seuil de 40 inscriptions en 30 jours constitue un signal de demande passive significatif. Seuil choisi par jugement, non calibré | À réviser une fois la page publiée ; ne peut pas être révisé **après** avoir vu le résultat | Avant publication de la page projet |
| **HYP-7** | Hypothèse | §17 | PHP 8.1+ comme runtime de l'outil n'exclut pas de cible pertinente, le PHAR couvrant le legacy | Entretiens + test d'installation sur environnements legacy réels | Porte §13, puis S2 |
| **HYP-8** | Hypothèse | §17 | Une part significative de la cible primaire ne peut pas installer par Composer et exige un PHAR | Question aux 2 leads legacy du §13.1 | Porte §13 (K-1) |
| **HYP-9** | Hypothèse | §3.4, §9.1, FR-3 | PDO comme sink base unique suffit à démontrer la valeur sur un legacy réel, malgré la présence probable de `mysqli` dans le même dépôt | Q-8, Q-11 ; SM-3 (dont le critère « ≥ 3 dépôts legacy > 100 kLOC ») | K-3 |
| **HYP-10** | Hypothèse | NFR-2, SM-1 | Le seuil normatif de NFR-2 est atteignable en configuration par défaut sur du code non typé. Seuils posés par jugement, non par mesure | S1 (FR-13). Si inatteignable, K-2 s'applique — le seuil de NFR-2 n'est **pas** abaissé pour faire passer la mesure | K-2 |
| **HYP-11** | Hypothèse | §3.2 | L'absence de traction de la niche dédiée s'explique autrement que par une absence de demande | Q-10 ; c'est l'hypothèse la plus risquée du document | Porte §13 (K-1) |
| **HYP-12** | Hypothèse | §7.6 | La voie de vérification du SQL généré, si elle est absorbée par un fournisseur de CLI, ne retire rien au périmètre V1 | Structurellement vérifié : aucune FR de la V1 n'en dépend | Vérifiée par construction |
| **HYP-13** | Hypothèse | §5.1 J-2, SM-5 | Un auditeur consomme un rapport d'outil comme travail préparatoire plutôt que comme travail de vérification supplémentaire | Entretiens (2 auditeurs) ; SM-5 | Porte §13, puis SM-5 |
| **HYP-14** | Hypothèse | §14 | Le durcissement d'une règle est vécu par les utilisateurs comme une rupture, justifiant sa sortie en version majeure | Retours des premiers adoptants ; l'hypothèse est prudente par construction | Après les premiers adoptants |
