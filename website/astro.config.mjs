import { defineConfig } from 'astro/config';
import starlight from '@astrojs/starlight';

export default defineConfig({
  // Sortie entierement statique : deployable sans serveur (NFR-9, §11.3 du PRD).
  output: 'static',
  integrations: [
    starlight({
      title: 'sqlguard',
      // Recherche : Pagefind est integre a Starlight et s'execute cote client.
      // Aucun service tiers, aucune cle d'API — coherent avec un outil dont le
      // sujet est de reduire la surface d'exposition.
      defaultLocale: 'fr',
      locales: {
        fr: { label: 'Français', lang: 'fr' },
        en: { label: 'English', lang: 'en' },
      },
      editLink: {
        baseUrl: 'https://github.com/stephsalou/db_query/edit/master/website/',
      },
      sidebar: [
        { label: 'Démarrage', items: [{ autogenerate: { directory: 'demarrage' } }] },
        { label: 'Concepts', items: [{ autogenerate: { directory: 'concepts' } }] },
        // Pages GENEREES depuis rules/manifest.json — jamais editees a la main.
        { label: 'Règles', items: [{ autogenerate: { directory: 'rules' } }] },
      ],
    }),
  ],
});
