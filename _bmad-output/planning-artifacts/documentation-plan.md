# Plan de documentation — sqlguard

> **Statut : IMPLÉMENTÉ le 2026-09-10** (story 5.2). Le site vit dans `website/`, pas dans `docs/`
> comme prévu initialement : `docs/` héberge déjà la provenance et les artefacts de validation.
> Build vérifié : 11 pages, recherche Pagefind locale, repli i18n fonctionnel.
> Versions réelles retenues : Astro 7.3.2, @astrojs/starlight 0.42.0.
> Source : `_bmad-output/brainstorming/brainstorm-avenir-outil-open-source-2026-09-10/`

## 1. Ce que la doc doit accomplir

Pour un outil d'analyse de sûreté SQL, la documentation **est** une partie du produit, pas son emballage. Trois fonctions distinctes :

| Fonction | Lecteur | Exigence technique induite |
|---|---|---|
| Convaincre en 30 secondes | dev qui découvre l'outil | page d'accueil avec démo de sortie réelle, pas un slogan |
| Référencer chaque règle | dev qui vient de recevoir une alerte | une URL stable par règle, atteignable depuis le message d'erreur |
| Prouver la conformité | lead, RSSI, auditeur | pages citables, versionnées, exportables |

La contrainte dimensionnante est la **deuxième** : chaque alerte émise par le scanner doit contenir un lien vers la page de sa règle (`sqlguard.dev/rules/sql-injection-concat`). Avec plusieurs dizaines de règles, ces pages ne peuvent pas être écrites à la main une par une — elles doivent être **générées depuis le manifeste de règles du dépôt**, sinon la doc et le code divergent au premier ajout de règle. C'est le critère qui départage les outils.

## 2. Choix de l'outil

### Comparaison

| Outil | Pour | Contre | Verdict |
|---|---|---|---|
| **Astro Starlight** | i18n natif (fallback + RTL), sidebar auto-générée depuis un répertoire, recherche intégrée (Pagefind, sans service tiers), `editLink`, Expressive Code, sortie 100 % statique | écosystème Astro à apprendre | **Retenu** |
| Docusaurus | versioning mature, très gros écosystème, React | plus lourd, recherche = Algolia (service tiers) ou plugin local moins bon | Alternative de repli |
| VitePress | très rapide, minimal, excellent DX | i18n plus manuel, moins outillé pour de la génération de pages en masse | Écarté |
| MkDocs Material | excellent produit, très bonne recherche | chaîne Python sur un projet PHP : deuxième runtime à installer en CI | Écarté |
| Mintlify | rendu superbe, zéro effort | propriétaire et payant — incompatible avec un positionnement OSS crédible | Écarté |
| Nextra | bon si déjà sur Next.js | pas le cas ici, surdimensionné | Écarté |

### Pourquoi Starlight

1. **La génération de pages de règles est directe.** `{ autogenerate: { directory: 'rules' } }` construit la navigation depuis le répertoire ; un script de build convertit le manifeste de règles en un fichier MDX par règle. La doc ne peut plus mentir sur les règles existantes.
2. **Recherche sans dépendance externe.** Pagefind indexe au build et s'exécute côté client. Pas de clé Algolia, pas de quota, pas de service à surveiller — cohérent avec un outil qu'on installe pour réduire son exposition.
3. **i18n de première classe.** Français et anglais dès le départ, avec repli automatique sur la langue par défaut quand une page n'est pas traduite. Cela évite l'effet « moitié du site en 404 » pendant la traduction.
4. **Statique intégral.** Se déploie sur GitHub Pages ou Cloudflare Pages gratuitement, sans serveur — donc sans surface d'attaque supplémentaire pour un projet dont le sujet est la sécurité.

**Réserve honnête :** Docusaurus reste meilleur sur le versioning multi-versions. Tant que l'outil n'a pas de v1 stable, la question ne se pose pas ; si un jour il faut maintenir la doc de trois versions majeures en parallèle, ce choix mérite d'être revu.

## 3. Architecture du site

```
website/
├─ astro.config.mjs
├─ package.json
├─ scripts/
│  └─ build-rule-pages.mjs        # manifeste de règles -> src/content/docs/{fr,en}/rules/*.mdx
└─ src/content/docs/
   ├─ fr/
   │  ├─ index.mdx                # accueil : le problème, la sortie réelle, l'installation
   │  ├─ demarrage/
   │  │  ├─ installation.mdx       # composer require --dev
   │  │  ├─ premier-scan.mdx       # sqlguard scan . en moins de 30 s
   │  │  └─ integration-ci.mdx     # GitHub Actions + SARIF dans l'onglet Security
   │  ├─ concepts/
   │  │  ├─ analyse-de-flux.mdx    # ce qu'est le taint tracking, en clair
   │  │  ├─ niveaux-de-surete.mdx  # niveaux 0 à 5, adoption progressive
   │  │  └─ limites.mdx            # ce que l'outil NE détecte pas — page obligatoire
   │  ├─ rules/                    # GÉNÉRÉ, jamais édité à la main
   │  ├─ guides/
   │  │  ├─ legacy-wordpress.mdx
   │  │  ├─ verifier-du-sql-genere-par-ia.mdx
   │  │  └─ migration-requetes-preparees.mdx
   │  ├─ reference/
   │  │  ├─ cli.mdx
   │  │  ├─ configuration.mdx
   │  │  └─ format-sarif.mdx
   │  └─ conformite/
   │     ├─ rapport-d-audit.mdx
   │     └─ cartographie-pii.mdx
   └─ en/                          # même arborescence, repli automatique sur fr
```

### Gabarit d'une page de règle (généré)

Chaque page contient, dans cet ordre : identifiant et niveau ; le code vulnérable ; **l'exploit concret qui fonctionnerait** ; le code corrigé ; comment désactiver la règle et pourquoi on pourrait légitimement le faire. Ce dernier point est ce qui distingue une doc respectée d'une doc contournée.

## 4. Décisions transverses

- **Pas de blog au lancement.** Un blog vide nuit plus qu'il n'aide. À ouvrir quand il y a un chiffre à annoncer (« premier million de lignes migrées »).
- **Page « Limites » obligatoire et visible.** Un outil de sécurité qui ne dit pas ce qu'il rate perd sa crédibilité au premier faux négatif trouvé par un utilisateur.
- **La sortie réelle de l'outil sur l'accueil**, pas une capture retouchée : le terminal brut est l'argument le plus fort.
- **Un badge README** généré depuis le score de sûreté, comme vecteur de diffusion (trait repris de Docker Scout dans le brainstorming).
- **`editLink` activé** vers GitHub : les corrections de doc par la communauté sont le premier pas vers une contribution de code.

## 5. Ce qui reste à trancher

- Nom de domaine, dépendant de la disponibilité du nom `sqlguard` (Packagist et marque) — non vérifié à ce stade.
- Hébergement : GitHub Pages (plus simple, même organisation que le code) ou Cloudflare Pages (meilleures performances mondiales).
- Anglais d'abord ou français d'abord pour la rédaction initiale : l'audience OSS est anglophone, mais le mainteneur écrit plus vite en français.
