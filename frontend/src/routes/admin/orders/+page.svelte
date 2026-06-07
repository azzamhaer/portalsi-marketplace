<script lang="ts">
  import { onMount } from 'svelte';
  import { apiEndpoints } from '$lib/api';
  import { fmtRp, statusPill, ORDER_STATUS_LABEL } from '$lib/utils';
  import { toast } from '$lib/stores.svelte';

  let orders = $state<any[]>([]);
  let loading = $state(true);
  let status = $state('');

  async function load() {
    loading = true;
    try {
      const r: any = await apiEndpoints.adminOrders(status ? `status=${status}` : '');
      orders = r.data ?? [];
    } finally { loading = false; }
  }
  onMount(load);

  async function changeStatus(o: any, newStatus: string) {
    try { await apiEndpoints.adminUpdateOrder(o.id, { status: newStatus }); toast.success('Status diubah'); load(); }
    catch (e: any) { toast.error(e.message); }
  }
</script>

<div class="card flex items-center gap-3 mb-4 flex-wrap">
  <h3 class="font-semibold">Semua Pesanan</h3>
  <select bind:value={status} on:change={load} class="input-sm input w-48 ml-auto">
    <option value="">Semua status</option>
    {#each Object.entries(ORDER_STATUS_LABEL) as [k, v]}<option value={k}>{v}</option>{/each}
  </select>
</div>

{#if loading}<div class="card text-center text-ink-500 py-10">Memuat…</div>
{:else}
  <div class="card overflow-x-auto">
    <table class="w-full text-sm">
      <thead class="text-xs text-ink-500 border-b border-ink-100">
        <tr><th class="text-left py-2">Order</th><th class="text-left py-2">Pembeli</th><th class="text-left py-2">Total</th><th class="text-left py-2">Status</th><th class="text-left py-2">Aksi</th></tr>
      </thead>
      <tbody>
        {#each orders as o (o.id)}
          <tr class="border-b border-ink-100 last:border-0">
            <td class="py-2"><b class="font-mono text-xs">{o.order_number}</b><div class="text-[11px] text-ink-500">{new Date(o.created_at).toLocaleDateString('id-ID')}</div></td>
            <td class="py-2">{o.user?.name}<div class="text-[11px] text-ink-500">{o.address?.city}</div></td>
            <td class="py-2 font-semibold">{fmtRp(o.total)}</td>
            <td class="py-2"><span class="pill {statusPill(o.status)}">{ORDER_STATUS_LABEL[o.status]}</span></td>
            <td class="py-2">
              <select on:change={(e: any) => changeStatus(o, e.target.value)} value={o.status} class="text-xs border border-ink-200 rounded px-2 py-1">
                {#each Object.entries(ORDER_STATUS_LABEL) as [k, v]}<option value={k}>{v}</option>{/each}
              </select>
            </td>
          </tr>
        {/each}
      </tbody>
    </table>
  </div>
{/if}
