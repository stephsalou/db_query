#!/usr/bin/env node
/**
 * Genere une page MDX par regle du manifeste, et refuse de laisser passer un
 * manifeste desynchronise.
 *
 * Deux modes :
 *   node scripts/build-rule-pages.mjs           genere
 *   node scripts/build-rule-pages.mjs --check    verifie, sortie non nulle si desynchronise
 *
 * Le mode --check est branche sur `prebuild` : une regle ajoutee au manifeste
 * sans regeneration fait echouer le build au lieu de produire un site
 * incomplet (critere d'acceptation de la story 5.2).
 */
import { readFileSync, writeFileSync, mkdirSync, readdirSync, rmSync, existsSync } from 'node:fs';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';
import { createHash } from 'node:crypto';

const here = dirname(fileURLToPath(import.meta.url));
const root = join(here, '..');
const manifestPath = join(root, '..', 'rules', 'manifest.json');
const outDir = join(root, 'src', 'content', 'docs', 'fr', 'rules');
const stampPath = join(outDir, '.generated-from');

const check = process.argv.includes('--check');

const manifestRaw = readFileSync(manifestPath, 'utf8');
const manifest = JSON.parse(manifestRaw);
const digest = createHash('sha256').update(manifestRaw).digest('hex');

// --- garde-fous sur le manifeste lui-meme ------------------------------------
const ids = manifest.rules.map((r) => r.id);
const dupes = ids.filter((id, i) => ids.indexOf(id) !== i);
if (dupes.length) {
  console.error(`manifeste invalide : identifiants dupliques -> ${[...new Set(dupes)].join(', ')}`);
  process.exit(2);
}
const required = ['id', 'level', 'title', 'summary', 'vulnerable', 'fixed', 'disable', 'legitimate'];
for (const r of manifest.rules) {
  const missing = required.filter((k) => r[k] === undefined || r[k] === '');
  if (missing.length) {
    console.error(`regle ${r.id ?? '(sans id)'} : champs manquants -> ${missing.join(', ')}`);
    process.exit(2);
  }
  if (!/^[a-z0-9]+(-[a-z0-9]+)*$/.test(r.id)) {
    console.error(`regle ${r.id} : identifiant non conforme (kebab-case attendu)`);
    process.exit(2);
  }
}

// --- mode verification -------------------------------------------------------
if (check) {
  const stamp = existsSync(stampPath) ? readFileSync(stampPath, 'utf8').trim() : null;
  if (stamp !== digest) {
    console.error(
      'Les pages de regles sont desynchronisees du manifeste.\n' +
        `  manifeste : ${digest.slice(0, 12)}\n` +
        `  pages     : ${stamp ? stamp.slice(0, 12) : '(aucune)'}\n` +
        'Lancez `npm run rules` puis committez les pages generees.'
    );
    process.exit(1);
  }
  const onDisk = readdirSync(outDir).filter((f) => f.endsWith('.mdx')).length;
  if (onDisk !== manifest.rules.length) {
    console.error(`Compte incoherent : ${onDisk} page(s) sur disque pour ${manifest.rules.length} regle(s).`);
    process.exit(1);
  }
  console.log(`OK : ${onDisk} page(s) de regle synchronisee(s) avec le manifeste.`);
  process.exit(0);
}

// --- generation --------------------------------------------------------------
if (existsSync(outDir)) rmSync(outDir, { recursive: true });
mkdirSync(outDir, { recursive: true });

const fence = (code, lang = 'php') => '```' + lang + '\n' + code + '\n```';
const esc = (s) => String(s).replace(/"/g, '\\"');

for (const r of manifest.rules) {
  const body = `---
title: "${esc(r.title)}"
description: "${esc(r.summary)}"
sidebar:
  label: "${esc(r.id)}"
  badge:
    text: "niveau ${r.level}"
---

{/* PAGE GENEREE — ne pas editer a la main. Source : rules/manifest.json */}

**Identifiant :** \`${r.id}\` · **Niveau :** ${r.level}

${r.summary}

## Code vulnérable

${fence(r.vulnerable)}

## Code corrigé

${fence(r.fixed)}

## Désactiver cette règle

${fence(r.disable, 'text')}

**Quand c'est légitime.** ${r.legitimate}
`;
  writeFileSync(join(outDir, `${r.id}.mdx`), body, 'utf8');
}
writeFileSync(stampPath, digest, 'utf8');
console.log(`${manifest.rules.length} page(s) de regle generee(s) dans src/content/docs/fr/rules/`);
