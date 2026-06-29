import { defineStore } from 'pinia';
import { ref } from 'vue';
import axios from 'axios';

export interface EdgeNode {
  id: string;
  node_id: string;
  company_name: string;
  is_online: boolean;
  license_status: 'active' | 'expiring_soon' | 'expired' | 'revoked';
  license_expires_at: string | null;
  last_sync_at: string | null;
  silent_since: string | null;
  pending_records: number;
  ip_address: string | null;
  created_at: string;
}

export const useEdgeNodesStore = defineStore('edgeNodes', () => {
  const nodes = ref<EdgeNode[]>([]);

  async function fetchNodes(): Promise<void> {
    const { data } = await axios.get<{ data: EdgeNode[] }>('/api/v1/admin/edge-nodes');
    nodes.value = data.data;
  }

  async function triggerSync(nodeId: string): Promise<void> {
    await axios.post(`/api/v1/admin/edge-nodes/${nodeId}/sync`);
  }

  async function revokeLicense(nodeId: string): Promise<void> {
    await axios.post(`/api/v1/admin/edge-nodes/${nodeId}/revoke`);
    await fetchNodes();
  }

  return { nodes, fetchNodes, triggerSync, revokeLicense };
});
