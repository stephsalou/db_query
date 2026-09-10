# Protocole d'entretien — porte de validation K-1

> **Figé le 2026-09-10, avant le premier entretien.** Toute modification postérieure au premier
> entretien doit être datée, justifiée, et signalée dans le compte rendu : un protocole qu'on
> ajuste en cours de route mesure l'enquêteur, pas le marché.
>
> Story 1.1 · exigences PRD §13.1, §5.1 · lève partiellement Q-8, Q-10, Q-11, HYP-8

## Pourquoi ce document existe avant les entretiens

L'auteur veut que le produit existe. C'est le biais principal, et il est structurel : les 8 jobs
du §5.1 proviennent de son propre raisonnement, sans aucun entretien. Ce protocole est écrit
d'avance pour que les réponses ne soient pas contaminées par cette envie.

La porte K-1 tombe à **T+6 semaines**. Sa décision par défaut en cas d'échec est **l'abandon du
produit**, pas l'ajustement du discours.

## 1. Segments et nombres visés

| Segment | Visé | Question de décision à trancher |
|---|---|---|
| **S1 — Leads techniques sur PHP legacy** | 2 | Existe-t-il une occasion réelle et datée où ils ont eu besoin de prouver l'absence d'injection ? Qu'ont-ils fait à la place ? |
| **S2 — Mainteneurs WordPress** | 2 | Le sink `wpdb` est-il la condition d'entrée, ou PDO suffit-il à démarrer ? |
| **S3 — Auditeurs / consultants en sécurité** | 2 | Un rapport autoportant réduit-il leur temps de mission, ou crée-t-il du travail de vérification supplémentaire ? Quelles limites d'analyse exigent-ils de voir ? |
| **S4 — Utilisateurs d'agents de codage** | 2 | Relisent-ils réellement le SQL généré ? La voie de vérification du SQL généré est-elle un besoin ou une projection ? |
| **S5 — Optionnels (RSSI, DPO)** | 0-4 | Uniquement si les 8 premiers font émerger un signal. Ne pas démarcher par défaut |

**Minimum pour ouvrir la porte : 8 entretiens**, dont les quatre segments obligatoires couverts.

## 2. Questions ouvertes du PRD à instruire

Chacune est rattachée à un segment. Une question ouverte non instruite reste ouverte : elle ne se
referme pas par déduction.

| Réf. | Segment | Question posée |
|---|---|---|
| **Q-8** | S2 | « Si un outil ne couvrait que PDO et pas `wpdb`, l'installeriez-vous quand même sur vos projets ? Sur lesquels, concrètement ? » |
| **Q-10** | S1 et S3 | « Un outil de ce type existe déjà et personne ne l'utilise. À votre avis, pourquoi ? » — posée **sans** nommer le projet ni suggérer de réponse. Contacter si possible l'auteur du dépôt à zéro étoile |
| **Q-11** | S1 | « Vos dépôts mélangent-ils PDO et `mysqli` ? Un scan qui n'en couvre qu'un seul vous paraîtrait-il utile, ou trompeur ? » |
| **HYP-8** | S1 | « Comment installez-vous un outil de développement sur ce projet ? Composer est-il disponible en pratique, ou faut-il un binaire autonome ? » |

## 3. Déroulé d'un entretien (45 minutes)

1. **Cadrage (2 min)** — « Je cherche à comprendre comment vous travaillez, pas à présenter un
   outil. Il n'y a pas de bonne réponse. » Aucune description du produit à ce stade.
2. **Découverte (25 min)** — le passé uniquement. Occasions réelles, datées, vécues.
3. **Instruction des questions ouvertes (10 min)** — tableau du §2 ci-dessus.
4. **Confrontation (5 min)** — **et seulement ici**, présenter la revendication de différenciation
   du PRD, puis demander ce qui, dedans, paraît faux ou sans intérêt.
5. **Ouverture (3 min)** — « Qui d'autre devrais-je interroger ? » et « Qu'ai-je oublié de
   demander ? »

## 4. Règle de codage des réponses

Un job du §5.1 passe de `hypothèse` à `validé par N entretiens` **si et seulement si** :

- **N ≥ 2** personnes de segments pertinents le valident, et
- chacune rapporte une **occasion réelle et datée** — un projet nommé, un moment situé dans le
  temps, une action effectivement entreprise.

**Explicitement exclu du décompte de validation :**

- toute réponse déclarative ou hypothétique : « oui, ce serait utile », « je pense que
  j'utiliserais ça », « c'est un vrai problème en général » ;
- tout accord obtenu après l'étape 4 (confrontation) — à ce stade la personne connaît la thèse et
  cherche à être agréable ;
- l'intérêt exprimé par une personne qui n'a pas de projet PHP legacy en cours ;
- toute réponse que l'enquêteur a dû reformuler pour qu'elle valide le job.

Une occasion datée qui **contredit** un job est enregistrée avec la même force qu'une occasion qui
le valide. Le décompte des infirmations est publié à côté de celui des validations.

## 5. Formulations proscrites

Aucune de ces formes ne doit être employée pendant les étapes 1 à 3 :

- **Présenter la revendication de différenciation du PRD avant la fin de l'étape 3.** C'est la
  proscription principale : elle transforme un entretien de découverte en demande d'approbation.
- « Ne trouvez-vous pas que… », « Seriez-vous d'accord pour dire que… », « N'est-ce pas
  frustrant de… » — questions qui portent leur réponse.
- Toute question au futur ou au conditionnel sur un comportement : « utiliseriez-vous », « seriez-
  vous prêt à », « paieriez-vous pour ». Remplacer par le passé : « qu'avez-vous fait la dernière
  fois que… ».
- Citer Psalm, Semgrep ou un concurrent **avant** que la personne les mentionne d'elle-même. Leur
  mention spontanée est une donnée ; leur mention par l'enquêteur la détruit.
- Annoncer un chiffre du PRD (70 % du web, 85 % de rappel) : ce sont des hypothèses, les énoncer
  les transforme en prémisses acceptées.
- Demander une estimation de budget ou de prix. Hors périmètre de K-1, et cela oriente tout le
  reste de l'entretien.

## 6. Traçabilité

Un compte rendu par entretien dans `docs/validation/entretiens/`, nommé
`AAAA-MM-JJ-segment-N.md`, contenant : segment, date, occasions datées rapportées, jobs validés,
jobs infirmés, questions ouvertes instruites, verbatims utiles. **Aucun nom ni donnée personnelle**
— un identifiant de segment et un numéro suffisent.

La synthèse et la décision K-1 vont dans `docs/validation/decision-k1.md` (story 1.5).
