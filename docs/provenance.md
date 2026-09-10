# Provenance et divulgation

Cette page existe parce qu'un outil de sécurité doit dire d'où il vient. Elle est liée depuis le
README, au-dessus de la ligne de flottaison, et non rangée dans un sous-dossier qu'on trouve par
accident.

> Story 5.1 · exigence PRD FR-20

## L'historique

Ce dépôt a commencé en 2019 comme `db_query`, un constructeur de requêtes SQL sur PDO. Un de plus :
Doctrine, Eloquent, Cycle, Atlas et Medoo occupaient déjà le terrain. Il n'a jamais eu
d'utilisateurs.

## Le défaut

`db_query` contenait une méthode nommée `escape_data()`. Son rôle annoncé était d'échapper les
valeurs avant leur insertion dans la requête. Voici ce qu'elle faisait, littéralement :

```php
foreach ($arr as $keys => $value) {
    addslashes(($arr[$keys] = '\'' . $value . '\''));
}
```

L'affectation a lieu **à l'intérieur** de l'appel, et la valeur de retour d'`addslashes()` est
jetée. `addslashes()` est une fonction pure : elle ne modifie pas son argument, elle renvoie une
copie échappée. Cette copie n'était affectée à rien.

**La fonction dont le nom promettait la sécurité n'échappait rien du tout.** Zéro caractère. Et
comme les guillemets étaient ajoutés *avant* l'appel, elle aurait été fausse même si la valeur de
retour avait été conservée.

Deux conséquences aggravantes : aucune requête n'était préparée — tout partait dans
`PDO::query()` par concaténation — et le code contenait cette signature :

```
* ajout de where dans la requete preparer
```

Un commentaire décrivant des requêtes préparées dans un fichier qui n'en contenait aucune.

## Comment il a été découvert

Pas par un audit, pas par un rapport de vulnérabilité, pas par un utilisateur. Par une revue de
code automatisée du dépôt, en 2026-09-09, sept ans après l'écriture. Le défaut a été confirmé par
exécution, pas par lecture :

```
resultat escape_data('data') : 'a'b'
addslashes attendu          : \'a\'b\'
```

Trois autres défauts découverts le même jour rendaient la bibliothèque inutilisable en l'état :
neuf constantes jamais définies (donc `Error` fatale sur PHP 8 dans chaque chemin d'erreur), un
`INSERT INTO` concaténé sans espace, et un autoloader dont le préfixe de namespace était mal
orthographié. Ces défauts prouvent que ce code n'avait **jamais été exécuté**. C'est aussi pour
cela que l'échappement no-op n'a jamais blessé personne : la bibliothèque n'avait pas
d'utilisateurs.

Le correctif est public : les valeurs passent désormais par `prepare()` et des paramètres liés,
les identifiants par une liste blanche, et 31 vérifications automatisées couvrent le chemin.

## Ce qui couvre ce défaut désormais

Le corpus de référence de `sqlguard` contient un cas dédié :

- **Cas `sanitizer-noop-addslashes`** — vérifie qu'une fonction locale qui appelle un échappeur
  sans conserver sa valeur de retour **n'est pas** reconnue comme assainisseur, et que la chaîne
  de propagation traverse donc l'appel.
- Emplacement prévu : `corpus/cas/sanitizer-noop-addslashes/`

**Ce cas n'existe pas encore.** Il est introduit par la story 2.2 du découpage en epics, qui est
située après la porte de validation K-1. Ce lien est donc une intention datée, pas un fait : il
sera remplacé par un lien réel à la création du corpus. Annoncer autrement serait reproduire
exactement le défaut que cette page documente — un nom qui promet plus que ce que le code fait.

## Ce que ce défaut prouve, et pourquoi c'est la thèse du produit

Ce n'est ni un atout ni un détail.

Ce n'est pas un atout : personne ne devrait faire confiance à un outil de sécurité au motif que
son auteur a écrit une faille. L'expérience d'une erreur ne confère aucune compétence.

Ce n'est pas un détail : la fonction s'appelait `escape_data`, elle était `private`, elle
paraissait juste à la lecture, et elle a survécu sept ans dans un dépôt public sans que personne
— l'auteur compris — remarque qu'elle ne faisait rien.

Ce qu'il prouve est plus étroit et plus utile : **la lecture attentive du code par son auteur ne
détecte pas ce genre de défaut.** L'intuition ne suffit pas. Il faut une exécution qui échoue, ou
une analyse qui produit une preuve.

C'est exactement la thèse de `sqlguard`, et l'origine de son invariant central : **aucune alerte
n'est publiée sans témoin.** Un outil qui affirme sans démontrer répète l'erreur d'`escape_data` à
plus grande échelle — il rassure sans protéger.
