# Dépôts de référence du banc de performance

Le banc mesure sqlguard sur du **vrai code PHP publiquement accessible**, pas sur des fichiers
écrits pour l'occasion. Les trois paliers sont des paquets Composer, installés séparément parce
que leurs contraintes de version sont incompatibles entre elles.

## Reproduire la mesure

```sh
export SQLGUARD_BENCH_DIR=/tmp/sgbench
for t in small medium large; do
    mkdir -p "$SQLGUARD_BENCH_DIR/$t"
    cp corpus/bench-fixtures/$t/composer.json "$SQLGUARD_BENCH_DIR/$t/"
    (cd "$SQLGUARD_BENCH_DIR/$t" && composer install --no-interaction --no-scripts)
done

php -d memory_limit=8G sqlguard/packages/bench/bin/bench-perf
```

Aucune autre configuration n'est nécessaire. Les résultats sont écrits dans
[`corpus/performance.json`](../performance.json) et commentés dans
[`docs/performance.md`](../../docs/performance.md).

## Les paliers

| Palier | Contenu | Ordre de grandeur |
|---|---|---|
| `petit` | `nikic/php-parser` 5.8.0 | ~25 000 lignes |
| `moyen` | `laravel/framework` ^11 et ses dépendances | ~595 000 lignes |
| `grand` | les trois arbres cumulés | ~1 260 000 lignes |

**Écart assumé par rapport à la story 2.1**, qui demandait ~20 k / ~200 k / ~2 M : le palier moyen
est trois fois plus gros que demandé, et le palier grand n'atteint pas 2 M. Aucun paquet Composer
public unique ne fournit proprement ces tailles exactes. Les ordres de grandeur restent séparés
d'un facteur ~24 puis ~2, ce qui suffit à observer l'évolution du coût. Cet écart est déclaré
plutôt que corrigé par un palier artificiel, qui n'aurait mesuré que du code écrit pour le banc.

Le palier `grand` **inclut** les deux autres : c'est voulu, cela évite de télécharger un
quatrième arbre pour un gain nul.
