// ============================================================
// Edge Nodes Dashboard Page — Priority 3.2
// Manages Leopardo Edge nodes for the company
// ============================================================

'use client';

import { useState, useEffect, useSyncExternalStore } from 'react';
import { motion } from 'framer-motion';
import { Cpu, Plus, RefreshCw, ShieldAlert, Wifi, WifiOff, X } from 'lucide-react';
import { ApiError, apiFetch } from '@/lib/api-client';
import { ModulePageShell } from '@/components/module-page-shell';
import { getCopy, getPreferredLocale, toIntlLocale, type AppLocale } from '@/lib/i18n';

const emptySubscribe = () => () => {};

interface EdgeNode {
  id: string;
  name: string;
  slug: string;
  site_address: string | null;
  status: 'active' | 'inactive' | 'suspended';
  mode: 'cloud' | 'offline' | 'hybrid';
  is_online: boolean;
  license_valid: boolean;
  license_expires_at: string | null;
  last_sync_at: string | null;
  last_seen_at: string | null;
  edge_version: string;
}

const MODE_STYLES: Record<string, string> = {
  cloud: 'bg-info/15 text-info',
  hybrid: 'bg-finance-light text-finance-dark',
  offline: 'bg-slate-100 text-slate-600',
};

export default function EdgeNodesPage() {
  const locale = useSyncExternalStore<AppLocale>(emptySubscribe, getPreferredLocale, () => 'fr');
  const labels = getCopy(locale).edgeNodesPage;
  const intlLocale = toIntlLocale(locale);
  const modeLabels: Record<string, string> = {
    cloud: labels.modeCloud,
    hybrid: labels.modeHybrid,
    offline: labels.modeOffline,
  };
                      </p>
                    </div>
                  </div>
                  <div className="flex items-center gap-2">
                    <span className={`rounded-full px-3 py-1 text-[11px] font-bold uppercase tracking-wider ${node.is_online ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-600'}`}>
                      {node.is_online ? labels.statusOnline : labels.statusOffline}
                    </span>
                    <button
                      onClick={() => triggerSync(node.id)}
                      disabled={syncing === node.id || !node.is_online}
                      className="inline-flex items-center gap-1.5 rounded-lg border border-app-border bg-white px-3 py-1.5 text-xs font-bold text-slate-700 transition hover:bg-slate-50 disabled:opacity-50"
                    >
                      <RefreshCw className={`h-3.5 w-3.5 ${syncing === node.id ? 'animate-spin' : ''}`} />
                      {syncing === node.id ? labels.syncing : labels.sync}
                    </button>
                  </div>
                </div>
              ))}
            </div>
          )}
        </section>
      </ModulePageShell>

      {showAddModal ? (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
          <div className="w-full max-w-md rounded-3xl bg-white p-6 shadow-xl">
            <div className="mb-4 flex items-center justify-between">
              <h2 className="flex items-center gap-2 text-lg font-black text-slate-950">
                <Cpu className="h-5 w-5 text-brand-600" /> {labels.modalTitle}
              </h2>
              <button onClick={() => setShowAddModal(false)} className="text-slate-400 hover:text-slate-600">
                <X className="h-5 w-5" />
              </button>
            </div>
            <div className="space-y-3">
              <div>
                <label className="mb-1 block text-xs font-bold uppercase tracking-wider text-slate-400">{labels.siteNameLabel}</label>
                <input
                  type="text"
                  placeholder={labels.siteNamePlaceholder}
                  value={newNode.name}
                  onChange={(e) => setNewNode({ ...newNode, name: e.target.value })}
                  className="w-full rounded-xl border border-app-border px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500"
                />
              </div>
              <div>
                <label className="mb-1 block text-xs font-bold uppercase tracking-wider text-slate-400">{labels.siteAddressLabel}</label>
                <input
                  type="text"
                  placeholder={labels.siteAddressPlaceholder}
                  value={newNode.site_address}
                  onChange={(e) => setNewNode({ ...newNode, site_address: e.target.value })}
                  className="w-full rounded-xl border border-app-border px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500"
                />
              </div>
              <div>
                <label className="mb-1 block text-xs font-bold uppercase tracking-wider text-slate-400">{labels.modeLabel}</label>
                <select
                  value={newNode.mode}
                  onChange={(e) => setNewNode({ ...newNode, mode: e.target.value })}
                  className="w-full rounded-xl border border-app-border px-3 py-2 text-sm"
                >
                  <option value="hybrid">{labels.modeHybridOption}</option>
                  <option value="offline">{labels.modeOfflineOption}</option>
                  <option value="cloud">{labels.modeCloudOption}</option>
                </select>
              </div>
            </div>
            <div className="mt-5 flex gap-3">
              <button
                onClick={() => setShowAddModal(false)}
                className="flex-1 rounded-xl border border-app-border px-4 py-2 text-sm font-bold text-slate-700 hover:bg-slate-50"
              >
                {labels.cancel}
              </button>
              <button
                onClick={addNode}
                disabled={!newNode.name}
                className="flex-1 rounded-xl bg-brand-600 px-4 py-2 text-sm font-bold text-white hover:bg-brand-700 disabled:opacity-50"
              >
                {labels.createNode}
              </button>
            </div>
          </div>
        </div>
      ) : null}
    </>
  );
}
