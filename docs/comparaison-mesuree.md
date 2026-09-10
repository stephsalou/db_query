# Comparaison mesurée — sqlguard, Psalm, Semgrep

**Date :** 2026-09-10 · **Corpus :** 18 cas, 98 lignes · **Reproductible :** `php sqlguard/packages/bench/bin/bench-compare`
**Données brutes :** [`corpus/comparaison.json`](../corpus/comparaison.json)

> Exigence PRD FR-13, story 2.4. Règle de méthode : les défaites de `sqlguard` sont publiées
> comme ses victoires. Un harnais qui ne peut pas perdre ne mesure rien.

## Versions mesurées

| Outil | Version | Configuration |
|---|---|---|
| sqlguard | 0.1.0 | par défaut, aucune configuration |
| Psalm | 6.17.0 | **deux** variantes, voir ci-dessous |
| Semgrep | 1.176.1 | `--config=p/php` (jeu de règles public) |

## Résultats

| Outil | Vrais positifs | Manques | Faux positifs | Rappel |
|---|---|---|---|---|
| **sqlguard** | 10 | 0 | 0 | 100,0 % |
| **psalm — configuration par défaut** | 0 | 10 | 0 | **0,0 %** |
| **psalm — stub PDO chargé** | 8 | 2 | 0 | 80,0 % |
| **semgrep** | 8 | 2 | 3 | 80,0 % |

## Lisez d'abord ceci : le 100 % de sqlguard ne veut presque rien dire

**J'ai écrit le corpus et le moteur.** Un outil qui obtient 100 % sur des cas rédigés en même
temps que lui ne démontre rien d'autre que sa propre cohérence interne. Ce chiffre n'est pas un
argument, et il ne doit jamais être cité seul.

Ce qui a de la valeur dans ce tableau, ce sont **les trois autres colonnes** : elles décrivent le
comportement d'outils que je n'ai pas écrits, sur des cas qu'ils n'ont pas vus.

Le corpus est également **trop petit pour un taux de faux positifs**. 3 faux positifs sur 89
lignes s'extrapolent à 337 FP/10 kLOC, un chiffre absurde. Les colonnes FP/10 kLOC sont donc
omises de ce tableau : elles n'auront de sens qu'à partir de plusieurs dizaines de milliers de
lignes de code réel.

## Le résultat qui compte : Psalm ne détecte rien sur PDO en configuration par défaut

Psalm **sait** détecter ces injections. Ses stubs déclarent bien `@psalm-taint-sink sql` sur
`PDO::exec`, `PDO::prepare` et `PDO::query` (`stubs/extensions/pdo.phpstub`, lignes 97, 114, 121).

Mais dans une configuration minimale, il n'en trouve **aucune**. Établi en A/B strict, même
fichier, même commande, une seule différence :

```xml
<!-- A : 0 détection -->
<psalm errorLevel="1"><projectFiles><directory name="target"/></projectFiles></psalm>

<!-- B : détection -->
<psalm errorLevel="1"><projectFiles><directory name="target"/></projectFiles>
    <stubs><file name="vendor/vimeo/psalm/stubs/extensions/pdo.phpstub"/></stubs>
</psalm>
```

Cause : l'extension PDO étant chargée dans le PHP hôte, Psalm utilise la classe réfléchie, qui ne
porte aucune annotation, et son propre stub annoté est ignoré. Le résultat est un **silence**, pas
une erreur : rien n'avertit l'utilisateur que l'analyse SQL ne couvre pas PDO.

C'est la seule revendication de différenciation que ce projet est autorisé à porter, et elle est
étroite : **rendement en configuration par défaut**, pas supériorité analytique. Une fois Psalm
configuré, il est à 88,9 % avec zéro faux positif — c'est-à-dire au niveau de sqlguard sur ce
corpus, avec des années de maturité en plus.

## Où sqlguard perd, ou risque de perdre

**Psalm et Semgrep manquent les deux cas `$_SERVER`** (`HTTP_USER_AGENT`, `HTTP_REFERER`), que
sqlguard détecte. Ces en-têtes sont contrôlés par le client, donc sqlguard a raison sur le fond.

Mais la première version de sqlguard traitait **tout** `$_SERVER` comme non fiable, ce qui aurait
produit des faux positifs sur `DOCUMENT_ROOT` ou `SCRIPT_NAME`. Aucun cas du corpus ne le
révélait : **un faux positif latent que ma propre mesure ne voyait pas.** Corrigé — seules les
clés `HTTP_*` et une liste explicite de clés contrôlables sont marquées ; une clé non littérale
est marquée par prudence avec une limite d'analyse consignée. Deux cas ont été ajoutés au corpus
pour fixer la distinction, dont un cas *propre* qui aurait échoué avant correction.

**Semgrep produit 3 faux positifs** (`clean-cast-int`, `clean-cast-float`, `clean-quote`) parce
qu'il travaille par motif et ne modélise pas les assainisseurs. C'est un choix de conception, pas
un défaut d'implémentation : il échange de la précision contre une couverture multi-langages que
sqlguard n'aura jamais.

**Psalm m'a corrigé.** Sa première exécution signalait `clean-addslashes-conserve`, un cas que
mon corpus annotait *propre*. Psalm avait raison : `addslashes()` n'échappe pas selon le jeu de
caractères de la connexion et reste contournable. **Mon corpus était faux et mon moteur traitait
`addslashes` comme un assainisseur** — un faux négatif que seule la comparaison a révélé. Les
deux ont été corrigés, le cas est devenu `tp-addslashes-insuffisant`, et c'est aujourd'hui l'un
des 9 vrais positifs. Sans cette comparaison, le défaut serait toujours là.

## Ce que cette mesure ne dit pas

- Rien sur du code réel. 89 lignes écrites pour l'exercice ne prédisent pas le comportement sur
  200 kLOC de legacy.
- Rien sur la performance. Aucun banc n'a été exécuté (story 2.1, non faite).
- Rien sur l'interprocédural : sqlguard n'en fait pas (story 3.4, non faite), et le corpus ne
  contient aucun cas qui l'exigerait. Psalm, lui, en fait. **Sur ce terrain il gagnerait**, et le
  corpus actuel est incapable de le montrer.
- Rien sur `mysqli` ni `wpdb`, hors périmètre V1.

## Note d'environnement

Sous macOS Apple Silicon avec un PHP x86_64 (Rosetta), les processus enfants héritent de x86_64 et
l'extension native de Semgrep (`pydantic_core`, arm64) refuse de se charger : Semgrep sort en
traceback. Le harnais préfixe donc son appel par `arch -arm64`. Sans cela il aurait déclaré
Semgrep « indisponible » et publié une comparaison **silencieusement amputée d'un concurrent** —
exactement le genre de défaut que ce projet prétend combattre.
