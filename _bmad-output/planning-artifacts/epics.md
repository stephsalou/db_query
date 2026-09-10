---
stepsCompleted: ["step-01-validate-prerequisites", "step-02-design-epics", "step-03-create-stories", "step-04-final-validation"]
inputDocuments:
  - "_bmad-output/planning-artifacts/prds/prd-db_query-2026-09-10/PRD.md"
  - "_bmad-output/planning-artifacts/adversarial-review-concept.md"
  - "_bmad-output/planning-artifacts/documentation-plan.md"
  - "_bmad-output/brainstorming/brainstorm-avenir-outil-open-source-2026-09-10/brainstorm-intent.md"
missingInputDocuments:
  - "Architecture.md — ABSENT. Bloque les epics du moteur (E2, E3). Voir « Écart de prérequis »."
  - "UX design contract — sans objet : le produit est une CLI. La doc web est couverte par documentation-plan.md."
---

# sqlguard — Découpage en epics

## Overview

Ce document décompose les exigences du PRD `prd-db_query-2026-09-10` en epics et stories implémentables.

**Les identifiants `FR-n` / `NFR-n` du PRD sont conservés à l'identique.** Le §0 du PRD engage leur stabilité et six chapitres les citent ; les renuméroter casserait la traçabilité.

### Écart de prérequis — à lire avant de planifier

~~Le workflow attend un document d'architecture. **Il n'existe pas.**~~

**Résolu le 2026-09-10.** La colonne architecture existe :
`_bmad-output/planning-artifacts/architecture/architecture-db_query-2026-09-10/ARCHITECTURE-SPINE.md`
— 26 AD, dont les 11 prérequis marqués ci-dessous, plus 9 trous dérivés par auto-critique
adversariale. Lint déterministe : 0 constat.

**E3 et E4 ne sont plus bloqués par l'architecture.** Ils restent bloqués par la porte **K-1**,
qui est une contrainte de validation et non d'ingénierie : le PRD (§19) interdit d'écrire le
moteur avant que des entretiens réels aient eu lieu. Cette porte ne peut pas être franchie sans
intervention humaine.

Tableau d'origine, conservé pour mémoire :

| Epic | Dépend de l'architecture ? | Statut |
|---|---|---|
| **E1 — Validation avant le code** | Non | **Planifiable immédiatement** |
| **E2 — Mesure** | Partiellement (format du corpus, harnais) | Planifiable, avec réserve |
| **E3 — Moteur d'analyse** | **Oui, fortement** | **Bloqué** — exige `bmad-architecture` d'abord |
| **E4 — Sorties et intégrations** | Oui (contrats de sortie, §14 du PRD) | **Bloqué** |
| **E5 — Crédibilité et documentation** | Non | Planifiable immédiatement |

Cet écart n'empêche pas d'avancer, parce que le PRD l'impose déjà : **le jalon K-1 interdit d'écrire le moteur avant d'avoir franchi la porte de validation** (§13, §19). L'architecture doit être produite pendant E1/E2, avant l'entrée dans E3.

Le PRD porte déjà, aux §14 et §17, des éléments qui relèvent de l'architecture (schéma de champs de sortie, politique de dépendances). La revue `structure` avait soulevé cette question de périmètre. Ils sont repris ci-dessous en *exigences additionnelles provisoires*, à confirmer par le document d'architecture.

## Requirements Inventory

### Functional Requirements

| ID | Exigence | Bloc |
|---|---|---|
| **FR-1** | Identification des sources non fiables | Moteur |
| **FR-2** | Propagation interprocédurale | Moteur |
| **FR-3** | Sink base unique — PDO | Moteur |
| **FR-4** | Reconnaissance des assainisseurs | Moteur |
| **FR-5** | Témoin de propagation (niveau 1, par défaut) | Témoin |
| **FR-6** | Témoin exploitable (niveau 2, opt-in) | Témoin |
| **FR-7** | Invariant « pas d'alerte sans témoin » | Témoin |
| **FR-8** | CLI zéro-configuration | Sortie |
| **FR-9** | Commentaire de PR via action GitHub — livrable primaire | Sortie |
| **FR-10** | Rapport autoportant | Sortie |
| **FR-11** | SARIF — artefact secondaire | Sortie |
| **FR-12** | Code de sortie et politique de seuil | Sortie |
| **FR-13** | Corpus de référence figé, harnais de mesure et comparaison publiée | Mesure |
| **FR-14** | Banc de performance en semaine 1 | Performance |
| **FR-15** | Cache de résumés de fonction persisté (conditionnelle) | Performance |
| **FR-16** | Configuration optionnelle et paquets de règles | Sortie |
| **FR-17** | Suppression d'alerte traçable | Sortie |
| **FR-18** | Surface programmatique du moteur | Moteur |
| **FR-19** | Étude de mesure directe du stock de dette SQL PHP | Mesure |
| **FR-20** | Page de provenance et de divulgation | Crédibilité |

### NonFunctional Requirements

| ID | Exigence | Vérification |
|---|---|---|
| **NFR-1** | Déterminisme : deux exécutions sur le même commit produisent une sortie identique après normalisation | Test byte-à-byte en CI |
| **NFR-2** | **Budget de faux positifs (contraignant)** : rappel ≥ 85 % et ≤ 3 FP/10 kLOC sur le corpus figé. Une version qui viole ce seuil **n'est pas publiée** | `make bench-quality` en porte de publication |
| **NFR-3** | Invariant du témoin : aucune alerte publiée sans témoin de niveau 1 | Test d'invariant faisant échouer la construction |
| **NFR-4** | Aucune sortie réseau pendant un scan | Exécution en environnement isolé |
| **NFR-5** | Aucune exécution du code analysé (ni `eval`, ni `include`, ni instanciation) | Revue + test sur fichier à effet de bord observable |
| **NFR-6** | Échec explicite plutôt que silence : toute analyse incomplète produit un code de sortie distinct | Tests sur dépassement temps/mémoire et erreur de parsing |
| **NFR-7** | Observabilité : `--verbose` expose file d'analyse, fonctions résumées, constructions non suivies, temps par phase | Test de présence des champs |
| **NFR-8** | Reproductibilité des mesures publiées par un tiers avec une commande unique | Rejeu par un contributeur externe avant annonce |
| **NFR-9** | Installation : `composer require --dev` puis une commande, sans service ni démon | Test en conteneur vierge |
| **NFR-10** | Budgets de performance — aucun chiffre avant FR-14 | Différée |

### Additional Requirements

**Provisoires — issues du PRD faute de document d'architecture, à confirmer par `bmad-architecture` :**

- Cibles runtime : PHP ≥ 7.4 pour le code analysé ; runtime de l'outil à arbitrer. Windows pris en charge sans garantie, non testé en V1 (§17).
- Politique de dépendances : `nikic/php-parser` comme socle d'analyse ; refus d'un parser SQL maison ; politique de dépendances transitives (§17).
- Contrats de sortie : schéma de champs stable pour la sortie machine, versionné en SemVer (§14, §15).
- Aucun service hébergé, aucun budget d'infrastructure (§11.3) — contraint toute conception vers l'exécution locale.
- Cache de résumés inter-procéduraux : exigence V1 **conditionnelle** ; sans lui, le mode pre-commit est abandonné et seul le mode CI est annoncé (FR-15, §16).

**Décisions d'architecture encore ouvertes et bloquantes pour E3 :** représentation du graphe de flux, stratégie de résumés inter-procéduraux, format de sérialisation du corpus annoté, frontière exacte du paquet `moteur` vs `interface` (FR-18).

### UX Design Requirements

**Sans objet.** Le produit est une interface en ligne de commande ; aucun contrat UX (`DESIGN.md` / `EXPERIENCE.md`) n'existe et aucun n'est requis.

Deux surfaces d'interface existent néanmoins et sont traitées comme des exigences fonctionnelles, pas UX :
- La sortie CLI et le rapport autoportant (FR-8, FR-10), dont la lisibilité par deux publics — développeur et auditeur — est un arbitrage produit du §5.1.
- Le site de documentation, couvert par `documentation-plan.md` (Astro Starlight), traité dans E5.

### FR Coverage Map

| FR | Epic | Apport |
|---|---|---|
| **FR-19** | E1 | Étude de mesure du stock de dette SQL — dimensionne le marché et constitue le premier actif de crédibilité |
| **FR-13** | E2 | Corpus de référence figé, harnais, comparaison publiée contre Psalm et Semgrep |
| **FR-14** | E2 | Banc de performance — produit les chiffres avant toute promesse |
| **FR-1** | E3 | Identification des sources non fiables |
| **FR-2** | E3 | Propagation interprocédurale |
| **FR-3** | E3 | Sink base unique PDO — la borne de la V1 |
| **FR-4** | E3 | Reconnaissance des assainisseurs — condition du budget de faux positifs |
| **FR-5** | E3 | Témoin de propagation niveau 1, par défaut |
| **FR-6** | E3 | Témoin exploitable niveau 2, opt-in et cloisonné |
| **FR-7** | E3 | Invariant « pas d'alerte sans témoin » |
| **FR-15** | E3 | Cache de résumés persisté — story conditionnelle, arbitrée par FR-14 |
| **FR-18** | E3 | Surface programmatique du moteur — rend le moteur réutilisable par les consommateurs aval |
| **FR-8** | E4 | CLI zéro-configuration |
| **FR-9** | E4 | Action GitHub avec commentaire de PR — livrable primaire |
| **FR-10** | E4 | Rapport autoportant |
| **FR-11** | E4 | SARIF — artefact secondaire |
| **FR-12** | E4 | Code de sortie et politique de seuil |
| **FR-16** | E4 | Configuration optionnelle et paquets de règles |
| **FR-17** | E4 | Suppression d'alerte traçable |
| **FR-20** | E5 | Page de provenance et de divulgation |

**Couverture : 20/20.** Aucune FR orpheline.

Les NFR ne sont pas mappées à un epic unique : NFR-1 à NFR-7 sont des invariants vérifiés en continu dans E3 et E4 ; NFR-8 est portée par E2 ; NFR-9 par E4 ; NFR-10 est différée jusqu'à FR-14.

## Epic List

**5 epics.** Le découpage suit les étapes S0 à S4 du §20 du PRD, parce que ce sont de vraies frontières de risque : chaque epic peut faire tomber le suivant. Les exigences du moteur (FR-1 à FR-7, FR-15, FR-18) sont regroupées dans un seul epic malgré leur nombre, car elles modifient toutes les mêmes fichiers cœur — les éclater créerait du va-et-vient sans bénéfice.

### Epic 1 : Décider, preuves en main, si l'outil doit exister

Au terme de cet epic, l'auteur peut trancher K-1 sur des éléments extérieurs à lui — entretiens, demande passive mesurée, taille réelle du problème — au lieu de son propre raisonnement. Le livrable de valeur n'est pas du code : c'est une décision documentée, plus une étude publiable qui existe même si le projet s'arrête là.

**FRs couvertes :** FR-19
**Autonomie :** complète. Ne dépend d'aucun autre epic, et n'exige ni architecture ni code.
**Ce qu'il rend possible :** l'entrée dans E2. Un échec ici clôt le projet (§19, K-1).

### Epic 2 : Savoir mesurer avant de promettre quoi que ce soit

Au terme de cet epic, n'importe quel tiers peut rejouer une comparaison chiffrée entre `sqlguard`, Psalm et Semgrep sur un corpus figé, et constater où `sqlguard` perd. C'est ce qui transforme la revendication du §3.5 en énoncé falsifiable.

**FRs couvertes :** FR-13, FR-14 — porte aussi NFR-8
**Autonomie :** complète. Le corpus annoté et le harnais ont une valeur propre : le pivot prévu en K-2 consiste précisément à les publier seuls si le moteur échoue.
**Ce qu'il rend possible :** la porte de qualité de E3. Sans corpus, NFR-2 est invérifiable et donc décoratif.

### Epic 3 : Détecter une injection, et le prouver

Au terme de cet epic, un utilisateur en intégration programmatique obtient, pour un dépôt PHP donné, la liste des chaînes de propagation atteignant PDO, chacune accompagnée d'un témoin reproductible — et aucune alerte sans témoin.

**FRs couvertes :** FR-1, FR-2, FR-3, FR-4, FR-5, FR-6, FR-7, FR-15, FR-18 — porte NFR-1 à NFR-7
**Autonomie :** fonctionnelle via la surface programmatique FR-18, sans attendre E4.
**Prérequis bloquant :** le **document d'architecture**, absent à ce jour.
**Ce qu'il rend possible :** E4, et tout consommateur aval (vérification de SQL généré, codemod).

### Epic 4 : Utiliser l'outil sur un vrai projet, sans le configurer

Au terme de cet epic, un développeur installe l'outil en deux commandes et obtient un verdict exploitable : en local, en commentaire de PR, et sous forme de rapport transmissible à un auditeur. Le même artefact sert les deux publics, ce qui est l'arbitrage produit du §5.1.

**FRs couvertes :** FR-8, FR-9, FR-10, FR-11, FR-12, FR-16, FR-17 — porte NFR-9
**Autonomie :** complète en s'appuyant sur E3.
**Prérequis bloquant :** contrats de sortie à figer dans le document d'architecture (§14).
**Ce qu'il rend possible :** l'adoption mesurable de SM-3, donc le jalon K-3.

### Epic 5 : Faire confiance au projet, pas seulement à son code

Au terme de cet epic, une équipe qui évalue l'outil trouve de quoi décider : ce que l'outil ne détecte pas, d'où il vient — y compris l'échappement sans effet de son ancêtre — qui le maintient et ce qu'il devient si le mainteneur s'arrête. Pour un outil de sécurité, c'est une condition d'adoption, pas de la communication.

**FRs couvertes :** FR-20
**Autres livrables :** site de documentation selon `documentation-plan.md` (Astro Starlight), politique de succession, recherche d'antériorité de marque (Q-1)
**Autonomie :** complète. Aucune dépendance de code.
**Ce qu'il rend possible :** l'annonce publique, qui reste interdite tant que cet epic n'est pas livré.

---

## Epic 1 : Décider, preuves en main, si l'outil doit exister

Au terme de cet epic, l'auteur détient de quoi trancher K-1 sans s'appuyer sur son propre raisonnement : un protocole d'entretien écrit avant les entretiens, 8 à 12 entretiens réalisés selon la composition imposée du §13.1, au moins une preuve de demande passive non déclarative, et l'étude de mesure FR-19 publiable même si le projet s'arrête. **Aucun code de moteur n'est écrit dans cet epic** (§19, K-1). Le livrable final est une décision écrite et datée.

### Story 1.1 : Protocole d'entretien figé avant le premier entretien

En tant que mainteneur-fondateur,
je veux un protocole d'entretien écrit et figé avant d'appeler qui que ce soit,
afin que les réponses ne soient pas contaminées par mon envie que le produit existe.

**Critères d'acceptation :**

**Étant donné** qu'aucun entretien n'a encore été mené
**Quand** le protocole est déposé dans le dépôt
**Alors** il nomme les quatre segments obligatoires du §13.1 (leads legacy, mainteneurs WordPress, auditeurs, utilisateurs d'agents de codage) avec le nombre visé par segment
**Et** il porte, pour chaque segment, la question de décision à trancher telle qu'écrite au §13.1

**Étant donné** le protocole déposé
**Quand** on y cherche les questions ouvertes du PRD à instruire en entretien
**Alors** Q-8 (`wpdb` est-il la condition d'entrée ?), Q-10 (explication du zéro-traction), Q-11 (un seul sink induit-il en erreur ?), HYP-8 (nécessité d'un PHAR) y figurent chacune sous forme de question posée à un segment identifié

**Étant donné** le protocole déposé
**Quand** on examine la règle de codage des réponses
**Alors** elle définit ce qui fait passer un job du §5.1 de `hypothèse` à `validé par N entretiens`, avec N ≥ 2, et exige une occasion réelle et datée plutôt qu'une opinion
**Et** elle exclut explicitement les réponses déclaratives du type « oui, ce serait utile » du décompte de validation

**Étant donné** le protocole déposé
**Quand** on cherche les questions interdites
**Alors** le protocole liste les formulations suggestives proscrites, dont toute présentation de la revendication du §3.5 avant la fin de la partie découverte

### Story 1.2 : Entretiens conduits et statut de validation des jobs mis à jour

En tant que mainteneur-fondateur,
je veux avoir mené les entretiens et consigné leur résultat au format du protocole,
afin que le tableau des jobs du §5.1 cesse d'être un raisonnement de l'auteur avec lui-même.

**Critères d'acceptation :**

**Étant donné** le protocole de la story 1.1
**Quand** la campagne d'entretiens est déclarée terminée
**Alors** au moins 8 entretiens sont consignés, chacun avec sa date, son segment et ses réponses codées
**Et** la composition respecte le §13.1 : au minimum 2 leads techniques legacy, 2 mainteneurs WordPress, 2 auditeurs, 2 utilisateurs d'agents de codage

**Étant donné** les entretiens consignés
**Quand** le tableau des jobs du §5.1 est mis à jour
**Alors** chaque job porte soit `hypothèse`, soit `validé par N entretiens` avec N explicite
**Et** aucun job resté en `hypothèse` ne porte de FR de la V1

**Étant donné** que J-1 ou J-2 reste en `hypothèse` à l'issue de la campagne
**Quand** le statut est publié
**Alors** le document énonce que la V1 n'est pas lançable dans sa forme actuelle, sans reformulation atténuante

**Étant donné** les réponses des 2 mainteneurs WordPress
**Quand** Q-8 est instruite
**Alors** une réponse écrite existe sur la question « le sink `wpdb` est-il la véritable condition d'entrée ? », et si elle est positive le choix de PDO comme sink unique (FR-3) est marqué à rejuger avant S2

**Étant donné** les réponses des 2 leads techniques legacy
**Quand** Q-11 est instruite
**Alors** il est écrit si la mention du nombre d'appels non couverts (FR-3) suffit, selon eux, à ne pas induire en erreur

### Story 1.3 : Preuve de demande passive obtenue sans démarchage

En tant que mainteneur-fondateur,
je veux une preuve de demande obtenue sans que j'aie sollicité personne,
afin que la décision K-1 ne repose pas uniquement sur de la politesse d'entretien.

**Critères d'acceptation :**

**Étant donné** qu'aucune audience n'est achetée
**Quand** la page de projet est publiée
**Alors** elle décrit le problème et la revendication du §3.5 telle quelle, propose une inscription à un avis de disponibilité, et ne revendique nulle part que « le marché de la vérification est vide »

**Étant donné** la page publiée
**Quand** 30 jours se sont écoulés
**Alors** le nombre d'inscriptions est relevé, daté et consigné dans le dépôt, y compris s'il est inférieur au seuil de 40

**Étant donné** que le seuil de 40 inscriptions en 30 jours n'est pas atteint
**Quand** les autres voies du §13.2 sont examinées
**Alors** la preuve peut être substituée par la reprise organique mesurée de l'étude FR-19 ou par ≥ 3 demandes spontanées non sollicitées d'accès anticipé, chacune tracée avec sa date et sa source
**Et** aucune preuve déclarative recueillie en entretien n'est comptée

**Étant donné** le seuil `[HYP-6]` de 40 inscriptions
**Quand** le résultat est connu
**Alors** le seuil n'est pas modifié après coup ; toute révision de `[HYP-6]` est datée d'avant le relevé ou refusée

### Story 1.4 : Étude de mesure directe du stock de dette SQL PHP publiée

En tant que lecteur extérieur au projet,
je veux un chiffre sourcé et rejouable sur le volume réel de concaténation vers un sink base en PHP,
afin que je puisse juger la taille du problème sans croire les pourcentages non sourcés qui circulent.

**Critères d'acceptation :**

**Étant donné** que l'étude porte sur du code tiers
**Quand** le premier dépôt est cloné pour analyse
**Alors** la politique de divulgation responsable (Q-7) est déjà publiée dans le dépôt, conformément au §11.1

**Étant donné** la politique publiée
**Quand** l'échantillon est constitué
**Alors** il compte 200 dépôts PHP publics et la méthode d'échantillonnage est écrite avant le tirage, avec ses critères d'inclusion et d'exclusion

**Étant donné** l'échantillon constitué
**Quand** le comptage est exécuté
**Alors** le code de comptage, les données brutes et la commande de rejeu sont publiés, et un tiers obtient les mêmes chiffres sans assistance
**Et** le comptage est mécanique : aucun chiffre n'est saisi à la main dans les résultats

**Étant donné** l'étude publiée
**Quand** on relit les communications du projet
**Alors** les chiffres « ≈70 % du web », « WordPress ≈40 % », « 200 k lignes » et « part majeure générée par IA » n'y apparaissent plus, remplacés par les résultats mesurés

**Étant donné** un résultat montrant un stock faible
**Quand** l'étude est publiée
**Alors** elle l'énonce comme tel et renvoie à K-3 comme critère d'arrêt, sans réinterprétation favorable

**Étant donné** l'étude publiée
**Quand** aucune V1 n'est lancée
**Alors** l'étude reste un livrable autonome et citable, conforme au pivot autorisé de K-1

### Story 1.5 : Décision K-1 écrite, datée et opposable

En tant que contributeur potentiel ou lecteur du dépôt,
je veux trouver la décision K-1 écrite avec les preuves qui la fondent,
afin que je sache si ce projet a le droit d'écrire du code et pourquoi.

**Critères d'acceptation :**

**Étant donné** les entretiens, le statut des jobs et la preuve de demande passive
**Quand** la décision K-1 est déposée dans le dépôt
**Alors** elle est datée, énonce chacun des trois seuils de SM-4 et indique pour chacun s'il est atteint ou manqué, avec le lien vers la preuve correspondante

**Étant donné** un ou plusieurs seuils manqués
**Quand** la décision est rédigée
**Alors** elle prononce l'abandon du produit ou le pivot autorisé (publication de l'étude FR-19 seule et clôture), et non un ajustement de stratégie

**Étant donné** tous les seuils atteints
**Quand** la décision est rédigée
**Alors** elle autorise explicitement l'entrée dans E2 et rappelle que le moteur reste interdit jusqu'à l'atteinte de SM-1 (§20)

**Étant donné** un report de la date butoir T+6 semaines
**Quand** le report est consigné
**Alors** il est unique, d'au maximum 4 semaines, et porte un motif écrit ; un second report vaut critère non atteint

---

## Epic 2 : Savoir mesurer avant de promettre quoi que ce soit

Au terme de cet epic, un tiers hostile peut rejouer en une commande la comparaison chiffrée entre `sqlguard`, Psalm et Semgrep sur un corpus figé, et lire où `sqlguard` perd. L'epic produit aussi les seuls chiffres de performance que le projet est autorisé à publier. Le corpus et le harnais ont une valeur propre : ils constituent le pivot autorisé de K-2.

### Story 2.1 : Banc de performance mesuré, publié, puis budgets écrits

> Exigences : **FR-14** ; lève **NFR-10** (budgets de performance, délibérément sans chiffre jusqu'à cette story).

En tant que mainteneur-fondateur,
je veux des temps observés sur trois tailles de dépôt avant d'arbitrer quoi que ce soit sur la performance,
afin que les budgets du §16 soient dérivés d'une mesure et non d'une intuition.

**Critères d'acceptation :**

**Étant donné** trois dépôts de référence PHP nommés et publiquement accessibles d'environ 20 k, 200 k et 2 M lignes
**Quand** le banc est exécuté par une commande unique
**Alors** il relève pour chaque dépôt le temps mural, le pic de mémoire, le nombre de fichiers analysés et le nombre de limites d'analyse
**Et** les résultats sont écrits dans un fichier versionné du dépôt, avec la version d'outil mesurée et la date

**Étant donné** les résultats publiés
**Quand** un tiers exécute la même commande sur les mêmes dépôts
**Alors** il obtient des valeurs du même ordre de grandeur, et la commande n'exige aucune configuration préalable autre que l'installation documentée

**Étant donné** les temps observés publiés
**Quand** les budgets de performance sont rédigés
**Alors** chaque budget `PB-n` cite la valeur mesurée dont il dérive et la marge appliquée, sous la forme imposée au §16
**Et** aucun budget n'est daté d'avant la publication des mesures

**Étant donné** les budgets écrits
**Quand** une régression dépasse un budget
**Alors** la CI du projet échoue

**Étant donné** que le banc mesure une version antérieure au moteur complet
**Quand** les résultats sont publiés
**Alors** le périmètre exact de ce qui a été mesuré est indiqué, sans laisser croire que la V1 est mesurée

> **Prérequis architecture :** le harnais de mesure et le point d'entrée qu'il invoque — quelle commande le banc appelle, et sur quelle frontière du paquet elle se branche.

### Story 2.2 : Corpus de référence annoté, versionné et gelé

En tant que tiers évaluant l'outil,
je veux un corpus de référence public dont chaque chaîne de propagation vraie est annotée à la main,
afin que je puisse vérifier les chiffres de rappel et de faux positifs au lieu de les croire.

**Critères d'acceptation :**

**Étant donné** le corpus déposé et versionné
**Quand** on compte ses cas annotés
**Alors** il en contient au moins 150, dont au moins 40 % de cas négatifs — du code sûr qui ne doit produire aucune alerte
**Et** il inclut du legacy PHP non typé réel sous licence permissive, avec la mention de sa provenance et de sa licence

**Étant donné** le corpus déposé
**Quand** une modification est proposée
**Alors** elle exige un nouveau numéro de version et une entrée de journal justifiant le changement
**Et** l'ajout d'un cas dans le but de faire passer une mesure est explicitement interdit par la règle écrite du corpus

**Étant donné** le corpus déposé
**Quand** on cherche les cas exigés par les FR du moteur
**Alors** il contient au moins un cas par superglobale du paquet de règles générique, au moins 3 cas de taint de second ordre, des cas positifs et négatifs de clés `$_SERVER`, un cas de profondeur d'appel ≥ 5 sauts, un cas par construction propageante (`sprintf`, `implode`, `str_replace`, `.`, interpolation), au moins 5 formes syntaxiques d'allowlist, les deux verdicts opposés de `PDO::quote`, et un cas d'échappement no-op

**Étant donné** un cas annoté
**Quand** on lit son annotation
**Alors** elle nomme la position source, la position sink et le verdict attendu, de façon exploitable sans intervention humaine par le harnais

> **Prérequis architecture :** le format de sérialisation du corpus annoté — comment une chaîne annotée est écrite pour être consommée mécaniquement.

### Story 2.3 : Harnais de qualité — rappel et faux positifs en une commande

En tant que mainteneur-fondateur,
je veux qu'une commande unique calcule le rappel et les FP/10 kLOC sur le corpus figé,
afin que NFR-2 soit une porte de publication vérifiable et non une intention.

**Critères d'acceptation :**

**Étant donné** le corpus de référence versionné
**Quand** `make bench-quality` est exécuté
**Alors** il produit le rappel sur les chaînes annotées atteignant un sink PDO et le nombre de FP/10 kLOC, et les écrit dans un fichier de résultats versionné portant la version du corpus et de l'outil

**Étant donné** une mesure produite
**Quand** le rappel est inférieur à 85 % ou les FP/10 kLOC supérieurs à 3
**Alors** la porte de publication est en échec et la version n'est pas publiable

**Étant donné** le canal `suspicions`
**Quand** le harnais calcule les FP/10 kLOC
**Alors** aucune suspicion n'est comptée, ni comme alerte ni comme faux positif

**Étant donné** deux exécutions successives du harnais sans modification du corpus ni de l'outil
**Quand** on compare les fichiers de résultats
**Alors** ils sont identiques

**Étant donné** un résultat sous le seuil
**Quand** on cherche à le rattraper
**Alors** les seuils de NFR-2 ne sont pas abaissés : l'issue prévue est K-2, conformément à `[HYP-10]`

### Story 2.4 : Comparaison publiée contre Psalm et Semgrep, défaites incluses

En tant qu'équipe déjà outillée avec Psalm ou Semgrep,
je veux la comparaison chiffrée en configuration par défaut, y compris les cas où `sqlguard` perd,
afin que je puisse décider si l'installer m'apporte quelque chose.

**Critères d'acceptation :**

**Étant donné** le corpus figé et le harnais de qualité
**Quand** la comparaison est exécutée
**Alors** elle produit trois colonnes de résultats — `sqlguard`, Psalm, Semgrep — chacune en configuration **par défaut** de l'outil concerné, avec la version exacte de chaque outil

**Étant donné** les résultats produits
**Quand** ils sont publiés
**Alors** les cas où `sqlguard` obtient un moins bon résultat que Psalm ou Semgrep sont listés nommément, avec le cas de corpus concerné
**Et** le protocole de mesure est publié en même temps que les chiffres

**Étant donné** le sous-corpus de code non typé
**Quand** SM-1 est évalué
**Alors** le résultat de `sqlguard` y est comparé à celui de Psalm et Semgrep, et le verdict « au moins égal » est écrit explicitement, atteint ou non

**Étant donné** la comparaison publiée
**Quand** un contributeur externe la rejoue avec une commande unique et sans assistance
**Alors** il régénère les mêmes chiffres, et ce rejeu est consigné avant toute annonce publique (NFR-8, SM-6)

**Étant donné** que SonarQube exige d'opérer une instance
**Quand** on lit la comparaison
**Alors** son absence est déclarée et rattachée à Q-6, sans laisser croire à une omission

---

## Epic 3 : Détecter une injection, et le prouver

Au terme de cet epic, un consommateur programmatique obtient pour un dépôt PHP la liste des chaînes de propagation atteignant PDO, chacune portant un témoin reproductible, et aucune alerte sans témoin.

> **Cet epic ne peut pas entrer en développement avant l'existence du document d'architecture.** Quatre décisions au moins lui manquent : représentation du graphe de flux, stratégie de résumés inter-procéduraux, frontière du paquet `moteur` vs `interface`, schéma de champs de sortie. Les critères d'acceptation ci-dessous sont écrits contre le comportement observable imposé par le PRD ; là où une décision d'architecture manque, elle est nommée sous la story. Rappel de séquencement : rien de cet epic ne se code avant K-1 franchi (§19) et son entrée est conditionnée à SM-1 atteint sur le corpus (§20).

### Story 3.1 : Identifier les sources non fiables sans configuration

En tant que développeur analysant un dépôt legacy,
je veux que l'outil sache seul quelles expressions sont contrôlables par un tiers,
afin que je n'aie pas à déclarer moi-même ce qui est dangereux avant d'obtenir un premier résultat.

**Critères d'acceptation :**

**Étant donné** le paquet de règles générique actif par défaut et aucune configuration utilisateur
**Quand** un fichier lit directement une superglobale du paquet
**Alors** cette lecture est marquée comme source non fiable
**Et** le corpus de référence contient au moins un cas par superglobale, et chacun est reconnu

**Étant donné** une valeur lue depuis un sink base puis réinjectée dans un autre sink base
**Quand** l'analyse s'exécute
**Alors** la valeur relue est marquée comme source non fiable (taint de second ordre)
**Et** les 3 cas annotés du corpus correspondants sont reconnus

**Étant donné** une lecture de `$_SERVER`
**Quand** la clé lue est `HTTP_*`, `QUERY_STRING`, `REQUEST_URI` ou `PATH_INFO`
**Alors** la lecture est une source non fiable
**Et** pour toute autre clé de `$_SERVER` elle ne l'est pas, les cas positifs et négatifs du corpus donnant les verdicts opposés attendus

**Étant donné** une clé de `$_SERVER` calculée dynamiquement
**Quand** l'analyse ne peut pas la résoudre
**Alors** le cas est enregistré dans les limites d'analyse et ne produit pas d'alerte silencieuse

### Story 3.2 : Reconnaître PDO comme unique sink base, et dire ce qui n'est pas couvert

En tant que lead technique sur un dépôt mixte,
je veux savoir exactement quels points d'exécution SQL sont analysés et combien ne le sont pas,
afin que l'absence d'alerte ne me donne pas une fausse assurance.

**Critères d'acceptation :**

**Étant donné** un appel à `PDO::query`, `PDO::exec`, ou `PDO::prepare` dont l'argument SQL est une expression construite dynamiquement
**Quand** l'analyse s'exécute
**Alors** l'appel est reconnu comme sink base

**Étant donné** un `PDO::prepare` avec littéral SQL constant suivi d'une liaison de paramètres
**Quand** l'analyse s'exécute
**Alors** aucune alerte n'est produite, et le décompte d'alertes sur les cas correctement paramétrés du corpus est exactement 0

**Étant donné** un appel à `mysqli_query` ou `$wpdb->query`
**Quand** le scan se termine
**Alors** aucune alerte n'est produite pour cet appel
**Et** le scan affiche en fin d'exécution le nombre d'appels non couverts détectés et le lien vers le ticket de suivi

**Étant donné** un `PDOStatement` obtenu depuis un `PDO::prepare` dynamique
**Quand** l'analyse s'exécute
**Alors** le sink est rattaché à la position d'exécution SQL, et non à une position intermédiaire arbitraire

> **Prérequis architecture :** la représentation du graphe de flux, et la façon dont une position de sink y est identifiée de manière stable.

### Story 3.3 : Suivre la propagation à l'intérieur d'une fonction

En tant que développeur,
je veux que l'outil suive une valeur non fiable au fil des affectations et des concaténations d'une même fonction,
afin d'obtenir un premier résultat exploitable sur les cas les plus fréquents du legacy.

**Critères d'acceptation :**

**Étant donné** une source non fiable affectée à une variable puis concaténée dans une chaîne atteignant un sink base, dans une seule fonction
**Quand** l'analyse s'exécute
**Alors** une chaîne de propagation ordonnée et non vide est produite, de la position source à la position sink

**Étant donné** les constructions `sprintf`, `implode`, `str_replace`, l'opérateur `.` et l'interpolation `"{$x}"`
**Quand** chacune reçoit une valeur marquée
**Alors** la marque est propagée à son résultat
**Et** le cas de corpus dédié à chacune de ces cinq constructions est reconnu

**Étant donné** une source non fiable stockée dans un élément de tableau puis relue
**Quand** l'analyse s'exécute
**Alors** la marque est propagée à travers l'élément de tableau, et le saut correspondant figure dans la chaîne

**Étant donné** une variable réaffectée par une valeur sûre après avoir porté la marque
**Quand** l'analyse s'exécute
**Alors** aucune alerte n'est produite pour l'usage postérieur à la réaffectation

> **Prérequis architecture :** la représentation du graphe de flux intra-procédural et la granularité de l'état par variable.

### Story 3.4 : Traverser les appels de fonctions et de méthodes du périmètre analysé

En tant que lead technique sur du code découpé en couches,
je veux que la propagation traverse les appels de fonctions et de méthodes définies dans le dépôt,
afin que les injections qui passent par une couche d'accès aux données soient détectées.

**Critères d'acceptation :**

**Étant donné** une source non fiable passée en argument à une fonction dont le retour atteint un sink base
**Quand** l'analyse s'exécute
**Alors** la chaîne de propagation contient la position source, la position sink et le saut intermédiaire par l'appel et le retour

**Étant donné** un chemin de propagation traversant 5 appels imbriqués
**Quand** le cas dédié du corpus est analysé
**Alors** la chaîne est produite complète, ce qui atteste une profondeur d'appel supportée ≥ 5 sauts

**Étant donné** une méthode d'instance définie dans le périmètre analysé qui reçoit une valeur marquée et l'écrit vers un sink base
**Quand** l'analyse s'exécute
**Alors** la propagation traverse l'appel de méthode et la chaîne nomme la méthode traversée

**Étant donné** un appel résolu au travers d'un conteneur d'injection de dépendances, ou un appel inter-dépôts
**Quand** l'analyse s'exécute
**Alors** le cas est enregistré dans les limites d'analyse et ne produit pas d'alerte

**Étant donné** un cycle d'appels mutuellement récursifs portant la marque
**Quand** l'analyse s'exécute
**Alors** elle termine, et produit soit une chaîne, soit une entrée de limites d'analyse — jamais un silence

> **Prérequis architecture :** la stratégie de résumés inter-procéduraux — ce qu'un résumé de fonction retient (quels paramètres atteignent quels retours ou sinks), et l'ordre d'analyse retenu.

### Story 3.5 : Rompre la propagation sur un assainisseur, et refuser les faux assainisseurs

En tant que développeur qui a déjà paramétré ses requêtes,
je veux que l'outil ne m'alerte pas sur du code correctement assaini,
afin que le budget de faux positifs soit tenable en configuration par défaut.

**Critères d'acceptation :**

**Étant donné** une valeur marquée liée par `bindValue`, `bindParam`, ou passée dans le tableau d'arguments de `execute()`
**Quand** l'analyse s'exécute
**Alors** la propagation est rompue et aucune alerte n'est produite

**Étant donné** une valeur marquée soumise à un cast `(int)`, à `intval()`, ou à `+0`
**Quand** elle est utilisée dans un contexte numérique
**Alors** la propagation est rompue

**Étant donné** une valeur marquée comparée à une allowlist fermée — `in_array` sur tableau littéral, `match` ou `switch` à cas littéraux — avant usage
**Quand** l'analyse s'exécute
**Alors** la propagation est rompue
**Et** les 5 formes syntaxiques d'allowlist du corpus donnent toutes ce verdict

**Étant donné** une valeur marquée passée à `PDO::quote`
**Quand** elle est utilisée comme valeur entre quotes
**Alors** la propagation est rompue
**Et** quand elle est utilisée comme identifiant ou comme fragment de structure (`ORDER BY`, nom de table), la propagation n'est pas rompue et une alerte est produite — les deux cas du corpus donnant des verdicts opposés

**Étant donné** une valeur marquée passée à `addslashes`, `mysql_real_escape_string`, ou à une fonction d'échappement no-op
**Quand** l'analyse s'exécute
**Alors** la propagation n'est pas rompue et l'alerte est produite, conformément au cas de corpus issu du §4.2

**Étant donné** une fonction déclarée assainissante par un paquet de règles
**Quand** elle est traversée
**Alors** la propagation est rompue de la même façon qu'un assainisseur natif

### Story 3.6 : Dire ce que l'analyse n'a pas pu suivre, et échouer plutôt que se taire

En tant qu'auditrice consommant le résultat,
je veux la liste explicite de ce que l'outil n'a pas su suivre et un signal clair quand l'analyse est incomplète,
afin de commencer ma mission sur les zones que l'outil déclare ne pas couvrir.

**Critères d'acceptation :**

**Étant donné** un appel dynamique, un `eval`, une utilisation de la réflexion ou un `include` à chemin calculé
**Quand** l'analyse s'exécute
**Alors** chacun est enregistré dans les limites d'analyse avec sa position, et aucun ne produit d'alerte silencieuse

**Étant donné** un dépassement de la limite de temps, un dépassement de la limite de mémoire ou une erreur de parsing
**Quand** le scan se termine
**Alors** le résultat est marqué incomplet, la cause figure dans les limites d'analyse, et le résultat n'est pas interprétable comme « code sûr » (NFR-6)

**Étant donné** un fichier analysé produisant un effet de bord observable s'il était exécuté
**Quand** le scan s'exécute
**Alors** aucun effet de bord n'est observé : le code analysé n'est ni évalué, ni inclus, ni instancié (NFR-5)

**Étant donné** un environnement isolé du réseau
**Quand** le scan s'exécute
**Alors** il réussit à l'identique et n'émet aucune requête réseau (NFR-4)

**Étant donné** l'option `--verbose`
**Quand** le scan s'exécute
**Alors** la sortie expose la file d'analyse, les fonctions résumées, les constructions non suivies et les temps par phase (NFR-7)

### Story 3.7 : Attacher un témoin de niveau 1 à chaque alerte, et n'en publier aucune sans témoin

En tant que lead technique devant justifier un constat,
je veux que chaque alerte soit accompagnée de sa chaîne de propagation reproductible, sans charge utile,
afin de pouvoir la vérifier et la transmettre sans livrer une arme.

**Critères d'acceptation :**

**Étant donné** une alerte publiée
**Quand** on inspecte son témoin
**Alors** il contient au moins une position source, au moins une position sink et la liste ordonnée des sauts

**Étant donné** deux exécutions successives sur le même commit, la même version d'outil et le même paquet de règles
**Quand** on compare les sorties normalisées
**Alors** elles sont identiques byte à byte, et un test de non-régression en CI le vérifie (NFR-1)

**Étant donné** les artefacts de niveau 1
**Quand** un test automatisé y recherche les littéraux `' OR 1=1`, `UNION SELECT`, `;--`
**Alors** il n'en trouve aucun, et il échoue s'il en trouve un

**Étant donné** un constat pour lequel l'analyse n'a pas produit de chaîne de propagation complète
**Quand** le scan se termine
**Alors** ce constat est émis dans le canal `suspicions`, désactivé par défaut, activable par `--include-suspicions`
**Et** il n'entre ni dans le score, ni dans le code de sortie, ni dans le calcul de FP/10 kLOC

**Étant donné** la suite de tests de `sqlguard` lui-même
**Quand** une alerte sans témoin de niveau 1 est produite
**Alors** la construction du projet échoue (NFR-3, FR-7)

> **Prérequis architecture :** le schéma de champs de la sortie machine, et la règle de normalisation appliquée avant la comparaison byte à byte.

### Story 3.8 : Générer un témoin exploitable en double opt-in, hors de tout artefact partagé

En tant que mainteneur du projet,
je veux que le témoin de niveau 2 exige un double consentement et reste confiné,
afin que le projet ne soit jamais la source d'une charge armée archivée dans une CI.

**Critères d'acceptation :**

**Étant donné** le drapeau `--witness=exploitable` sans confirmation dans le fichier de configuration local, ou la confirmation sans le drapeau
**Quand** la commande est lancée
**Alors** elle échoue avec un message expliquant la responsabilité encourue, et aucun témoin de niveau 2 n'est produit

**Étant donné** le drapeau et la confirmation tous deux présents
**Quand** le scan s'exécute
**Alors** le témoin exploitable est écrit dans un fichier distinct, portant un en-tête d'avertissement
**Et** ce fichier est ajouté automatiquement au `.gitignore` du dépôt analysé s'il n'y figure pas

**Étant donné** un témoin de niveau 2 produit
**Quand** on inspecte le rapport autoportant, le commentaire de PR et la sortie SARIF
**Alors** aucun d'eux ne le contient, en aucune circonstance

**Étant donné** une configuration d'action GitHub demandant `exploitable`
**Quand** l'action démarre
**Alors** elle échoue au démarrage, et un test le vérifie

**Étant donné** un témoin exploitable
**Quand** on examine ce qu'il fait
**Alors** il décrit une entrée concrète modifiant la structure du SQL produit, sans jamais être exécuté et sans aucune connexion à une base réelle

### Story 3.9 : Consommer le moteur comme bibliothèque, sans passer par la CLI

En tant que développeur d'un consommateur aval,
je veux appeler le moteur d'analyse directement depuis du code PHP,
afin de réutiliser l'analyse sans forker le projet ni dupliquer sa logique.

**Critères d'acceptation :**

**Étant donné** un périmètre de fichiers et un paquet de règles
**Quand** le point d'entrée d'analyse du moteur est appelé programmatiquement
**Alors** il retourne une collection structurée d'alertes et de suspicions, chacune porteuse de son identifiant, de sa gravité, de son sink et de sa chaîne

**Étant donné** le paquet du moteur
**Quand** on inspecte ses dépendances
**Alors** il ne dépend d'aucun paquet d'interface, et cette absence est vérifiée automatiquement

**Étant donné** un consommateur de test tiers
**Quand** il obtient un résultat pour un dépôt donné
**Alors** ce résultat est identique à celui obtenu par la même analyse invoquée autrement, sans duplication de la logique d'analyse (SM-8)

**Étant donné** la surface publique du moteur
**Quand** on la parcourt
**Alors** seuls les éléments marqués `@api` y figurent, tout le reste étant `@internal` et modifiable en version mineure (§14, S-4)

> **Prérequis architecture :** la frontière exacte entre le paquet `moteur` et les paquets d'interface, et la liste des classes et interfaces `@api`.

### Story 3.10 : Réutiliser des résumés de fonction persistés — story conditionnelle

En tant que développeur voulant un retour avant de valider un commit,
je veux qu'un second scan réutilise les résumés déjà calculés,
afin que le temps d'analyse redevienne compatible avec un usage local répété.

> **Condition d'entrée :** cette story n'est engagée que si le banc de performance de la story 2.1 démontre que le budget d'un mode pre-commit est **hors d'atteinte sans cache de résumés**. Si le cache n'est pas livré, l'alternative écrite au §16 s'applique : le mode pre-commit est abandonné, non annoncé, et seul le mode CI est communiqué. C'est Q-9, tranchée après FR-14.

**Critères d'acceptation :**

**Étant donné** un scan complet déjà effectué et aucune modification de source
**Quand** un second scan est lancé
**Alors** les résumés persistés sont réutilisés et le gain de temps mural est mesuré et publié

**Étant donné** un cache existant
**Quand** la version de l'outil, la version du paquet de règles ou le contenu d'un fichier change
**Alors** les entrées de cache concernées sont invalidées

**Étant donné** un cache corrompu ou de version incompatible
**Quand** un scan est lancé
**Alors** il effectue un scan complet et ne produit jamais un résultat partiel silencieux

**Étant donné** un scan servi par le cache
**Quand** on compare son résultat à celui d'un scan complet sur le même commit
**Alors** les deux résultats sont identiques après normalisation (NFR-1)

> **Prérequis architecture :** le format de persistance d'un résumé de fonction et sa clé d'invalidation.

---

## Epic 4 : Utiliser l'outil sur un vrai projet, sans le configurer

Au terme de cet epic, un développeur installe l'outil en deux commandes et obtient un verdict exploitable : en local, en commentaire de PR, et sous forme de rapport transmissible à un auditeur.

> **Cet epic ne peut pas entrer en développement avant l'existence du document d'architecture.** Le §14 du PRD engage un schéma de champs stable pour la sortie machine, mais ce schéma n'est pas arrêté : ni la forme exacte des champs, ni la frontière entre le moteur et les paquets d'interface qui les sérialisent. Les critères ci-dessous sont écrits contre le comportement observable ; la décision manquante est nommée sous chaque story concernée.

### Story 4.1 : Obtenir un verdict en deux commandes, sans écrire de configuration

En tant que lead technique découvrant l'outil,
je veux installer puis scanner sans rien configurer,
afin d'obtenir un premier résultat avant d'avoir décidé si j'adopte l'outil.

**Critères d'acceptation :**

**Étant donné** un conteneur vierge sans service, sans base et sans démon
**Quand** on exécute `composer require --dev` puis une commande de scan
**Alors** le scan aboutit, et un test d'installation en conteneur vierge le vérifie (NFR-9)

**Étant donné** un dépôt PHP quelconque et aucun fichier de configuration
**Quand** `sqlguard scan <chemin>` est lancé
**Alors** aucune option n'est obligatoire, et le scan produit un verdict

**Étant donné** un scan par défaut
**Quand** on lit la sortie
**Alors** elle tient sur 40 lignes et contient le compte d'alertes par gravité, les N plus graves avec leur chaîne de propagation abrégée, le périmètre analysé et le compte des limites d'analyse

**Étant donné** l'option `--format=json`
**Quand** le scan s'exécute
**Alors** la sortie est conforme au schéma documenté, et un validateur de schéma le vérifie en CI

**Étant donné** un fichier de configuration optionnel
**Quand** il est présent
**Alors** il permet des exclusions de chemins, la sélection d'un paquet de règles, la déclaration de sources et d'assainisseurs supplémentaires et un seuil de code de sortie
**Et** le paquet de règles générique reste actif par défaut, les paquets d'écosystème étant opt-in, et toute mesure publiée du §7.2 est faite en configuration par défaut

> **Prérequis architecture :** le schéma de champs de la sortie machine S-2 (`alerts[]`, `suspicions[]`, `analysis_limits[]`, `scope`, `tool_version`, `rule_pack_version`, `commit`) et sa politique de versionnage.

### Story 4.2 : Faire échouer une CI sur un verdict, jamais sur une analyse incomplète

En tant que responsable d'une CI,
je veux un code de sortie qui exprime le verdict et distingue l'analyse incomplète,
afin de bloquer une régression sans bloquer sur une limite technique de l'outil.

**Critères d'acceptation :**

**Étant donné** un scan sans alerte de gravité haute
**Quand** il se termine
**Alors** le code de sortie est 0

**Étant donné** un scan avec au moins une alerte de gravité haute
**Quand** il se termine
**Alors** le code de sortie est 1
**Et** les suspicions, même activées, n'influencent jamais le code de sortie

**Étant donné** l'option `--baseline=<fichier>`
**Quand** le scan trouve uniquement des alertes présentes dans la référence
**Alors** le code de sortie est 0, et il échoue seulement sur les alertes absentes de la référence

**Étant donné** une analyse incomplète — limite de temps, limite de mémoire ou erreur de parsing
**Quand** le scan se termine
**Alors** le code de sortie est 2, distinct des précédents, et n'est pas interprétable comme « code sûr »

**Étant donné** un seuil de code de sortie déclaré dans la configuration
**Quand** le scan s'exécute
**Alors** ce seuil est appliqué à la place du défaut

### Story 4.3 : Transmettre un rapport autoportant lisible par un auditeur

En tant qu'auditrice recevant un rapport avant mission,
je veux un fichier unique lisible sans réseau contenant le périmètre, les versions et les limites,
afin de savoir sur quelles zones commencer plutôt que de repartir de zéro.

**Critères d'acceptation :**

**Étant donné** un scan terminé
**Quand** le rapport autoportant est généré
**Alors** c'est un fichier HTML unique, et un PDF peut en être dérivé

**Étant donné** le rapport ouvert dans un environnement isolé du réseau
**Quand** on l'affiche
**Alors** il s'affiche complètement, sans CDN, sans serveur et sans dépendance externe

**Étant donné** le rapport
**Quand** on en parcourt le contenu
**Alors** il contient la version de l'outil, la version du paquet de règles, le hash du commit analysé, le périmètre analysé avec fichiers inclus, exclus et lignes comptées, les alertes avec leurs chaînes de propagation, et la section des limites d'analyse en évidence et non en annexe

**Étant donné** le double lectorat du §5.1
**Quand** un développeur lit le rapport
**Alors** il y trouve des positions `fichier:ligne` cliquables ; et quand une auditrice le lit, elle y trouve périmètre, versions et limites

**Étant donné** un témoin exploitable produit par ailleurs
**Quand** on inspecte le rapport
**Alors** il ne le contient pas

> **Prérequis architecture :** la source des champs du rapport — quel contrat de sortie le générateur consomme, et où se situe la frontière entre le moteur et le générateur de rapport.

### Story 4.4 : Bloquer une régression sur la pull request, sans GitHub Advanced Security

En tant qu'ingénieure gardant un monorepo privé,
je veux que l'action commente la PR avec les alertes introduites par le diff,
afin que le défaut soit arrêté avant la fusion sans payer d'option GitHub.

**Critères d'acceptation :**

**Étant donné** un dépôt **privé** sans GitHub Advanced Security
**Quand** l'action s'exécute avec le seul `GITHUB_TOKEN` par défaut et la permission `pull-requests: write`
**Alors** elle publie son commentaire, sans autre secret ni service

**Étant donné** une PR introduisant une alerte
**Quand** l'action s'exécute
**Alors** le commentaire liste les alertes introduites par le diff, chacune avec un lien permanent vers la ligne du sink et vers celle de la source

**Étant donné** plusieurs push successifs sur la même PR
**Quand** l'action s'exécute à chaque fois
**Alors** un commentaire unique est mis à jour en place, sans empilement

**Étant donné** une analyse incomplète ou un dépassement du budget de temps de la CI
**Quand** l'action se termine
**Alors** elle publie un commentaire d'exécution partielle indiquant le périmètre couvert, et ne sort pas en échec pour cette raison

**Étant donné** le commentaire publié
**Quand** on en inspecte le contenu
**Alors** il ne contient aucun témoin exploitable, et aucune alerte n'y figure sans son témoin de niveau 1

### Story 4.5 : Supprimer une alerte sans effacer sa trace

En tant que lead technique adoptant l'outil sur un legacy en dette,
je veux pouvoir écarter une alerte en justifiant pourquoi, et que cet écart reste visible,
afin qu'une suppression ne devienne pas un mensonge d'audit.

**Critères d'acceptation :**

**Étant donné** une alerte portant un identifiant stable
**Quand** une annotation en ligne la supprime avec un motif
**Alors** l'alerte n'est plus comptée dans le verdict

**Étant donné** une annotation de suppression sans motif
**Quand** le scan s'exécute
**Alors** la suppression est refusée et l'alerte reste comptée

**Étant donné** des suppressions actives
**Quand** le rapport autoportant est généré
**Alors** il affiche leur nombre et leur détail

**Étant donné** un fichier reformaté qui déplace les lignes sans changer la structure du code
**Quand** le scan est relancé
**Alors** l'identifiant d'alerte est inchangé et la suppression reste effective, ce que vérifie un test de non-régression

**Étant donné** une version mineure ultérieure de l'outil
**Quand** le même défaut est réanalysé
**Alors** son identifiant est inchangé (§14)

> **Prérequis architecture :** la définition du hash structurel qui fonde l'identifiant d'alerte stable.

### Story 4.6 : Émettre du SARIF valide, annoncé comme secondaire

En tant qu'utilisateur d'une chaîne outillée qui consomme du SARIF,
je veux une sortie SARIF valide et une documentation honnête sur ses conditions d'affichage,
afin de l'intégrer sans découvrir après coup qu'elle exige une option payante.

**Critères d'acceptation :**

**Étant donné** un scan terminé
**Quand** la sortie SARIF est produite
**Alors** elle est conforme au schéma SARIF 2.1.0, validée par un validateur de schéma en CI, avec des identifiants de règles stables

**Étant donné** la documentation de la sortie SARIF
**Quand** on la lit
**Alors** elle qualifie SARIF de livrable secondaire et avertit explicitement que l'affichage dans l'onglet Security de GitHub sur dépôt privé requiert GitHub Advanced Security
**Et** cette phrase est vérifiée contre la tarification GitHub en vigueur avant publication (Q-4, `[HYP-3]`)

**Étant donné** une chaîne d'outils qui n'ingère pas de SARIF
**Quand** on utilise l'outil
**Alors** aucune fonctionnalité du produit n'est indisponible pour autant

**Étant donné** la sortie SARIF
**Quand** on l'inspecte
**Alors** elle ne contient aucun témoin exploitable

---

## Epic 5 : Faire confiance au projet, pas seulement à son code

Au terme de cet epic, une équipe qui évalue l'outil trouve de quoi décider : ce que l'outil ne détecte pas, d'où il vient — y compris l'échappement sans effet de son ancêtre — qui le maintient et ce qu'il devient si le mainteneur s'arrête. Aucune de ces stories ne dépend du code du moteur ; toutes conditionnent l'annonce publique, qui reste interdite tant que l'epic n'est pas livré.

### Story 5.1 : Trouver la divulgation de provenance avant d'installer l'outil

En tant que mainteneur évaluant un outil de sécurité,
je veux trouver dès le README l'historique du projet, défaut de sûreté compris,
afin de décider en connaissance de cause plutôt que de le découvrir moi-même et de perdre confiance.

**Critères d'acceptation :**

**Étant donné** le README du dépôt
**Quand** on l'ouvre sans défiler
**Alors** le lien vers la page de provenance est visible au-dessus de la ligne de flottaison, et non enfoui dans un sous-dossier

**Étant donné** la page de provenance
**Quand** on la lit
**Alors** elle décrit l'historique du query builder `db_query`, ce qu'était le défaut de la fonction `escape_data()` — un échappement no-op — et comment il a été découvert

**Étant donné** la page de provenance
**Quand** on cherche ce qui couvre désormais ce défaut
**Alors** elle nomme le cas du corpus de référence qui vérifie qu'un échappement no-op n'est pas reconnu comme assainisseur, avec un lien vers ce cas

**Étant donné** la page de provenance
**Quand** on en évalue le ton
**Alors** elle ne présente le défaut ni comme un atout ni comme un détail : elle l'énonce et en tire la thèse du produit — l'intuition ne suffit pas, il faut un témoin

### Story 5.2 : Consulter une documentation dont les pages de règles ne peuvent pas mentir

En tant que développeur venant de recevoir une alerte,
je veux atteindre depuis le message la page de la règle concernée,
afin de comprendre le défaut, sa correction, et pourquoi je pourrais légitimement désactiver la règle.

**Critères d'acceptation :**

**Étant donné** le site de documentation construit sous Astro Starlight
**Quand** le build s'exécute
**Alors** la sortie est entièrement statique, déployable sans serveur, et la recherche fonctionne sans service tiers

**Étant donné** le manifeste de règles du dépôt
**Quand** le script de génération des pages de règles s'exécute
**Alors** une page est produite par règle du manifeste, et aucune page de règle n'est éditée à la main
**Et** une règle ajoutée au manifeste sans nouvelle exécution du script fait échouer le build plutôt que de produire un site incomplet

**Étant donné** une alerte émise par le scanner
**Quand** on suit le lien de documentation qu'elle contient
**Alors** on atteint une URL stable correspondant à la règle, qui existe sur le site publié

**Étant donné** une page de règle générée
**Quand** on la lit
**Alors** elle contient dans l'ordre l'identifiant et le niveau, le code vulnérable, le code corrigé, et la façon de désactiver la règle avec les cas où c'est légitime

**Étant donné** le site publié
**Quand** on cherche ce que l'outil ne détecte pas
**Alors** une page « Limites » existe, atteignable depuis la navigation principale, et énumère les constructions non suivies

**Étant donné** l'internationalisation du site
**Quand** une page n'est pas traduite dans une langue
**Alors** le repli automatique sur la langue par défaut s'applique, sans page absente

### Story 5.3 : Lever les deux conditions d'annonce publique — succession et nom

En tant qu'équipe envisageant d'installer un scanner de sécurité en CI,
je veux savoir ce que devient le projet si son unique mainteneur s'arrête, et que son nom ne soit pas contesté,
afin que le facteur bus de 1 ne soit pas un motif de refus d'adoption.

**Critères d'acceptation :**

**Étant donné** un facteur bus de 1
**Quand** la politique de succession est publiée dans le dépôt
**Alors** elle nomme soit un co-mainteneur, soit la délégation des droits Packagist et GitHub à un second détenteur
**Et** elle décrit la procédure d'archivage annoncée si le mainteneur devient inactif 90 jours

**Étant donné** la politique de succession publiée
**Quand** on la date
**Alors** elle est antérieure à la première annonce publique, et la levée de Q-3 est consignée

**Étant donné** le nom `sqlguard` acté et le paquet Packagist vérifié libre
**Quand** la recherche d'antériorité de marque est menée
**Alors** elle couvre l'INPI, l'EUIPO et l'USPTO, ainsi que npm, PyPI et GitHub, et son résultat daté est consigné dans le dépôt

**Étant donné** une antériorité de marque trouvée
**Quand** le résultat est consigné
**Alors** l'annonce publique et le dépôt du paquet sont suspendus jusqu'à décision sur le nom, sans que le développement en cours soit bloqué (Q-1)

**Étant donné** que Q-5 n'est pas tranchée
**Quand** on prépare le premier commit public
**Alors** la licence retenue — MIT ou Apache-2.0 — est arrêtée et présente dans le dépôt avant ce commit (§12)
