# Revue adversariale — concept sqlguard

- **Contenu revu :** `_bmad-output/brainstorming/brainstorm-avenir-outil-open-source-2026-09-10/brainstorm-intent.md`
- **Lentille :** `adversarial` (demandée explicitement ; le contenu est de classe `docs`)
- **Date :** 2026-09-10
- **Verdict global :** la direction reste la meilleure des six candidates, mais le document qui la porte contient **quatre défauts porteurs** qui invalideraient le PRD s'ils n'étaient pas corrigés d'abord.

## Les quatre défauts porteurs

1. **La prémisse centrale est fausse** (constat 1) — « le marché de la vérification de l'accès base en PHP est vide ». Psalm embarque une taint analysis avec un type `sql` natif, active par défaut. Semgrep et SonarQube portent des règles SQLi PHP.
2. **Aucun budget de faux positifs** (constat 5) — variable de survie n°1 d'un linter de sécurité, absente du document. Le zéro-configuration est précisément le réglage qui les maximise.
3. **Aucun droit à gagner identifié** (constat 10) — aucun actif de crédibilité en sécurité, et pour seule provenance un query builder dont `escape_data()` livrait un no-op d'échappement.
4. **Le scoring NUF est auto-complaisant** (constats 2, 3) — le gagnant D est le seul cluster sans réserve de faisabilité alors qu'il dépend d'API tierces non contractuelles, et la feuille de route contredit le classement qu'elle invoque comme justification.

## Vérifications que j'ai conduites moi-même

Faits établis, non repris sur parole :

| Vérification | Résultat | Conséquence |
|---|---|---|
| Psalm fait-il du taint SQL ? | **Oui** — type de taint `sql` natif, `runTaintAnalysis` actif par défaut, sinks personnalisables par annotation, limites d'échappement documentées | Le constat 1 est confirmé ; la prémisse doit être réécrite |
| Existe-t-il un scanner PHP SQLi dédié qui a percé ? | **Non** — 5 dépôts GitHub correspondants, le plus gros à 15 étoiles, dont un `legacy-php-security-scanner` à **0 étoile** | La niche dédiée est vide, mais une tentative a déjà échoué : à traiter comme signal, pas comme espace libre |
| Le nom est-il libre ? | `sqlguard` : **aucun** paquet exact sur Packagist ; marque non vérifiée | Question ouverte partiellement fermée |

La lecture honnête n'est donc ni « marché vide » ni « marché occupé », mais : **aucun outil dédié n'a de traction, et le job est partiellement couvert par des généralistes qui exigent une configuration et un code typé.** C'est une revendication plus étroite, et défendable.

## Constats détaillés

| # | Localisation | Problème | Correctif exigé |
|---|---|---|---|
| 1 | Problème | « marché vide » factuellement faux (Psalm/Semgrep/Sonar) | Carte concurrentielle sourcée + revendication étroite et testable |
| 2 | Séquencement | La roadmap contredit le NUF qu'elle invoque : le gagnant D est en 2027, la V1 est A noté 22 | Retirer la justification décorative, énoncer le vrai critère (A est le moteur) |
| 3 | Tableau NUF | Notation complaisante : D et C sans réserve, alors que D dépend d'API tierces | Remplir les réserves, interdire à un dérivé d'hériter de la nouveauté du moteur (N(D) ≤ N(A)) |
| 4 | Séquencement étape 2 | D est une fonctionnalité que les éditeurs de CLI internaliseront ; les modèles paramètrent déjà de plus en plus par défaut | Traiter D comme un canal du moteur A, écrire ce qui reste si l'éditeur le livre nativement |
| 5 | Périmètre initial | **Aucun budget de faux positifs** | Corpus annoté figé + seuils rappel/FP chiffrés + invariant « pas d'alerte sans témoin » |
| 6 | Perf (<30 s, <2 s) | Cibles posées sans mesure ; une taint interprocédurale n'est pas incrémentale sans résumés persistés | Banc de mesure semaine 1, budgets fixés après ; le cache de résumés est une exigence V1 ou le pre-commit est abandonné |
| 7 | Positionnement | « le concurrent est le consultant » = erreur de catégorie : l'audit est acheté pour une signature engageant une responsabilité | Deux concurrents distincts : créneau d'installation (PHPStan/Psalm) vs consultant = canal |
| 8 | SARIF jour 1 | L'onglet Security n'est gratuit que sur dépôts publics ; la cible est en dépôt privé (GHAS payant) | Livrable primaire = commentaire de PR / rapport autoportant ; SARIF secondaire |
| 9 | Document entier | Zéro validation externe : 165 entrées sont l'auteur avec lui-même, les 8 JTBD sont analytiques | Porte de validation : 8-12 entretiens + preuve de demande passive, chaque JTBD étiqueté hypothèse/validé |
| 10 | Document entier | Aucun actif, aucun avantage injuste ; provenance = le no-op `escape_data()` | Section « Actifs et avantage » honnête + stratégie de crédibilité substitutive + divulgation assumée de l'historique |
| 11 | Risques | Engagement sur 4 exercices sans capacité budgétée ; facteur bus = 1 | Écrire heures/semaine et durée ; réduire la V1 à **un seul sink** (leçon Medoo, tirée puis ignorée) |
| 12 | Document entier | Aucun critère d'arrêt falsifiable | Pré-enregistrer 3 seuils datés + anti-métrique (téléchargements ≠ adoption) |
| 13 | Positionnement vs étape 4 | La contrainte « ne jamais concurrencer un framework » est violée par le proxy PDO runtime (C) | Exclure C, ou rendre la contrainte testable et y soumettre C par écrit |
| 14 | Document entier | Aucun modèle économique alors que des personas solvables sont identifiés | Nommer le modèle, même « aucun, portfolio, budget 6 mois » ; tracer la frontière open/commercial maintenant |
| 15 | Périmètre initial | « MySQL uniquement » est une restriction inversée : le taint est agnostique du dialecte | Cœur agnostique ; le dialecte ne borne que la génération de témoin. Ce qui borne la V1 = le nombre de **sinks** |
| 16 | Problème | Chiffres non sourcés (70 %, 40 %, « part majeure générée par IA », 200k lignes) ; l'injection est passée au rang 3 de l'OWASP 2021 | Sourcer ou étiqueter en hypothèse ; mesurer directement (200 dépôts échantillonnés) — ce chiffre serait le premier actif de crédibilité |
| 17 | Étape 5 / cluster F | L'observatoire WordPress est un passif non finançable, et Patchstack/WPScan/Wordfence occupent déjà le terrain | Reclasser en passif conditionnel ; version dégradée = statistiques agrégées anonymes, jamais de classement nommé |
| 18 | Témoin d'exploit | Un rapport contenant des charges utiles armées contre du code tiers, committé en CI : responsabilité non traitée | Deux niveaux : chaîne de propagation (défaut, sans charge) vs témoin exploitable (opt-in, sortie séparée) |

## Ce que la revue ne remet pas en cause

- Le refus d'être un query builder. Les 18 constats attaquent l'exécution du pivot, aucun ne défend le retour au builder.
- Le diagnostic « les outils PHP massivement installés jugent ou transforment le code, ils ne vivent pas dedans » (Composer, PHPStan, Rector).
- La lecture que la douleur porte sur la confiance dans le SQL existant, pas sur son écriture.
