<template>
  <div class="fixed inset-0 z-50 overflow-y-auto">
    <div class="flex min-h-screen items-end justify-center px-4 pt-4 pb-20 text-center sm:block sm:p-0">
      <div
        class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"
        @click="$emit('close')"
      ></div>

      <div class="inline-block transform overflow-hidden rounded-lg bg-white text-left align-bottom shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-2xl sm:align-middle">
        <div class="border-b border-gray-200 px-6 py-4">
          <div class="flex items-center justify-between">
            <div>
              <h3 class="text-lg font-semibold text-gray-900">
                {{ applicant?.first_name }} {{ applicant?.last_name }}
              </h3>
              <p class="text-sm text-gray-500">{{ applicant?.email }}</p>
            </div>
            <button
              class="rounded-md bg-white text-gray-400 hover:text-gray-500"
              @click="$emit('close')"
            >
              <XMarkIcon class="h-6 w-6" />
            </button>
          </div>
        </div>

        <div class="px-6 py-6">
          <div v-if="loading" class="py-8 text-center text-sm text-gray-500">
            Chargement...
          </div>
          <div v-else-if="error" class="py-8 text-center text-sm text-red-600">
            {{ error }}
          </div>
          <div v-else class="space-y-6">
            <div class="bg-gray-50 rounded-lg p-4">
              <h4 class="text-sm font-medium text-gray-900 mb-3">Informations candidature</h4>
              <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                  <dt class="text-xs font-medium text-gray-500">Poste</dt>
                  <dd class="mt-1 text-sm text-gray-900">{{ detail?.job_title || detail?.job_posting?.title || '\u2014' }}</dd>
                </div>
                <div>
                  <dt class="text-xs font-medium text-gray-500">Telephone</dt>
                  <dd class="mt-1 text-sm text-gray-900">{{ detail?.phone || '\u2014' }}</dd>
                </div>
                <div>
                  <dt class="text-xs font-medium text-gray-500">Source</dt>
                  <dd class="mt-1 text-sm text-gray-900">{{ detail?.source || '\u2014' }}</dd>
                </div>
                <div>
                  <dt class="text-xs font-medium text-gray-500">Note</dt>
                  <dd class="mt-1 text-sm text-gray-900">{{ detail?.rating ?? '\u2014' }}</dd>
                </div>
                <div>
                  <dt class="text-xs font-medium text-gray-500">Etape</dt>
                  <dd class="mt-1">
                    <StatusBadge :status="detail?.status" :map="stageStatusMap" />
                  </dd>
                </div>
                <div>
                  <dt class="text-xs font-medium text-gray-500">Candidature deposee le</dt>
                  <dd class="mt-1 text-sm text-gray-900">{{ formatDate(detail?.applied_at || detail?.created_at) }}</dd>
                </div>
              </dl>
            </div>

            <div class="bg-gray-50 rounded-lg p-4">
              <h4 class="text-sm font-medium text-gray-900 mb-3">CV / Lettre de motivation</h4>
              <a
                v-if="resumeUrl"
                :href="resumeUrl"
                target="_blank"
                rel="noopener"
                class="inline-flex items-center text-sm font-medium text-indigo-600 hover:text-indigo-800"
              >
                <DocumentTextIcon class="h-4 w-4 mr-1" />
                Voir le CV
              </a>
              <p v-else class="text-sm text-gray-500">Aucun CV joint.</p>
              <p v-if="detail?.cover_letter" class="mt-3 text-sm text-gray-700 whitespace-pre-line">{{ detail.cover_letter }}</p>
            </div>

            <div class="bg-gray-50 rounded-lg p-4">
              <h4 class="text-sm font-medium text-gray-900 mb-3">Notes</h4>
              <textarea
                v-model="notesDraft"
                rows="3"
                class="w-full rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                placeholder="Ajouter une note sur ce candidat..."
              />
              <div class="mt-2 flex justify-end">
                <button
                  class="rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-60"
                  :disabled="savingNotes"
                  @click="saveNotes"
                >
                  {{ savingNotes ? 'Enregistrement...' : 'Enregistrer' }}
                </button>
              </div>
            </div>

            <div v-if="detail?.interviews?.length" class="bg-gray-50 rounded-lg p-4">
              <h4 class="text-sm font-medium text-gray-900 mb-3">Entretiens</h4>
              <ul class="space-y-2">
                <li
                  v-for="interview in detail.interviews"
                  :key="interview.id"
                  class="flex items-center justify-between text-sm"
                >
                  <span class="text-gray-700">{{ interview.type }} \u2014 {{ formatDate(interview.scheduled_at) }}</span>
                  <StatusBadge :status="interview.status" :map="interviewStatusMap" />
                </li>
              </ul>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { XMarkIcon, DocumentTextIcon } from '@heroicons/vue/24/outline'
import api from '@/services/api'
import StatusBadge from '@/components/common/StatusBadge.vue'

const props = defineProps({
  applicantId: {
    type: [Number, String],
    required: true,
  },
})

const emit = defineEmits(['close', 'updated'])

const loading = ref(true)
const error = ref('')
const detail = ref(null)
const notesDraft = ref('')
const savingNotes = ref(false)

const applicant = computed(() => detail.value)

const resumeUrl = computed(() => detail.value?.resume_url || detail.value?.resume_path || '')

const stageStatusMap = {
  new: { label: 'Candidature', color: 'blue' },
  screening: { label: 'Pre-selection', color: 'yellow' },
  interview: { label: 'Entretien', color: 'purple' },
  offer: { label: 'Offre', color: 'indigo' },
  hired: { label: 'Embauche', color: 'green' },
  rejected: { label: 'Refuse', color: 'red' },
  withdrawn: { label: 'Retire', color: 'gray' },
}

const interviewStatusMap = {
  scheduled: { label: 'Planifie', color: 'blue' },
  completed: { label: 'Termine', color: 'green' },
  cancelled: { label: 'Annule', color: 'gray' },
}

function formatDate(date) {
  if (!date) return '\u2014'
  return new Date(date).toLocaleDateString('fr-FR', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
  })
}

async function fetchDetail() {
  loading.value = true
  error.value = ''
  try {
    const res = await api.get(`/v1/recruitment/applicants/${props.applicantId}`)
    detail.value = res.data?.data || res.data
    notesDraft.value = detail.value?.notes || ''
  } catch {
    error.value = 'Impossible de charger les details du candidat.'
  } finally {
    loading.value = false
  }
}

async function saveNotes() {
  savingNotes.value = true
  try {
    await api.put(`/v1/recruitment/applicants/${props.applicantId}`, { notes: notesDraft.value })
    emit('updated')
  } catch {
    error.value = 'Impossible d\u2019enregistrer la note.'
  } finally {
    savingNotes.value = false
  }
}

onMounted(fetchDetail)
</script>
