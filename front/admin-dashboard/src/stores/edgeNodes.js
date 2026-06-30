import { defineStore } from 'pinia';
import { ref } from 'vue';
import api from '@/services/api';

export const useEdgeNodesStore = defineStore('edgeNodes', () => {
  const nodes = ref([]);

  async function fetchNodes() {
    const { data } = await api.get('/v1/admin/edge-nodes');
    nodes.value = data.data;
  }

  async function triggerSync(nodeId) {
    await api.post(`/v1/admin/edge-nodes/${nodeId}/sync`);
  }

  async function revokeLicense(nodeId) {
    await api.post(`/v1/admin/edge-nodes/${nodeId}/revoke`);
    await fetchNodes();
  }

  return { nodes, fetchNodes, triggerSync, revokeLicense };
});
