<template>
  <div class="space-y-8 animate-fade-in max-w-7xl mx-auto">
    <!-- En-tête -->
    <div class="flex flex-wrap items-center justify-between gap-4">
      <div>
        <h1 class="text-4xl font-black tracking-tight text-slate-900 dark:text-white">
          Gestion &amp; Rédaction du Blog
        </h1>
        <p class="mt-1 text-slate-500 dark:text-slate-400 font-medium text-lg">
          Rédigez, éditez et publiez des articles sur la vitrine marketing avec prévisualisation en direct.
        </p>
      </div>
      <div class="flex items-center gap-3">
        <button
          v-if="!isEditing"
          type="button"
          class="btn-primary flex items-center gap-2"
          @click="startNewArticle"
        >
          <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
          </svg>
          Nouvel Article
        </button>
        <button
          v-else
          type="button"
          class="btn-secondary"
          @click="cancelEdit"
        >
          Retour à la liste
        </button>
      </div>
    </div>

    <!-- Mode 1 : Liste des Articles -->
    <div v-if="!isEditing" class="space-y-6">
      <div class="glass-card p-6 flex flex-wrap items-center justify-between gap-4">
        <div class="flex items-center gap-3">
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Rechercher par titre, mot-clé ou catégorie..."
            class="form-input min-w-80"
          />
          <select v-model="filterCategory" class="form-select">
            <option value="">Toutes les catégories</option>
            <option value="RH">Ressources Humaines</option>
            <option value="Paie">Paie &amp; Fiscalité</option>
            <option value="Technologie">Technologie &amp; Pointage</option>
            <option value="Tendances">Tendances</option>
          </select>
        </div>
        <div class="text-sm font-semibold text-slate-500">
          {{ filteredArticles.length }} article(s) enregistré(s)
        </div>
      </div>

      <!-- Grille des articles -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <div
          v-for="article in filteredArticles"
          :key="article.slug"
          class="glass-card p-5 flex flex-col justify-between hover:shadow-xl transition-all border border-slate-200/80 dark:border-slate-800"
        >
          <div>
            <div class="flex items-center justify-between gap-2 mb-3">
              <span class="px-2.5 py-1 rounded-full text-xs font-bold uppercase tracking-wider bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300">
                {{ article.category }}
              </span>
              <span class="text-xs text-slate-400 font-medium">
                {{ article.readingTime }} min de lecture
              </span>
            </div>

            <h3 class="text-lg font-black text-slate-900 dark:text-white line-clamp-2 mb-2">
              {{ article.title }}
            </h3>
            <p class="text-sm text-slate-500 dark:text-slate-400 line-clamp-3 mb-4 leading-relaxed">
              {{ article.excerpt }}
            </p>
          </div>

          <div class="pt-4 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between">
            <div class="text-xs text-slate-400">
              Par <strong class="text-slate-700 dark:text-slate-300">{{ article.author.name }}</strong>
            </div>
            <div class="flex items-center gap-2">
              <button
                type="button"
                class="px-3 py-1.5 rounded-lg text-xs font-bold bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-emerald-500 hover:text-white transition-colors"
                @click="editArticle(article)"
              >
                Éditer
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Mode 2 : Éditeur & Prévisualisation Temps Réel (Double Volet) -->
    <div v-else class="space-y-6">
      <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
        <!-- Volet Gauche : Formulaire & Markdown (7 colonnes) -->
        <div class="lg:col-span-7 glass-card p-6 sm:p-8 space-y-6">
          <h2 class="text-xl font-black text-slate-900 dark:text-white border-b border-slate-200 dark:border-slate-800 pb-3">
            {{ isNew ? "Rédiger un Nouvel Article" : "Modifier l’Article" }}
          </h2>

          <div class="space-y-4">
            <div>
              <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 dark:text-slate-300 mb-1">
                Titre de l’article *
              </label>
              <input
                v-model="currentArticle.title"
                type="text"
                class="form-input w-full font-bold text-lg"
                placeholder="Ex: Guide Complet : Automatiser la paie multi-pays"
                @input="generateSlugIfNew"
              />
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 dark:text-slate-300 mb-1">
                  Slug URL *
                </label>
                <input
                  v-model="currentArticle.slug"
                  type="text"
                  class="form-input w-full font-mono text-sm"
                  placeholder="guide-automatiser-paie"
                />
              </div>

              <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 dark:text-slate-300 mb-1">
                  Catégorie
                </label>
                <input
                  v-model="currentArticle.category"
                  type="text"
                  class="form-input w-full text-sm"
                  placeholder="RH, Paie, Technologie..."
                />
              </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 dark:text-slate-300 mb-1">
                  Nom de l’Auteur
                </label>
                <input
                  v-model="currentArticle.author.name"
                  type="text"
                  class="form-input w-full text-sm"
                  placeholder="Jean Martin"
                />
              </div>
              <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 dark:text-slate-300 mb-1">
                  Temps de lecture estimé (min)
                </label>
                <input
                  v-model.number="currentArticle.readingTime"
                  type="number"
                  min="1"
                  max="60"
                  class="form-input w-full text-sm"
                />
              </div>
            </div>

            <div>
              <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 dark:text-slate-300 mb-1">
                Image de Couverture (URL ou chemin SVG)
              </label>
              <input
                v-model="currentArticle.image"
                type="text"
                class="form-input w-full font-mono text-sm"
                placeholder="/blog/startup-rh.svg"
              />
            </div>

            <div>
              <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 dark:text-slate-300 mb-1">
                Extrait / Chapeau (Excerpt) *
              </label>
              <textarea
                v-model="currentArticle.excerpt"
                rows="2"
                class="form-textarea w-full text-sm leading-relaxed"
                placeholder="Court résumé percutant pour le SEO et les cartes de la vitrine..."
              />
            </div>

            <!-- Barre d'outils Markdown -->
            <div>
              <div class="flex items-center justify-between mb-2">
                <label class="text-xs font-bold uppercase tracking-wider text-slate-600 dark:text-slate-300">
                  Corps de l’Article (Markdown Enrichi)
                </label>
                <div class="flex items-center gap-1.5 flex-wrap">
                  <button
                    type="button"
                    class="px-2 py-1 text-xs font-semibold rounded bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700"
                    @click="insertSnippet('## Nouveau Titre\n\n')"
                  >
                    + H2
                  </button>
                  <button
                    type="button"
                    class="px-2 py-1 text-xs font-semibold rounded bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700"
                    @click="insertSnippet('![Titre du visuel](/blog/startup-rh-workflow.svg)\n\n')"
                  >
                    + Image/Infographie
                  </button>
                  <button
                    type="button"
                    class="px-2 py-1 text-xs font-semibold rounded bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-300 hover:bg-emerald-200"
                    @click="insertSnippet('> [!TIP] **Astuce Pro :** Votre conseil stratégique ici.\n\n')"
                  >
                    + Astuce
                  </button>
                  <button
                    type="button"
                    class="px-2 py-1 text-xs font-semibold rounded bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-300 hover:bg-amber-200"
                    @click="insertSnippet('> [!WARNING] **Attention légale :** Votre mise en garde ici.\n\n')"
                  >
                    + Alerte
                  </button>
                </div>
              </div>
              <textarea
                id="blog-content-area"
                v-model="currentArticle.content"
                rows="14"
                class="form-textarea w-full font-mono text-sm leading-relaxed"
                placeholder="Rédigez en Markdown..."
              />
            </div>
          </div>

          <!-- Actions d'enregistrement -->
          <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-200 dark:border-slate-800">
            <button type="button" class="btn-secondary" @click="cancelEdit">
              Annuler
            </button>
            <button type="button" class="btn-primary" @click="saveArticle">
              Enregistrer &amp; Publier sur la Vitrine
            </button>
          </div>
        </div>

        <!-- Volet Droit : Prévisualisation Rendu Vitrine (5 colonnes) -->
        <div class="lg:col-span-5 rounded-3xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-6 sm:p-8 shadow-xl sticky top-8">
          <div class="flex items-center justify-between pb-4 mb-4 border-b border-slate-100 dark:border-slate-800">
            <span class="text-xs font-bold uppercase tracking-widest text-emerald-600 dark:text-emerald-400">
              Aperçu en Direct (Rendu Vitrine)
            </span>
            <span class="text-xs text-slate-400 font-medium">
              Synchronisé
            </span>
          </div>

          <!-- Simulation Article Vitrine -->
          <div class="space-y-4 max-h-[750px] overflow-y-auto pr-2">
            <div class="inline-flex px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 uppercase tracking-wider">
              {{ currentArticle.category || 'Catégorie' }}
            </div>

            <h1 class="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white leading-tight">
              {{ currentArticle.title || "Titre de l’article" }}
            </h1>

            <div class="flex items-center gap-3 text-xs text-slate-400">
              <span>Par {{ currentArticle.author.name || 'Auteur' }}</span>
              <span>•</span>
              <span>{{ currentArticle.readingTime || 5 }} min de lecture</span>
            </div>

            <div v-if="currentArticle.image" class="rounded-xl overflow-hidden border border-slate-100 dark:border-slate-800 bg-slate-50 dark:bg-slate-800/50 p-2">
              <img :src="currentArticle.image" :alt="currentArticle.title" class="w-full h-40 object-cover rounded-lg" />
            </div>

            <p class="text-sm italic text-slate-600 dark:text-slate-300 font-medium leading-relaxed bg-slate-50 dark:bg-slate-800/40 p-3 rounded-xl border border-slate-200/50 dark:border-slate-800">
              {{ currentArticle.excerpt || "Extrait de l’article..." }}
            </p>

            <div class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed space-y-2 whitespace-pre-wrap font-sans">
              {{ currentArticle.content }}
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed } from 'vue'

const searchQuery = ref('')
const filterCategory = ref('')
const isEditing = ref(false)
const isNew = ref(false)

const articles = ref([
  {
    slug: 'guide-complet-gestion-rh-startup',
    title: 'Guide Complet: Gestion RH pour Startups',
    excerpt: 'Découvrez comment mettre en place une gestion RH efficace dès le départ dans votre startup.',
    content: `## Introduction\n\nLa gestion des ressources humaines est cruciale pour le succès d'une startup.\n\n![Le Cycle de Vie Collaborateur](/blog/startup-rh-workflow.svg "Infographie RH : Les 5 étapes du cycle de vie")\n\n> [!TIP] **Conseil de recruteur :** Ne tardez pas à digitaliser votre registre du personnel.`,
    image: '/blog/startup-rh.svg',
    author: { name: 'Ahmed Benali', avatar: '/avatars/ahmed.svg' },
    category: 'RH',
    readingTime: 8,
    tags: ['startup', 'RH', 'recrutement'],
  },
  {
    slug: 'automatiser-paie-excel-vers-logiciel',
    title: 'Passer de Excel à un Logiciel de Paie: Guide Pratique',
    excerpt: 'Comment migrer votre gestion de paie d\'Excel vers un logiciel automatisé sans stress.',
    content: `## Pourquoi Passer de Excel?\n\nExcel est un outil puissant, mais il n'est pas conçu pour la paie.\n\n![Comparatif Tableur vs Logiciel](/blog/paie-automation-comparison.svg "Étude comparative : Coûts et risques")\n\n> [!WARNING] **Attention aux redressements :** Les erreurs sur tableur coûtent en moyenne 3 500 € par contrôle.`,
    image: '/blog/paie-excel.svg',
    author: { name: 'Jean Martin', avatar: '/avatars/jean.svg' },
    category: 'Paie',
    readingTime: 10,
    tags: ['paie', 'excel', 'automatisation'],
  },
  {
    slug: 'pointage-biometrique-avantages',
    title: 'Pointage Biométrique: Avantages et Implémentation',
    excerpt: 'Découvrez comment le pointage biométrique peut améliorer votre gestion RH.',
    content: `## Avantages du pointage moderne\n\n![Chaîne de Confiance Biométrique](/blog/biometrie-securite-schema.svg "Du terminal physique au bulletin de paie")\n\n> [!NOTE] **Garantie RGPD :** Chiffrement des gabarits sous forme de vecteurs mathématiques.`,
    image: '/blog/biometrique.svg',
    author: { name: 'Ahmed Benali', avatar: '/avatars/ahmed.svg' },
    category: 'Technologie',
    readingTime: 7,
    tags: ['biométrie', 'pointage', 'sécurité'],
  },
])

const currentArticle = reactive({
  slug: '',
  title: '',
  excerpt: '',
  content: '',
  image: '/blog/startup-rh.svg',
  author: { name: 'Équipe Leopardo', avatar: '/avatars/ahmed.svg' },
  category: 'RH',
  readingTime: 5,
  tags: [],
})

const filteredArticles = computed(() => {
  return articles.value.filter((a) => {
    const matchQuery =
      !searchQuery.value ||
      a.title.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
      a.excerpt.toLowerCase().includes(searchQuery.value.toLowerCase())
    const matchCat = !filterCategory.value || a.category === filterCategory.value
    return matchQuery && matchCat
  })
})

function startNewArticle() {
  isNew.value = true
  isEditing.value = true
  currentArticle.slug = ''
  currentArticle.title = ''
  currentArticle.excerpt = ''
  currentArticle.content = '## Introduction\n\nRédigez votre introduction ici...\n\n'
  currentArticle.image = '/blog/startup-rh.svg'
  currentArticle.author = { name: 'Super Admin', avatar: '/avatars/ahmed.svg' }
  currentArticle.category = 'RH'
  currentArticle.readingTime = 5
  currentArticle.tags = []
}

function editArticle(article) {
  isNew.value = false
  isEditing.value = true
  Object.assign(currentArticle, JSON.parse(JSON.stringify(article)))
}

function cancelEdit() {
  isEditing.value = false
}

function generateSlugIfNew() {
  if (isNew.value && currentArticle.title) {
    currentArticle.slug = currentArticle.title
      .toLowerCase()
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '')
      .replace(/[^a-z0-9]+/g, '-')
      .replace(/(^-|-$)/g, '')
  }
}

function insertSnippet(text) {
  currentArticle.content += text
}

function saveArticle() {
  if (!currentArticle.title || !currentArticle.slug) {
    alert('Veuillez renseigner un titre et un slug valides.')
    return
  }

  const existingIdx = articles.value.findIndex((a) => a.slug === currentArticle.slug)
  if (existingIdx >= 0) {
    articles.value[existingIdx] = JSON.parse(JSON.stringify(currentArticle))
  } else {
    articles.value.unshift(JSON.parse(JSON.stringify(currentArticle)))
  }

  isEditing.value = false
  alert('Article enregistré avec succès ! Il est prêt pour la vitrine.')
}
</script>
