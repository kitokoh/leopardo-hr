'use client';

import { useState } from 'react';
import { Shield, Key, Webhook, FileText, Plus, Trash2, Copy, Check } from 'lucide-react';
import Link from 'next/link';
import { motion } from 'framer-motion';

export default function DeveloperSettingsPage() {
  const [copiedKey, setCopiedKey] = useState<string | null>(null);

  const handleCopy = (text: string, key: string) => {
    navigator.clipboard.writeText(text);
    setCopiedKey(key);
    setTimeout(() => setCopiedKey(null), 2000);
  };

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold text-slate-900">Espace Développeur</h1>
        <p className="text-slate-500">Gérez vos clés API et vos webhooks pour intégrer Leopardo RH à vos outils.</p>
      </div>

      <div className="grid gap-6 md:grid-cols-2">
        <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
          <div className="flex items-center gap-3 border-b border-slate-100 pb-4">
            <div className="rounded-xl bg-blue-50 p-2 text-blue-600">
              <Key className="h-5 w-5" />
            </div>
            <h2 className="text-lg font-semibold text-slate-900">Clés API</h2>
          </div>
          
          <div className="mt-4 space-y-4">
            <div className="flex items-center justify-between rounded-xl border border-slate-100 bg-slate-50 p-4">
              <div>
                <p className="font-medium text-slate-900">Production Key</p>
                <p className="text-xs text-slate-500">Créée le 10 Juin 2026</p>
              </div>
              <div className="flex items-center gap-2">
                <button 
                  onClick={() => handleCopy('sk_prod_123456789', 'prod_key')}
                  className="rounded-lg p-2 text-slate-400 hover:bg-slate-200 hover:text-slate-600"
                >
                  {copiedKey === 'prod_key' ? <Check className="h-4 w-4 text-emerald-500" /> : <Copy className="h-4 w-4" />}
                </button>
                <button className="rounded-lg p-2 text-red-400 hover:bg-red-50 hover:text-red-600">
                  <Trash2 className="h-4 w-4" />
                </button>
              </div>
            </div>
          </div>
          
          <button className="mt-4 flex w-full items-center justify-center gap-2 rounded-xl border border-dashed border-slate-300 py-3 text-sm font-medium text-slate-600 hover:bg-slate-50 hover:text-slate-900">
            <Plus className="h-4 w-4" /> Nouvelle clé API
          </button>
        </div>

        <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
          <div className="flex items-center gap-3 border-b border-slate-100 pb-4">
            <div className="rounded-xl bg-purple-50 p-2 text-purple-600">
              <Webhook className="h-5 w-5" />
            </div>
            <h2 className="text-lg font-semibold text-slate-900">Webhooks</h2>
          </div>
          
          <div className="mt-4 space-y-4">
            <div className="rounded-xl border border-slate-100 bg-slate-50 p-4">
              <div className="flex items-center justify-between">
                <div>
                  <p className="font-medium text-slate-900">Sync Paie ERP</p>
                  <p className="text-xs text-slate-500">https://erp.client.com/webhook</p>
                </div>
                <span className="rounded-full bg-emerald-100 px-2 py-1 text-xs font-medium text-emerald-700">Actif</span>
              </div>
              <div className="mt-3 flex items-center justify-between border-t border-slate-200 pt-3">
                <span className="text-xs text-slate-500">Secret: whsec_...89ab</span>
                <button 
                  onClick={() => handleCopy('whsec_123456789', 'webhook_secret')}
                  className="flex items-center gap-1 text-xs font-medium text-blue-600 hover:underline"
                >
                  {copiedKey === 'webhook_secret' ? 'Copié !' : 'Copier le secret'}
                </button>
              </div>
            </div>
          </div>
          
          <button className="mt-4 flex w-full items-center justify-center gap-2 rounded-xl border border-dashed border-slate-300 py-3 text-sm font-medium text-slate-600 hover:bg-slate-50 hover:text-slate-900">
            <Plus className="h-4 w-4" /> Ajouter un endpoint
          </button>
        </div>
      </div>

      <div className="rounded-2xl border border-slate-200 bg-gradient-to-br from-slate-900 to-slate-800 p-6 text-white shadow-sm">
        <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
          <div>
            <h2 className="text-lg font-bold">Documentation API</h2>
            <p className="mt-1 text-sm text-slate-400">
              Découvrez comment intégrer nos webhooks signés (format Svix) et nos endpoints REST.
            </p>
          </div>
          <Link 
            href={process.env.NEXT_PUBLIC_API_URL?.replace('/api/v1', '/api-explorer') ?? '/api-explorer'} 
            target="_blank"
            className="inline-flex items-center gap-2 rounded-xl bg-white px-4 py-2 text-sm font-bold text-slate-900 transition hover:bg-slate-100"
          >
            <FileText className="h-4 w-4" />
            Ouvrir l'Explorer
          </Link>
        </div>
      </div>
    </div>
  );
}
