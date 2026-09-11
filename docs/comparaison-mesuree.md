# Comparaison mesurée — sqlguard, Psalm, Semgrep

**Date :** 2026-09-11 (3ᵉ mesure, corpus durci) · **Corpus :** 35 cas, 192 lignes · **Reproductible :** `php sqlguard/packages/bench/bin/bench-compare`
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
| **sqlguard** | 21 | 2 | 0 | 91,3 % |
| **psalm — configuration par défaut** | 0 | 23 | 0 | **0,0 %** |
| **psalm — stub PDO chargé** | 16 | 7 | 0 | 69,6 % |
| **semgrep** | 19 | 4 | 5 | 82,6 % |

### ⚠ Ce tableau est biaisé en faveur de sqlguard, et voici comment

Le corpus a d'abord été durci avec 12 cas de vrai legacy. **sqlguard est alors tombé à 69,6 %,
à égalité exacte avec Psalm configuré, et Semgrep le battait à 82,6 %.** Mesure de référence,
horodatée, conservée ci-dessous.

J'ai ensuite corrigé le moteur **précisément sur les cas où il perdait** : appels statiques,
`self::`, `call_user_func`, littéraux de tableau, propriétés d'objet, fermetures. D'où les
91,3 %.

Psalm et Semgrep n'ont pas eu cette possibilité. Ce tableau ne compare donc pas la qualité de
trois outils : il mesure **à quel point j'ai ajusté le mien à mon propre jeu d'essai.** C'est du
surapprentissage au banc, assumé et déclaré.

| Mesure | sqlguard | psalm configuré | semgrep |
|---|---|---|---|
| Corpus durci, **avant** correctifs | 69,6 % | 69,6 % | 82,6 % |
| Corpus durci, **après** correctifs ciblés | 91,3 % | 69,6 % | 82,6 % |

La seule lecture défendable : **un corpus dit ce qu'un outil rate, il ne dit pas qu'un outil est
meilleur.** La valeur de ce travail est la liste des angles morts, pas le classement.

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

## Les angles morts, par outil — c'est la partie utile

Chaque outil rate des choses différentes. Un lecteur qui choisit un outil devrait lire ceci
plutôt que les pourcentages.

| Construction | sqlguard | psalm configuré | semgrep |
|---|---|---|---|
| Conteneur d'injection de dépendances | trouve | **rate** | trouve |
| `call_user_func('nom', …)` | trouve | **rate** | trouve |
| Fermeture appelée localement | trouve | **rate** | trouve |
| Propriété d'objet écrite ailleurs | trouve | trouve | trouve |
| `$_SERVER['HTTP_*']` | trouve | **rate** | **rate** |
| `global $x` | **rate** | **rate** | **rate** |
| Variables variables `$$name` | **rate** | **rate** | **rate** |
| Cast `(int)` reconnu comme sûr | oui | oui | **non → faux positif** |
| `$db->quote()` reconnu comme sûr | oui | oui | **non → faux positif** |
| Assaini dans toutes les branches | oui | oui | **non → faux positif** |

Deux constructions échappent **aux trois outils** : `global` et les variables variables. Un
lecteur qui en a dans son code doit savoir qu'aucune analyse statique disponible ne le couvre.
sqlguard les déclare au moins comme limites d'analyse ; il ne rend pas un vert trompeur.

Les trois faux positifs de Semgrep viennent tous du même choix de conception : il travaille par
motif et ne modélise pas les assainisseurs. Ce n'est pas un défaut d'implémentation, c'est le prix
d'une couverture multi-langages que sqlguard n'aura jamais.

## Ce que cette mesure ne dit pas

- Rien sur du code réel. 89 lignes écrites pour l'exercice ne prédisent pas le comportement sur
  200 kLOC de legacy.
- Rien sur la performance. Aucun banc n'a été exécuté (story 2.1, non faite).
- **Sur l'interprocédural, ma prédiction était fausse.** J'avais écrit que Psalm gagnerait sur ce
  terrain. L'interprocédural a été implémenté (story 3.4 : condensation SCC par Tarjan, fixpoint
  borné, profondeur vérifiée à 7 sauts) et 5 cas ont été ajoutés au corpus — dont un à 3 sauts
  inter-fichiers et un de récursion mutuelle. **Psalm configuré passe les trois cas
  interprocéduraux, comme sqlguard.** Aucun avantage ne s'est révélé, dans aucun sens.

  La conclusion honnête n'est pas « sqlguard égale Psalm » mais **« mes cas sont trop faciles pour
  les discriminer »**. Un corpus qui départagerait réellement exigerait des conteneurs
  d'injection de dépendances, des tableaux de callables, des appels via `__call`, des fabriques —
  c'est-à-dire ce que contient du vrai code legacy et pas mon corpus.
- Rien sur `mysqli` ni `wpdb`, hors périmètre V1.

## Note d'environnement

Sous macOS Apple Silicon avec un PHP x86_64 (Rosetta), les processus enfants héritent de x86_64 et
l'extension native de Semgrep (`pydantic_core`, arm64) refuse de se charger : Semgrep sort en
traceback. Le harnais préfixe donc son appel par `arch -arm64`. Sans cela il aurait déclaré
Semgrep « indisponible » et publié une comparaison **silencieusement amputée d'un concurrent** —
exactement le genre de défaut que ce projet prétend combattre.
