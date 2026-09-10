# Intent — sqlguard (nom de travail)

Source : session de brainstorming `brainstorm-avenir-outil-open-source-2026-09-10`. Ce document fixe la direction retenue ; il n'est pas un compte rendu de session. Destiné à alimenter directement `bmad-prd`.

## Problème

Il n'existe aucun outil PHP capable de répondre de façon automatisée et prouvable à la question « mon accès base de données est-il sûr ? ». Le marché du query builder est saturé et devenu une commodité ; le marché de la **vérification** de l'accès base en PHP est vide.

Trois faits structurent le problème :
- La douleur n'est pas d'écrire du SQL, mais d'avoir confiance dans le SQL déjà écrit — par un tiers, par du legacy, ou par un LLM.
- En 2026 une part majeure du code d'accès base est générée par IA et n'est relue par personne.
- PHP concentre la dette SQL la plus massive du monde (≈70 % du web, WordPress ≈40 %), sous forme de concaténation manuelle.

## Direction retenue

`sqlguard` : un moteur d'analyse de sûreté SQL pour PHP qui **juge** le SQL au lieu de l'écrire.

- Cœur : analyse statique de flux (taint analysis) des entrées non fiables jusqu'aux sinks base de données — PDO, mysqli, wpdb.
- Sortie : SARIF dès le jour 1 (affichage natif dans l'onglet Security de GitHub), plus un rendu CLI tenant sur un écran avec un score unique.
- Adoption à coût zéro : aucune ligne de code applicatif à changer, aucune API à adopter, zéro configuration. Une commande : `sqlguard scan .`, valeur rendue en moins de 30 secondes sur un dépôt inconnu.
- Le query builder `db_query` existant n'est plus le produit : il devient une **cible de migration** du codemod.

## Positionnement et contrainte fondatrice

- **Contrainte fondatrice, non négociable : ne jamais concurrencer Doctrine, Eloquent ou une fonctionnalité intégrée de framework.** En cessant d'écrire du SQL, l'outil devient complémentaire de tout l'écosystème plutôt que le 12e concurrent.
- Le concurrent réel n'est pas un ORM : c'est le consultant en audit de sécurité facturé à la journée. Rendre gratuit, automatique et reproductible ce qui était payant et manuel.
- Modèle de référence : Composer, PHPStan, Rector — les outils PHP massivement installés ne vivent pas dans le code applicatif, ils le jugent ou le transforment.
- Cœur ouvert obligatoire : sans cœur ouvert, pas d'adoption virale.
- Cibles prioritaires : le legacy que personne ne veut toucher, pas les projets neufs. WordPress est un marché, pas un repoussoir.

## Utilisateurs et jobs-to-be-done

Job commun à tous : **transformer une inquiétude non mesurable en un résultat vérifiable.**

| Utilisateur | Job | Ce qu'il achète |
|---|---|---|
| Dev junior | « écrire une requête sans me faire pirater » | de la tranquillité, pas une API fluide |
| Lead technique | « prouver que notre app n'est pas injectable » | une preuve, un rapport |
| RSSI | « inventorier les points d'accès base et leur exposition » | une cartographie (+ règles qu'il écrit lui-même) |
| Ops | « qu'aucun DELETE sans WHERE n'atteigne la prod » | un garde-fou d'exécution |
| DPO | « savoir quelles requêtes lisent des colonnes PII » | de la conformité |
| Mainteneur legacy | « migrer 200k lignes de SQL concaténé sans tout casser » | un codemod |
| Dev qui relit du code IA | « mon copilote a écrit cette requête, est-elle sûre ? » | un vérificateur de code généré |
| Équipe en audit | « l'auditeur arrive dans deux semaines » | un rapport signable |

Point d'arbitrage produit : un artefact unique doit être lisible à la fois par le dev (zéro friction) et par l'auditeur (preuve).

## Périmètre initial

Dans le périmètre (V1) :
- Analyse statique taint des sources non fiables vers les sinks PDO / mysqli / wpdb.
- **MySQL / MariaDB uniquement** — là où se concentre la dette PHP legacy.
- Sortie SARIF + CLI. Zéro configuration. Exécution sans serveur.
- Chaque alerte montre l'exploit concret qui fonctionnerait, pas un message abstrait.

Hors périmètre V1 (explicitement écarté) :
- Tout query builder ou API d'écriture de SQL.
- Support multi-SGBD (14 dialectes au lancement = anti-pattern identifié).
- Parser SQL maison : s'appuyer sur `nikic/php-parser` et un parser SQL existant.
- Garde-fou runtime, politique YAML, cartographie PII, observatoire WordPress — reportés (voir séquencement).

## Séquencement

Justifié par les scores NUF (Nouveau + Utile + Faisable, /10 chacun) des six clusters candidats :

| Cluster | N | U | F | Total | Réserve de faisabilité |
|---|---|---|---|---|---|
| A — Scanner statique d'injection (PDO/mysqli/wpdb, SARIF) | 5 | 9 | 8 | **22** | Psalm/Semgrep couvrent partiellement |
| B — Codemod concaténation → requêtes préparées | 8 | 9 | 6 | **23** | équivalence sémantique difficile sur SQL dynamique |
| C — Garde-fou runtime (proxy PDO + politique YAML) | 7 | 8 | 7 | **22** | — |
| D — Vérification du SQL généré par IA | 9 | 8 | 8 | **25 — gagnant** | — |
| E — Cartographie PII | 8 | 7 | 5 | **20** | exige la connaissance du schéma |
| F — Observatoire de sûreté des plugins WordPress | 9 | 8 | 6 | **23** | échelle + risque juridique du classement public |

Feuille de route :
1. **T4 2026** — Scanner seul (A) : une commande, sortie SARIF, MySQL uniquement.
2. **2027** — Vérification du SQL généré par IA (D) : hook de CLI de codage qui refuse le SQL non sûr au moment où l'IA l'écrit. Coin d'entrée principal.
3. **2027** — Codemod (B) : la détection crée la douleur, le codemod la soulage.
4. **2028** — Garde-fou runtime + politique YAML versionnée (C) : exige une confiance déjà établie.
5. **2029** — Cartographie PII (E) puis observatoire WordPress (F).

## Décisions d'architecture

- **A est le moteur commun.** Scanner, vérification IA et codemod ne sont pas trois produits : ils partagent un unique moteur d'analyse de flux (taint) vers les sinks base. Le moteur doit être conçu dès la V1 comme réutilisable par les trois consommateurs.
- **D et B sont les coins d'entrée** ; C, E, F sont des extensions du même moteur.
- Cœur générique + adaptateurs par écosystème (WordPress, Symfony, Laravel) sous forme de paquets de règles — trait ESLint/Semgrep.
- Instrumenter PDO comme point de passage commun côté runtime, plutôt que de parser tous les dialectes.
- Fusion des traces statique et runtime : le statique rate le SQL dynamique, le runtime rate le code non exécuté.
- Runtime en deux modes : observation à coût quasi nul, puis blocage ciblé. Jamais de refus silencieux ou indiagnosticable.
- Politique de sécurité par défaut opinionnée (refus par défaut, allowlist explicite), déclarative, en YAML versionné et revu en PR, lisible par un non-développeur.
- Niveaux de sûreté progressifs (trait PHPStan) : on adopte au niveau 1 et on monte.
- Accélérateurs de distribution, à traiter comme des exigences transverses et non comme des produits : SARIF, badge de sûreté README, scan incrémental sur le diff seul (< 2 s en pre-commit), PR de correction automatiques (trait Dependabot), lien alerte → ligne de code.
- Case vide du marché à occuper : relier une requête SQL à sa ligne de code PHP **et** à son verdict de sûreté **et** au champ PII touché.

## Risques et questions ouvertes

- **Équivalence sémantique du codemod (B)** : garantir qu'une transformation concaténation → requête préparée préserve le comportement sur du SQL dynamique est le point de faisabilité le plus faible. Nécessite une stratégie de preuve d'équivalence et un périmètre de patterns strictement borné.
- **Recouvrement avec Psalm (taint analysis) et Semgrep** : le cluster A est le moins « nouveau » (N=5). La différenciation doit être explicitée dans le PRD — sinks PHP spécifiques (mysqli/wpdb), zéro configuration, pédagogie de l'exploit, chaînage vers codemod et runtime — sinon le scanner seul ne justifie pas l'adoption.
- **Risque juridique et réputationnel de l'observatoire WordPress (F)** : publier un classement de sûreté de plugins tiers nommés expose à des contestations. À cadrer juridiquement avant toute publication ; ne pas engager avant 2029.
- **Disponibilité du nom** : `sqlguard` est un nom de travail provisoire. Disponibilité Packagist et antériorité de marque à vérifier avant toute annonce publique.
- **Faisabilité PII (E)** : dépend de la connaissance du schéma de base, non disponible par analyse statique seule. Mécanisme d'acquisition du schéma à définir.
- **Vitesse de réponse communautaire** : identifiée comme avantage produit décisif (échec de Propel). À traiter comme un engagement opérationnel, pas comme un détail.
