<script lang="ts">
  import { onMount } from 'svelte';
  import SellerSidebar from '$lib/components/SellerSidebar.svelte';
  import Icon from '$lib/components/Icon.svelte';
  import { fmtRp, statusPill, ORDER_STATUS_LABEL } from '$lib/utils';
  import { apiEndpoints } from '$lib/api';
  import { toast, confirmDialog } from '$lib/stores.svelte';

  let items = $state<any[]>([]);
  let loading = $state(true);

  async function load() {
    loading = true;
    try { const r: any = await apiEndpoints.sellerOrders(); items = r.data ?? []; }
    finally { loading = false; }
  }
  onMount(load);

  async function ship(id: number) {
    const ok = await confirmDialog.ask({ title: 'Kirim pesanan?', message: 'Sistem akan membuat nomor resi dan mengubah status menjadi dikirim.', confirmText: 'Kirim' });
    if (!ok) return;
    try { const r: any = await apiEndpoints.sellerShipOrder(id); toast.success('Resi: ' + r.tracking_no); load(); }
    catch (e: any) { toast.error(e.message); }
  }
</script>

<svelte:head><title>Pesanan Masuk — MPSI Seller</title></svelte:head>

<div class="container-x py-8">
  <h1 class="section-title mb-8">Pesanan Masuk</h1>
  <div class="grid lg:grid-cols-[230px_1fr] gap-6">
    <SellerSidebar />
    <div class="space-y-5">
      {#if loading}<div class="card text-center text-ink-500 py-12">Memuat…</div>
      {:else if items.length === 0}
        <div class="card text-center py-12 text-ink-500">
          <Icon name="package-open" size={48} class="mx-auto text-ink-300 mb-3" />
          <p>Belum ada pesanan.</p>
        </div>
      {:else}
        <div class="card overflow-x-auto">
          <table class="w-full text-sm">
            <thead class="text-xs text-ink-500 border-b border-ink-100">
              <tr><th class="text-left py-2 font-medium">Order</th><th class="text-left py-2 font-medium">Pembeli</th><th class="text-left py-2 font-medium">Produk</th><th class="text-left py-2 font-medium">Total</th><th class="text-left py-2 font-medium">Status</th><th class="text-left py-2 font-medium">Aksi</th></tr>
            </thead>
            <tbody>
              {#each items as it (it.id)}
                <tr class="border-b border-ink-100 last:border-0">
                  <td class="py-2"><b class="font-mono text-xs">{it.order?.order_number}</b><div class="text-[11px] text-ink-500">{new Date(it.order?.created_at).toLocaleDateString('id-ID')}</div></td>
                  <td class="py-2">{it.order?.address?.recipient}<div class="text-[11px] text-ink-500">{it.order?.address?.city}</div></td>
                  <td class="py-2 max-w-xs"><div class="line-clamp-2">{it.product_name}</div><div class="text-[11px] text-ink-500">{it.quantity} pcs</div></td>
                  <td class="py-2">{fmtRp(it.price * it.quantity)}</td>
                  <td class="py-2"><span class="pill {statusPill(it.order?.status ?? '')}">{ORDER_STATUS_LABEL[it.order?.status] ?? it.order?.status}</span></td>
                  <td class="py-2">
                    {#if it.order?.status === 'PROCESSING'}
                      <button on:click={() => ship(it.order.id)} class="btn-primary btn-sm"><Icon name="truck" size={12} /> Kirim</button>
                    {:else}
                      <a href={`/orders/${it.order?.id}`} class="text-xs text-ink-700 hover:text-ink-950">Lihat</a>
                    {/if}
                  </td>
                </tr>
              {/each}
            </tbody>
          </table>
        </div>
      {/if}
    </div>
  </div>
</div>
