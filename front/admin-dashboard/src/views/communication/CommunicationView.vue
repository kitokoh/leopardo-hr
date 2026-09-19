<template>
  <div class="space-y-6 animate-fade-in">
    <!-- En-tête -->
    <div>
      <h1 class="text-4xl font-black tracking-tight text-slate-900 dark:text-white">
        {{ t('communicationApp.moduleTitle') }}
      </h1>
      <p class="mt-1 text-slate-500 dark:text-slate-400 font-medium text-lg">
        {{ t('communicationApp.moduleSubtitle') }}
      </p>
    </div>

    <!-- Onglets -->
    <div class="flex flex-wrap gap-2" role="tablist" :aria-label="t('communicationApp.moduleTitle')">
      <button
        v-for="tab in tabs"
        :key="tab.id"
        type="button"
        role="tab"
        :aria-selected="activeTab === tab.id"
        :class="[
          'rounded-full border px-4 py-2 text-sm font-semibold transition-colors',
          activeTab === tab.id
            ? 'border-indigo-500 bg-indigo-50 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300'
            : 'border-slate-200 text-slate-600 hover:border-indigo-300 dark:border-slate-700 dark:text-slate-300'
        ]"
        @click="switchTab(tab.id)"
      >
        {{ tab.label }}
      </button>
    </div>

    <!-- États globaux -->
    <div v-if="loading" class="flex items-center justify-center gap-3 py-16 text-slate-400">
      <div class="h-5 w-5 animate-spin rounded-full border-2 border-indigo-500 border-t-transparent" />
      <span class="text-sm">{{ t('communicationApp.common.loading') }}</span>
    </div>

    <div
      v-else-if="forbidden"
      class="flex items-center justify-center gap-3 rounded-xl border border-amber-200 bg-amber-50 py-14 text-amber-700 dark:border-amber-700 dark:bg-amber-900/20 dark:text-amber-300"
    >
      <ExclamationTriangleIcon class="h-6 w-6" />
      <p class="text-sm font-medium">{{ t('communicationApp.common.featureLocked') }}</p>
    </div>

    <template v-else>
      <!-- ═══ Onglet Boîte connectée ═══ -->
      <div v-if="activeTab === 'inbox'" class="space-y-6">
        <section class="glass-card rounded-xl p-6 shadow">
          <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-lg font-bold text-slate-900 dark:text-white">
              {{ t('communicationApp.mailbox.title') }}
            </h2>
            <span
              v-if="moduleStatus"
              class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:bg-slate-800 dark:text-slate-300"
            >
              {{ t('communicationApp.common.stage', { stage: moduleStatus.stage }) }}
            </span>
          </div>
          <p class="mb-5 text-sm text-slate-500 dark:text-slate-400">
            {{ t('communicationApp.mailbox.subtitle') }}
          </p>

          <div
            v-if="integrations.length === 0"
            class="flex flex-col items-center gap-2 rounded-xl border border-dashed border-slate-200 py-10 text-slate-400 dark:border-slate-700"
          >
            <InboxIcon class="h-8 w-8" />
            <p class="text-sm font-medium">{{ t('communicationApp.mailbox.empty') }}</p>
            <p class="text-xs">{{ t('communicationApp.mailbox.emptyHint') }}</p>
          </div>

          <ul v-else class="space-y-3">
            <li
              v-for="integration in integrations"
              :key="integration.id"
              class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-100 p-4 dark:border-slate-700"
            >
              <div>
                <p class="font-semibold text-slate-900 dark:text-white">
                  {{ integration.email || integration.provider }}
                </p>
                <p class="text-xs text-slate-500">
                  {{ t('communicationApp.mailbox.connectedAt') }} {{ formatDate(integration.connected_at) }}
                </p>
              </div>
              <div class="flex flex-wrap items-center gap-2">
                <span
                  :class="[
                    'rounded-full px-3 py-1 text-xs font-semibold',
                    integration.status === 'active'
                      ? 'bg-emerald-100 text-emerald-700'
                      : integration.status === 'revoked'
                        ? 'bg-slate-100 text-slate-500'
                        : 'bg-red-100 text-red-700'
                  ]"
                >
                  {{ t(`communicationApp.mailbox.status.${integration.status}`) }}
                </span>
                <span class="rounded-full bg-indigo-50 px-3 py-1 text-xs font-semibold text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300">
                  {{ hasSendScope(integration) ? t('communicationApp.mailbox.scopeSend') : t('communicationApp.mailbox.readOnly') }}
                </span>
                <button
                  v-if="integration.status === 'active'"
                  type="button"
                  :disabled="revoking === integration.id"
                  class="rounded-full border border-red-200 px-3 py-1 text-xs font-semibold text-red-600 hover:bg-red-50 disabled:opacity-50"
                  @click="revokeIntegration(integration.id)"
                >
                  {{ t('communicationApp.mailbox.revoke') }}
                </button>
              </div>
            </li>
          </ul>

          <div class="mt-5 flex flex-wrap items-center gap-4 rounded-xl border border-slate-100 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-800/50">
            <label class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
              <input
                v-model="withSend"
                type="checkbox"
                class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
              />
              {{ t('communicationApp.mailbox.connectWithSend') }}
            </label>
            <button
              type="button"
              :disabled="connecting"
              class="rounded-full bg-indigo-600 px-5 py-2.5 text-sm font-bold text-white shadow hover:bg-indigo-700 disabled:opacity-50"
              @click="connectGoogle"
            >
              {{ connecting ? t('communicationApp.mailbox.connecting') : t('communicationApp.mailbox.connect') }}
            </button>
            <p class="w-full text-xs text-slate-400">
              {{ t('communicationApp.mailbox.connectHint') }} {{ t('communicationApp.mailbox.withSendHint') }}
            </p>
          </div>
        </section>

        <!-- Propositions de contacts (R3) -->
        <section class="glass-card rounded-xl p-6 shadow">
          <h2 class="mb-1 text-lg font-bold text-slate-900 dark:text-white">
            {{ t('communicationApp.proposals.title') }}
          </h2>
          <p class="mb-4 text-sm text-slate-500 dark:text-slate-400">
            {{ t('communicationApp.proposals.subtitle') }}
          </p>
          <p
            v-if="proposals.length === 0"
            class="rounded-xl border border-dashed border-slate-200 py-6 text-center text-sm text-slate-400 dark:border-slate-700"
          >
            {{ t('communicationApp.proposals.empty') }}
          </p>
          <ul v-else class="grid gap-3 sm:grid-cols-2">
            <li
              v-for="proposal in proposals"
              :key="proposal.id"
              class="rounded-xl border border-slate-100 p-4 dark:border-slate-700"
            >
              <p class="font-semibold text-slate-900 dark:text-white">
                {{ proposal.suggested_name || proposal.email }}
              </p>
              <p class="text-xs text-slate-500">{{ proposal.email }}</p>
              <p class="mt-1 text-xs text-slate-400">
                {{ t('communicationApp.proposals.seenIn', { count: proposal.message_count }) }}
              </p>
              <div class="mt-3 flex gap-2">
                <button
                  type="button"
                  :disabled="proposalBusy === proposal.id"
                  class="rounded-full bg-emerald-600 px-4 py-1.5 text-xs font-bold text-white hover:bg-emerald-700 disabled:opacity-50"
                  @click="decideProposal(proposal.id, 'accept')"
                >
                  {{ t('communicationApp.proposals.accept') }}
                </button>
                <button
                  type="button"
                  :disabled="proposalBusy === proposal.id"
                  class="rounded-full border border-slate-200 px-4 py-1.5 text-xs font-semibold text-slate-600 hover:bg-slate-50 disabled:opacity-50 dark:border-slate-600 dark:text-slate-300"
                  @click="decideProposal(proposal.id, 'dismiss')"
                >
                  {{ t('communicationApp.proposals.dismiss') }}
                </button>
              </div>
            </li>
          </ul>
        </section>

        <!-- Fils synchronisés (R2/R3) -->
        <section class="glass-card rounded-xl p-6 shadow">
          <div class="mb-1 flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-lg font-bold text-slate-900 dark:text-white">
              {{ t('communicationApp.threads.title') }}
            </h2>
            <select
              v-if="activeIntegrations.length > 1"
              v-model="selectedIntegration"
              :aria-label="t('communicationApp.policies.mailbox')"
              class="rounded-lg border-slate-200 text-sm dark:bg-slate-800 dark:text-white"
            >
              <option v-for="integration in activeIntegrations" :key="integration.id" :value="integration.id">
                {{ integration.email || integration.provider }}
              </option>
            </select>
          </div>
          <p class="mb-4 text-sm text-slate-500 dark:text-slate-400">
            {{ t('communicationApp.threads.subtitle') }}
          </p>

          <div
            v-if="threads.length === 0"
            class="flex flex-col items-center gap-2 rounded-xl border border-dashed border-slate-200 py-10 text-slate-400 dark:border-slate-700"
          >
            <InboxIcon class="h-8 w-8" />
            <p class="text-sm font-medium">{{ t('communicationApp.threads.empty') }}</p>
            <p class="text-xs">{{ t('communicationApp.threads.emptyHint') }}</p>
          </div>

          <ul v-else class="space-y-3">
            <li
              v-for="thread in threads"
              :key="thread.id"
              class="rounded-xl border border-slate-100 dark:border-slate-700"
            >
              <button
                type="button"
                class="flex w-full flex-wrap items-center justify-between gap-2 p-4 text-start"
                @click="toggleThread(thread.id)"
              >
                <span>
                  <span class="block font-semibold text-slate-900 dark:text-white">
                    {{ thread.subject || t('communicationApp.threads.noSubject') }}
                  </span>
                  <span class="block text-xs text-slate-500">{{ thread.snippet }}</span>
                </span>
                <span class="flex items-center gap-3 text-xs text-slate-400">
                  <span>{{ t('communicationApp.threads.messagesCount', { count: thread.message_count }) }}</span>
                  <span>{{ formatDate(thread.last_message_at) }}</span>
                  <ChevronUpIcon v-if="openThread === thread.id" class="h-4 w-4" />
                  <ChevronDownIcon v-else class="h-4 w-4" />
                </span>
              </button>

              <div v-if="openThread === thread.id" class="space-y-3 border-t border-slate-100 p-4 dark:border-slate-700">
                <p v-if="threadLoading === thread.id" class="text-sm text-slate-400">
                  {{ t('communicationApp.threads.loadingMessages') }}
                </p>
                <article
                  v-for="message in threadMessages[thread.id] || []"
                  :key="message.id"
                  class="rounded-lg border border-slate-100 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-800/60"
                >
                  <div class="flex flex-wrap items-center justify-between gap-2 text-xs text-slate-500">
                    <span><strong>{{ t('communicationApp.messages.from') }}</strong> {{ message.from_email || '—' }}</span>
                    <span>{{ formatDate(message.sent_at) }}</span>
                  </div>
                  <div class="mt-2 flex flex-wrap items-center gap-2">
                    <span class="rounded-full bg-indigo-100 px-2.5 py-0.5 text-xs font-semibold text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300">
                      {{ categoryLabelFor(message.ai_category) }}
                    </span>
                    <span
                      v-if="message.ai_sentiment"
                      class="rounded-full bg-slate-200 px-2.5 py-0.5 text-xs font-medium text-slate-600 dark:bg-slate-700 dark:text-slate-300"
                    >
                      {{ t(`communicationApp.messages.sentiment.${message.ai_sentiment}`) }}
                    </span>
                    <span
                      v-if="message.ai_action"
                      class="rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-medium text-emerald-700"
                    >
                      {{ t(`communicationApp.messages.action.${message.ai_action}`) }}
                    </span>
                    <span v-if="typeof message.ai_confidence === 'number'" class="text-xs text-slate-400">
                      {{ t('communicationApp.messages.confidence', { value: message.ai_confidence }) }}
                    </span>
                    <button
                      type="button"
                      class="ms-auto rounded-full border border-slate-200 px-3 py-0.5 text-xs font-semibold text-slate-500 hover:bg-white dark:border-slate-600"
                      @click="reclassify(message.id)"
                    >
                      {{ t('communicationApp.messages.reclassify') }}
                    </button>
                  </div>
                  <p v-if="message.body" class="mt-3 whitespace-pre-wrap text-sm leading-relaxed text-slate-700 dark:text-slate-200">
                    {{ message.body }}
                  </p>
                  <p v-else class="mt-3 text-sm italic text-slate-400">{{ message.snippet }}</p>
                </article>
              </div>
            </li>
          </ul>
        </section>
      </div>

      <!-- ═══ Onglet File de confirmations ═══ -->
      <div v-else-if="activeTab === 'replies'" class="space-y-6">
        <section class="glass-card rounded-xl p-6 shadow">
          <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
            <label class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
              {{ t('communicationApp.replies.filterLabel') }}
              <select
                v-model="replyStatusFilter"
                class="rounded-lg border-slate-200 text-sm dark:bg-slate-800 dark:text-white"
                @change="loadReplies"
              >
                <option v-for="value in replyStatuses" :key="value" :value="value">
                  {{ t(`communicationApp.replies.status.${value}`) }}
                </option>
              </select>
            </label>
            <button
              type="button"
              class="rounded-full border border-slate-200 px-4 py-1.5 text-xs font-semibold text-slate-600 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-300"
              @click="loadReplies"
            >
              {{ t('communicationApp.common.refresh') }}
            </button>
          </div>

          <div
            v-if="pendingReplies.length === 0"
            class="flex flex-col items-center gap-3 py-16 text-slate-400"
          >
            <InboxIcon class="h-10 w-10" />
            <p class="text-sm font-medium">{{ t('communicationApp.replies.empty') }}</p>
            <p class="text-xs">{{ t('communicationApp.replies.emptyHint') }}</p>
          </div>

          <ul v-else class="space-y-4">
            <li
              v-for="reply in pendingReplies"
              :key="reply.id"
              class="rounded-xl border border-slate-100 p-5 dark:border-slate-700"
            >
              <div class="flex flex-wrap items-center justify-between gap-2">
                <div class="flex flex-wrap items-center gap-2">
                  <span :class="['rounded-full px-3 py-1 text-xs font-semibold', replyStatusBadge(reply.status)]">
                    {{ t(`communicationApp.replies.status.${reply.status}`) }}
                  </span>
                  <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                    {{ t('communicationApp.replies.modeLabel') }} : {{ t(`communicationApp.replies.mode.${reply.mode}`) }}
                  </span>
                  <span v-if="typeof reply.ai_confidence === 'number'" class="text-xs text-slate-400">
                    {{ t('communicationApp.replies.confidence', { value: reply.ai_confidence }) }}
                  </span>
                </div>
                <span class="text-xs text-slate-400">{{ formatDate(reply.created_at) }}</span>
              </div>

              <p class="mt-3 text-sm text-slate-600 dark:text-slate-300">
                <strong>{{ t('communicationApp.replies.to') }} :</strong> {{ reply.to_email }}
              </p>
              <p v-if="reply.skip_reason" class="text-sm text-slate-600 dark:text-slate-300">
                <strong>{{ t('communicationApp.replies.skipReason') }} :</strong> {{ reply.skip_reason }}
              </p>

              <div v-if="editingReply === reply.id" class="mt-4 space-y-3">
                <label class="block text-sm">
                  <span class="mb-1 block font-semibold text-slate-600 dark:text-slate-300">
                    {{ t('communicationApp.replies.subject') }}
                  </span>
                  <input
                    v-model="editSubject"
                    type="text"
                    maxlength="255"
                    class="w-full rounded-lg border-slate-200 text-sm dark:bg-slate-800 dark:text-white"
                  />
                </label>
                <label class="block text-sm">
                  <span class="mb-1 block font-semibold text-slate-600 dark:text-slate-300">
                    {{ t('communicationApp.replies.body') }}
                  </span>
                  <textarea
                    v-model="editBody"
                    rows="6"
                    maxlength="10000"
                    class="w-full rounded-lg border-slate-200 text-sm dark:bg-slate-800 dark:text-white"
                  />
                </label>
                <div class="flex gap-2">
                  <button
                    type="button"
                    :disabled="replyBusy === reply.id"
                    class="rounded-full bg-indigo-600 px-4 py-1.5 text-xs font-bold text-white hover:bg-indigo-700 disabled:opacity-50"
                    @click="saveReplyEdit(reply.id)"
                  >
                    {{ t('communicationApp.common.save') }}
                  </button>
                  <button
                    type="button"
                    class="rounded-full border border-slate-200 px-4 py-1.5 text-xs font-semibold text-slate-600 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-300"
                    @click="editingReply = null"
                  >
                    {{ t('communicationApp.common.cancel') }}
                  </button>
                </div>
              </div>

              <div v-else class="mt-4 rounded-lg border border-slate-100 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-800/60">
                <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">
                  {{ reply.subject || t('communicationApp.threads.noSubject') }}
                </p>
                <p class="mt-2 whitespace-pre-wrap text-sm leading-relaxed text-slate-600 dark:text-slate-300">
                  {{ reply.body }}
                </p>
              </div>

              <div v-if="reply.status === 'pending' && editingReply !== reply.id" class="mt-4 flex flex-wrap gap-2">
                <button
                  type="button"
                  :disabled="replyBusy === reply.id"
                  class="rounded-full bg-emerald-600 px-5 py-2 text-xs font-bold text-white hover:bg-emerald-700 disabled:opacity-50"
                  @click="decideReply(reply.id, 'approve')"
                >
                  {{ t('communicationApp.replies.approve') }}
                </button>
                <button
                  type="button"
                  :disabled="replyBusy === reply.id"
                  class="rounded-full border border-indigo-200 px-5 py-2 text-xs font-semibold text-indigo-700 hover:bg-indigo-50 disabled:opacity-50"
                  @click="startReplyEdit(reply)"
                >
                  {{ t('communicationApp.replies.edit') }}
                </button>
                <button
                  type="button"
                  :disabled="replyBusy === reply.id"
                  class="rounded-full border border-red-200 px-5 py-2 text-xs font-semibold text-red-600 hover:bg-red-50 disabled:opacity-50"
                  @click="decideReply(reply.id, 'reject')"
                >
                  {{ t('communicationApp.replies.reject') }}
                </button>
              </div>
            </li>
          </ul>
        </section>
      </div>

      <!-- ═══ Onglet Réglages ═══ -->
      <div v-else class="space-y-6">
        <div
          v-if="activeIntegrations.length === 0"
          class="flex flex-col items-center gap-2 rounded-xl border border-dashed border-slate-200 py-12 text-slate-400 dark:border-slate-700"
        >
          <InboxIcon class="h-8 w-8" />
          <p class="text-sm font-medium">{{ t('communicationApp.mailbox.empty') }}</p>
          <p class="text-xs">{{ t('communicationApp.mailbox.emptyHint') }}</p>
        </div>

        <template v-else>
          <label
            v-if="activeIntegrations.length > 1"
            class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300"
          >
            {{ t('communicationApp.policies.mailbox') }}
            <select
              v-model="selectedIntegration"
              class="rounded-lg border-slate-200 text-sm dark:bg-slate-800 dark:text-white"
            >
              <option v-for="integration in activeIntegrations" :key="integration.id" :value="integration.id">
                {{ integration.email || integration.provider }}
              </option>
            </select>
          </label>

          <!-- Politiques de réponse (R5) -->
          <section class="glass-card rounded-xl p-6 shadow">
            <h2 class="mb-1 text-lg font-bold text-slate-900 dark:text-white">
              {{ t('communicationApp.policies.title') }}
            </h2>
            <p class="mb-4 text-sm text-slate-500 dark:text-slate-400">
              {{ t('communicationApp.policies.subtitle') }}
            </p>
            <ul class="space-y-3">
              <li
                v-for="category in activeCategories"
                :key="category.id"
                class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-100 p-4 dark:border-slate-700"
              >
                <div>
                  <p class="font-semibold text-slate-900 dark:text-white">{{ category.label }}</p>
                  <p v-if="policyFor(category.key)?.auto_blocked" class="mt-1 text-xs font-medium text-amber-600">
                    {{ t('communicationApp.policies.autoBlocked') }}
                  </p>
                </div>
                <select
                  :value="policyFor(category.key)?.policy || 'off'"
                  :disabled="policyBusy === category.key"
                  :aria-label="`${t('communicationApp.policies.level')} — ${category.label}`"
                  class="rounded-lg border-slate-200 text-sm dark:bg-slate-800 dark:text-white"
                  @change="savePolicy(category.key, $event.target.value)"
                >
                  <option value="off">{{ t('communicationApp.policies.off') }}</option>
                  <option value="draft">{{ t('communicationApp.policies.draft') }}</option>
                  <option value="confirm">{{ t('communicationApp.policies.confirmPolicy') }}</option>
                  <option value="auto" :disabled="policyFor(category.key)?.auto_blocked">
                    {{ t('communicationApp.policies.auto') }}
                  </option>
                </select>
              </li>
            </ul>
          </section>

          <!-- Règles de relance (R4) -->
          <section class="glass-card rounded-xl p-6 shadow">
            <h2 class="mb-1 text-lg font-bold text-slate-900 dark:text-white">
              {{ t('communicationApp.followUps.title') }}
            </h2>
            <p class="mb-4 text-sm text-slate-500 dark:text-slate-400">
              {{ t('communicationApp.followUps.subtitle') }}
            </p>

            <p
              v-if="selectedRules.length === 0"
              class="mb-4 rounded-xl border border-dashed border-slate-200 py-6 text-center text-sm text-slate-400 dark:border-slate-700"
            >
              {{ t('communicationApp.followUps.empty') }} — {{ t('communicationApp.followUps.emptyHint') }}
            </p>
            <ul v-else class="mb-5 space-y-3">
              <li
                v-for="rule in selectedRules"
                :key="rule.id"
                class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-100 p-4 dark:border-slate-700"
              >
                <div>
                  <p class="font-semibold text-slate-900 dark:text-white">{{ rule.name }}</p>
                  <p class="text-xs text-slate-400">{{ ruleStepsSummary(rule) }}</p>
                </div>
                <div class="flex items-center gap-2">
                  <button
                    type="button"
                    :disabled="ruleBusy === rule.id"
                    :class="[
                      'rounded-full px-4 py-1.5 text-xs font-semibold disabled:opacity-50',
                      rule.active
                        ? 'bg-emerald-100 text-emerald-700 hover:bg-emerald-200'
                        : 'bg-slate-100 text-slate-500 hover:bg-slate-200'
                    ]"
                    @click="toggleRule(rule)"
                  >
                    {{ rule.active ? t('communicationApp.followUps.active') : t('communicationApp.followUps.inactive') }}
                  </button>
                  <button
                    type="button"
                    :disabled="ruleBusy === rule.id"
                    class="rounded-full border border-red-200 px-3 py-1.5 text-xs font-semibold text-red-600 hover:bg-red-50 disabled:opacity-50"
                    @click="deleteRule(rule.id)"
                  >
                    {{ t('communicationApp.followUps.deleteRule') }}
                  </button>
                </div>
              </li>
            </ul>

            <div class="rounded-xl border border-slate-100 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-800/50">
              <div class="grid gap-3 sm:grid-cols-2">
                <label class="block text-sm">
                  <span class="mb-1 block font-semibold text-slate-600 dark:text-slate-300">
                    {{ t('communicationApp.followUps.ruleName') }}
                  </span>
                  <input
                    v-model="ruleName"
                    type="text"
                    maxlength="128"
                    :placeholder="t('communicationApp.followUps.ruleNamePlaceholder')"
                    class="w-full rounded-lg border-slate-200 text-sm dark:bg-slate-800 dark:text-white"
                  />
                </label>
                <div class="text-sm">
                  <span class="mb-1 block font-semibold text-slate-600 dark:text-slate-300">
                    {{ t('communicationApp.followUps.steps') }}
                  </span>
                  <div class="flex flex-wrap items-center gap-2">
                    <label
                      v-for="(delayDays, index) in ruleSteps"
                      :key="index"
                      class="flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-2 py-1 dark:border-slate-600 dark:bg-slate-800"
                    >
                      <span class="text-xs text-slate-500">{{ t('communicationApp.followUps.delayDays') }}</span>
                      <input
                        v-model.number="ruleSteps[index]"
                        type="number"
                        min="1"
                        max="90"
                        class="w-16 rounded border-slate-200 text-sm dark:bg-slate-900 dark:text-white"
                      />
                      <button
                        v-if="ruleSteps.length > 1"
                        type="button"
                        :aria-label="t('communicationApp.followUps.removeStep')"
                        class="text-slate-400 hover:text-red-500"
                        @click="ruleSteps.splice(index, 1)"
                      >
                        ✕
                      </button>
                    </label>
                    <button
                      v-if="ruleSteps.length < 3"
                      type="button"
                      class="rounded-full border border-indigo-200 px-3 py-1 text-xs font-semibold text-indigo-700 hover:bg-indigo-50"
                      @click="ruleSteps.push(7)"
                    >
                      + {{ t('communicationApp.followUps.addStep') }}
                    </button>
                  </div>
                </div>
              </div>
              <button
                type="button"
                :disabled="creatingRule || ruleName.trim() === ''"
                class="mt-4 rounded-full bg-indigo-600 px-5 py-2 text-xs font-bold text-white hover:bg-indigo-700 disabled:opacity-50"
                @click="createRule"
              >
                {{ t('communicationApp.followUps.create') }}
              </button>
            </div>
          </section>

          <!-- Exclusions de relance (R4) -->
          <section class="glass-card rounded-xl p-6 shadow">
            <h2 class="mb-1 text-lg font-bold text-slate-900 dark:text-white">
              {{ t('communicationApp.optOuts.title') }}
            </h2>
            <p class="mb-4 text-sm text-slate-500 dark:text-slate-400">
              {{ t('communicationApp.optOuts.subtitle') }}
            </p>

            <div class="mb-4 flex flex-wrap items-end gap-3">
              <label class="block text-sm">
                <span class="mb-1 block font-semibold text-slate-600 dark:text-slate-300">
                  {{ t('communicationApp.optOuts.email') }}
                </span>
                <input
                  v-model="optOutEmail"
                  type="email"
                  :placeholder="t('communicationApp.optOuts.emailPlaceholder')"
                  class="w-64 rounded-lg border-slate-200 text-sm dark:bg-slate-800 dark:text-white"
                />
              </label>
              <button
                type="button"
                :disabled="optOutBusy || optOutEmail.trim() === ''"
                class="rounded-full bg-slate-800 px-5 py-2 text-xs font-bold text-white hover:bg-slate-900 disabled:opacity-50"
                @click="addOptOut"
              >
                {{ t('communicationApp.optOuts.add') }}
              </button>
            </div>

            <p
              v-if="optOuts.length === 0"
              class="rounded-xl border border-dashed border-slate-200 py-6 text-center text-sm text-slate-400 dark:border-slate-700"
            >
              {{ t('communicationApp.optOuts.empty') }}
            </p>
            <ul v-else class="space-y-2">
              <li
                v-for="optOut in optOuts"
                :key="optOut.id"
                class="flex items-center justify-between gap-3 rounded-lg border border-slate-100 px-4 py-2 text-sm dark:border-slate-700"
              >
                <span class="font-medium text-slate-800 dark:text-slate-200">{{ optOut.email }}</span>
                <span class="flex items-center gap-2">
                  <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs text-slate-500 dark:bg-slate-800 dark:text-slate-300">
                    {{ t(`communicationApp.optOuts.source.${optOut.source}`) }}
                  </span>
                  <button
                    type="button"
                    class="rounded-full border border-red-200 px-3 py-0.5 text-xs font-semibold text-red-600 hover:bg-red-50"
                    @click="removeOptOut(optOut.id)"
                  >
                    {{ t('communicationApp.optOuts.remove') }}
                  </button>
                </span>
              </li>
            </ul>
          </section>
        </template>
      </div>
    </template>
  </div>
</template>

<script setup>
/**
 * BC-29 COMMUNICATION — R6 (#7691) : écran plateforme « Boîte connectée ».
 *
 * Trois onglets sur l'API tenant `/communication/*` (R1→R5) :
 *  - Boîte connectée : OAuth Google (R1), fils/messages classés (R2/R3),
 *    propositions de contacts CRM (R3) ;
 *  - File de confirmations : propositions de réponses IA (R5) —
 *    éditer/approuver/rejeter, approve = seul chemin d'envoi du mode confirm ;
 *  - Réglages : politiques off/draft/confirm/auto par boîte × catégorie (R5,
 *    `auto` grisé quand `auto_blocked`), règles de relance + exclusions (R4).
 *
 * i18n : namespace partagé `communicationApp` (shared/i18n, FR/EN/AR/TR).
 */
import { computed, onMounted, ref, watch } from 'vue'
import {
  ChevronDownIcon,
  ChevronUpIcon,
  ExclamationTriangleIcon,
  InboxIcon,
} from '@heroicons/vue/24/outline'
import { useToast } from 'vue-toastification'
import api from '@/services/api'
import { translate } from '@/i18n/index.js'
import { useLocaleStore } from '@/stores/locale.js'

const localeStore = useLocaleStore()
const toast = useToast()

/** Traduction avec interpolation `:variable` (placeholders style Laravel). */
const t = (key, vars = {}) => {
  let msg = translate(localeStore.current, key) || key
  for (const [k, v] of Object.entries(vars)) {
    msg = msg.replace(`:${k}`, String(v))
  }
  return msg
}

const GMAIL_SEND_SCOPE = 'https://www.googleapis.com/auth/gmail.send'

const tabs = computed(() => [
  { id: 'inbox', label: t('communicationApp.tabs.inbox') },
  { id: 'replies', label: t('communicationApp.tabs.replies') },
  { id: 'settings', label: t('communicationApp.tabs.settings') },
])
const activeTab = ref('inbox')

const loading = ref(true)
const forbidden = ref(false)

const moduleStatus = ref(null)
const integrations = ref([])
const categories = ref([])
const proposals = ref([])
const threads = ref([])
const selectedIntegration = ref(null)

const withSend = ref(false)
const connecting = ref(false)
const revoking = ref(null)
const proposalBusy = ref(null)

const openThread = ref(null)
const threadMessages = ref({})
const threadLoading = ref(null)

const replyStatuses = ['pending', 'drafted', 'sent', 'rejected', 'skipped', 'failed']
const replyStatusFilter = ref('pending')
const pendingReplies = ref([])
const editingReply = ref(null)
const editSubject = ref('')
const editBody = ref('')
const replyBusy = ref(null)

const policies = ref([])
const rules = ref([])
const optOuts = ref([])
const policyBusy = ref(null)
const ruleName = ref('')
const ruleSteps = ref([3])
const creatingRule = ref(false)
const ruleBusy = ref(null)
const optOutEmail = ref('')
const optOutBusy = ref(false)

const activeIntegrations = computed(() =>
  integrations.value.filter((integration) => integration.status === 'active'),
)
const activeCategories = computed(() => categories.value.filter((category) => category.active))
const selectedRules = computed(() =>
  rules.value.filter((rule) => rule.integration_id === selectedIntegration.value),
)

function policyFor(categoryKey) {
  return policies.value.find(
    (policy) =>
      policy.integration_id === selectedIntegration.value && policy.category_key === categoryKey,
  )
}

function hasSendScope(integration) {
  return (integration.scopes || []).includes(GMAIL_SEND_SCOPE)
}

function categoryLabelFor(key) {
  if (!key) return t('communicationApp.messages.uncategorized')
  const category = categories.value.find((item) => item.key === key)
  return category ? category.label : key
}

function formatDate(value) {
  if (!value) return '—'
  try {
    return new Date(value).toLocaleString(localeStore.current)
  } catch {
    return value
  }
}

function replyStatusBadge(status) {
  const map = {
    pending: 'bg-amber-100 text-amber-700',
    drafted: 'bg-indigo-100 text-indigo-700',
    sent: 'bg-emerald-100 text-emerald-700',
    rejected: 'bg-slate-200 text-slate-600',
    skipped: 'bg-slate-100 text-slate-500',
    failed: 'bg-red-100 text-red-700',
  }
  return map[status] || map.pending
}

function ruleStepsSummary(rule) {
  return (rule.steps || [])
    .map((step, index) =>
      t('communicationApp.followUps.stepLabel', {
        position: step.position ?? index + 1,
        days: step.delay_days,
      }),
    )
    .join(' · ')
}

function switchTab(tabId) {
  activeTab.value = tabId
  if (tabId === 'replies' && pendingReplies.value.length === 0) {
    loadReplies()
  }
}

async function loadAll() {
  loading.value = true
  forbidden.value = false
  try {
    const [statusRes, integrationsRes, categoriesRes, proposalsRes, policiesRes, rulesRes, optOutsRes] =
      await Promise.allSettled([
        api.get('/communication/status'),
        api.get('/communication/integrations'),
        api.get('/communication/categories'),
        api.get('/communication/contact-proposals', { params: { status: 'proposed', per_page: 50 } }),
        api.get('/communication/reply-policies'),
        api.get('/communication/follow-up-rules'),
        api.get('/communication/follow-up-opt-outs'),
      ])

    if (integrationsRes.status === 'rejected') {
      const httpStatus = integrationsRes.reason?.response?.status
      forbidden.value = httpStatus === 403 || httpStatus === 404
      return
    }

    moduleStatus.value = statusRes.status === 'fulfilled' ? statusRes.value.data?.data : null
    integrations.value = integrationsRes.value.data?.data || []
    categories.value = categoriesRes.status === 'fulfilled' ? categoriesRes.value.data?.data || [] : []
    proposals.value = proposalsRes.status === 'fulfilled' ? proposalsRes.value.data?.data || [] : []
    policies.value = policiesRes.status === 'fulfilled' ? policiesRes.value.data?.data || [] : []
    rules.value = rulesRes.status === 'fulfilled' ? rulesRes.value.data?.data || [] : []
    optOuts.value = optOutsRes.status === 'fulfilled' ? optOutsRes.value.data?.data || [] : []

    if (!selectedIntegration.value) {
      const firstActive = integrations.value.find((integration) => integration.status === 'active')
      selectedIntegration.value = firstActive ? firstActive.id : null
    }
  } finally {
    loading.value = false
  }
}

async function loadThreads() {
  if (!selectedIntegration.value) {
    threads.value = []
    return
  }
  try {
    const res = await api.get('/communication/threads', {
      params: { integration: selectedIntegration.value, per_page: 50 },
    })
    threads.value = res.data?.data || []
  } catch {
    threads.value = []
  }
}

async function connectGoogle() {
  connecting.value = true
  try {
    const res = await api.post('/communication/integrations/google', { with_send: withSend.value })
    const url = res.data?.data?.authorization_url
    if (url) {
      window.location.assign(url)
      return
    }
    toast.error(t('communicationApp.mailbox.connectError'))
  } catch {
    toast.error(t('communicationApp.mailbox.connectError'))
  } finally {
    connecting.value = false
  }
}

async function revokeIntegration(integrationId) {
  if (!window.confirm(t('communicationApp.mailbox.revokeConfirm'))) return
  revoking.value = integrationId
  try {
    await api.delete(`/communication/integrations/${integrationId}`)
    toast.success(t('communicationApp.mailbox.revokeSuccess'))
    selectedIntegration.value = null
    await loadAll()
    await loadThreads()
  } catch {
    toast.error(t('communicationApp.mailbox.revokeError'))
  } finally {
    revoking.value = null
  }
}

async function toggleThread(threadId) {
  if (openThread.value === threadId) {
    openThread.value = null
    return
  }
  openThread.value = threadId
  if (threadMessages.value[threadId]) return
  threadLoading.value = threadId
  try {
    const res = await api.get(`/communication/threads/${threadId}/messages`)
    threadMessages.value = {
      ...threadMessages.value,
      [threadId]: res.data?.data?.messages || [],
    }
  } catch {
    toast.error(t('communicationApp.threads.messagesError'))
  } finally {
    threadLoading.value = null
  }
}

async function reclassify(messageId) {
  try {
    await api.post(`/communication/messages/${messageId}/classify`)
    toast.success(t('communicationApp.messages.reclassifyQueued'))
  } catch {
    toast.error(t('communicationApp.messages.reclassifyError'))
  }
}

async function decideProposal(proposalId, decision) {
  proposalBusy.value = proposalId
  try {
    await api.post(`/communication/contact-proposals/${proposalId}/${decision}`)
    toast.success(
      t(decision === 'accept' ? 'communicationApp.proposals.accepted' : 'communicationApp.proposals.dismissed'),
    )
    proposals.value = proposals.value.filter((proposal) => proposal.id !== proposalId)
  } catch {
    toast.error(t('communicationApp.proposals.actionError'))
  } finally {
    proposalBusy.value = null
  }
}

async function loadReplies() {
  try {
    const res = await api.get('/communication/pending-replies', {
      params: { status: replyStatusFilter.value, per_page: 50 },
    })
    pendingReplies.value = res.data?.data || []
  } catch {
    pendingReplies.value = []
  }
}

function startReplyEdit(reply) {
  editingReply.value = reply.id
  editSubject.value = reply.subject || ''
  editBody.value = reply.body || ''
}

async function saveReplyEdit(replyId) {
  replyBusy.value = replyId
  try {
    const res = await api.patch(`/communication/pending-replies/${replyId}`, {
      subject: editSubject.value,
      body: editBody.value,
    })
    const updated = res.data?.data
    pendingReplies.value = pendingReplies.value.map((reply) =>
      reply.id === replyId && updated ? updated : reply,
    )
    editingReply.value = null
    toast.success(t('communicationApp.replies.saved'))
  } catch {
    toast.error(t('communicationApp.replies.saveError'))
  } finally {
    replyBusy.value = null
  }
}

async function decideReply(replyId, decision) {
  replyBusy.value = replyId
  try {
    await api.post(`/communication/pending-replies/${replyId}/${decision}`)
    toast.success(
      t(decision === 'approve' ? 'communicationApp.replies.approveSuccess' : 'communicationApp.replies.rejectSuccess'),
    )
    await loadReplies()
  } catch (error) {
    const status = error?.response?.status
    if (status === 429) {
      toast.error(t('communicationApp.replies.rateLimited'))
    } else if (status === 502) {
      toast.error(t('communicationApp.replies.sendFailed'))
    } else if (status === 422) {
      const reason = error?.response?.data?.reason || error?.response?.data?.message || ''
      toast.error(t('communicationApp.replies.blocked', { reason }))
      await loadReplies()
    } else {
      toast.error(t('communicationApp.replies.actionError'))
    }
  } finally {
    replyBusy.value = null
  }
}

async function savePolicy(categoryKey, level) {
  if (!selectedIntegration.value) return
  policyBusy.value = categoryKey
  try {
    const res = await api.post('/communication/reply-policies', {
      integration_id: selectedIntegration.value,
      category_key: categoryKey,
      policy: level,
    })
    const saved = res.data?.data
    policies.value = [
      ...policies.value.filter(
        (policy) =>
          !(policy.integration_id === selectedIntegration.value && policy.category_key === categoryKey),
      ),
      ...(saved ? [saved] : []),
    ]
    toast.success(t('communicationApp.policies.saved'))
  } catch (error) {
    const code = error?.response?.data?.code
    const keyByCode = {
      REPLY_AUTO_CATEGORY_BLOCKED: 'communicationApp.policies.errors.autoCategoryBlocked',
      GMAIL_SEND_SCOPE_REQUIRED: 'communicationApp.policies.errors.sendScopeRequired',
      GMAIL_COMPOSE_SCOPE_REQUIRED: 'communicationApp.policies.errors.composeScopeRequired',
      REPLY_CATEGORY_UNKNOWN: 'communicationApp.policies.errors.categoryUnknown',
    }
    toast.error(t(keyByCode[code] || 'communicationApp.policies.saveError'))
  } finally {
    policyBusy.value = null
  }
}

async function createRule() {
  if (!selectedIntegration.value || ruleName.value.trim() === '') return
  creatingRule.value = true
  try {
    const res = await api.post('/communication/follow-up-rules', {
      integration_id: selectedIntegration.value,
      name: ruleName.value.trim(),
      active: true,
      steps: ruleSteps.value.map((delayDays) => ({ delay_days: delayDays })),
    })
    if (res.data?.data) {
      rules.value = [...rules.value, res.data.data]
    }
    ruleName.value = ''
    ruleSteps.value = [3]
    toast.success(t('communicationApp.followUps.created'))
  } catch {
    toast.error(t('communicationApp.followUps.createError'))
  } finally {
    creatingRule.value = false
  }
}

async function toggleRule(rule) {
  ruleBusy.value = rule.id
  try {
    const res = await api.patch(`/communication/follow-up-rules/${rule.id}`, { active: !rule.active })
    const updated = res.data?.data
    rules.value = rules.value.map((item) => (item.id === rule.id && updated ? updated : item))
    toast.success(t('communicationApp.followUps.updated'))
  } catch {
    toast.error(t('communicationApp.followUps.updateError'))
  } finally {
    ruleBusy.value = null
  }
}

async function deleteRule(ruleId) {
  if (!window.confirm(t('communicationApp.followUps.deleteConfirm'))) return
  ruleBusy.value = ruleId
  try {
    await api.delete(`/communication/follow-up-rules/${ruleId}`)
    rules.value = rules.value.filter((rule) => rule.id !== ruleId)
    toast.success(t('communicationApp.followUps.deleted'))
  } catch {
    toast.error(t('communicationApp.followUps.updateError'))
  } finally {
    ruleBusy.value = null
  }
}

async function addOptOut() {
  if (optOutEmail.value.trim() === '') return
  optOutBusy.value = true
  try {
    const res = await api.post('/communication/follow-up-opt-outs', { email: optOutEmail.value.trim() })
    if (res.data?.data) {
      optOuts.value = [...optOuts.value, res.data.data]
    }
    optOutEmail.value = ''
    toast.success(t('communicationApp.optOuts.added'))
  } catch {
    toast.error(t('communicationApp.optOuts.addError'))
  } finally {
    optOutBusy.value = false
  }
}

async function removeOptOut(optOutId) {
  try {
    await api.delete(`/communication/follow-up-opt-outs/${optOutId}`)
    optOuts.value = optOuts.value.filter((optOut) => optOut.id !== optOutId)
    toast.success(t('communicationApp.optOuts.removed'))
  } catch {
    toast.error(t('communicationApp.optOuts.addError'))
  }
}

watch(selectedIntegration, () => {
  openThread.value = null
  threadMessages.value = {}
  loadThreads()
})

onMounted(async () => {
  await loadAll()
  await loadThreads()
})
</script>
