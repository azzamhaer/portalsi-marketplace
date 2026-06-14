<script lang="ts">
  import Icon from '$lib/components/Icon.svelte';
  import { onMount } from 'svelte';
  import { apiEndpoints } from '$lib/api';
  import { fmtRp } from '$lib/utils';
  import { toast, confirmDialog } from '$lib/stores.svelte';

  let stats = $state<any>(null);
  let freshSummary = $state<any>(null);
  let freshLoading = $state(false);
  let freshRunning = $state(false);
  let loading = $state(true);
  onMount(async () => {
    try { stats = await apiEndpoints.adminStats(); }
    finally { loading = false; }
  });

  async function loadFreshSummary() {
    freshLoading = true;
    try {
      freshSummary = await apiEndpoints.adminFreshStartSummary();
    } catch (e: any) {
      toast.error(e.message || 'Gagal memuat ringkasan fresh start');
    } finally {
      freshLoading = false;
    }
  }

  async function runFreshStart() {
    if (!freshSummary) await loadFreshSummary();
    const ok = await confirmDialog.ask({
      title: 'Hapus semua data testing?',
      message: 'Aksi ini menghapus semua user non-admin, vendor, produk, order, chat, laporan, wishlist, review, voucher, withdrawal, notifikasi, dan token terkait. Settings app, kurir, payment methods, FAQ, kategori, tag, dan admin tetap aman.',
      confirmText: 'Ya, fresh start',
      tone: 'danger',
    });
    if (!ok) return;
    freshRunning = true;
    try {
      await apiEndpoints.adminFreshStart();
      toast.success('Data testing berhasil dibersihkan');
      freshSummary = await apiEndpoints.adminFreshStartSummary();
      stats = await apiEndpoints.adminStats();
    } catch (e: any) {
      toast.error(e.message || 'Fresh start gagal');
    } finally {
      freshRunning = false;
    }
  }

  const cards = $derived(stats ? [
    { i:'users',          l:'Total Users',        v: stats.users,          h:'/admin/users' },
    { i:'store',          l:'Total Vendor',       v: stats.vendors,        h:'/admin/vendors' },
    { i:'shopping-cart',  l:'Total Pesanan',      v: stats.orders,         h:'/admin/orders' },
    { i:'trending-up',    l:'Pesanan Hari Ini',   v: stats.orders_today,   h:'/admin/orders' },
    { i:'wallet',         l:'Total Revenue',      v: fmtRp(stats.revenue), h:'/admin/orders' },
    { i:'shield-alert',   l:'Vendor Pending',     v: stats.pending_vendors,h:'/admin/vendors' },
    { i:'undo-2',         l:'Return Pending',     v: stats.pending_returns,h:'/admin/returns' }
  ] : []);
</script>

<svelte:head><title>Admin Dashboard</title></svelte:head>

{#if loading}<div class="card text-center text-ink-500 py-12">Memuat…</div>
{:else}
  <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3 sm:gap-4">
    {#each cards as c}
      <a href={c.h} class="card-hover group">
        <div class="w-10 h-10 rounded-xl bg-ink-100 grid place-items-center mb-3 group-hover:bg-ink-950 transition">
          <Icon name={c.i} size={18} class="text-ink-700 group-hover:text-white transition" />
        </div>
        <div class="text-[11px] uppercase tracking-widest text-ink-500">{c.l}</div>
        <div class="font-display text-2xl font-bold tracking-tightest mt-1">{c.v}</div>
      </a>
    {/each}
  </div>

  <div class="card mt-6">
    <h3 class="font-semibold mb-2">Aksi Cepat</h3>
    <div class="grid sm:grid-cols-3 gap-3">
      <a href="/admin/vendors?status=PENDING" class="btn-outline btn-md">Verifikasi vendor pending</a>
      <a href="/admin/settings" class="btn-outline btn-md">Edit branding & Tripay</a>
      <a href="/admin/returns" class="btn-outline btn-md">Tinjau permintaan return</a>
    </div>
  </div>

  <div class="card mt-6 border-red-100 bg-red-50/40">
    <div class="flex items-start gap-3">
      <div class="grid h-10 w-10 place-items-center rounded-xl bg-red-100 text-red-700">
        <Icon name="database-zap" size={18} />
      </div>
      <div class="min-w-0 flex-1">
        <h3 class="font-semibold text-red-900">Fresh Start Data Testing</h3>
        <p class="mt-1 text-sm text-red-800/80">
          Membersihkan data hasil migrate seed/testing: user non-admin, vendor, produk, order, chat, laporan, wishlist, review, voucher, withdrawal, dan notifikasi. Konfigurasi app tetap disimpan.
        </p>
        {#if freshSummary}
          <div class="mt-4 grid grid-cols-2 sm:grid-cols-4 gap-2 text-xs">
            <div class="rounded-xl bg-white p-3"><b>{freshSummary.users_removed}</b><br />User non-admin</div>
            <div class="rounded-xl bg-white p-3"><b>{freshSummary.vendors}</b><br />Vendor</div>
            <div class="rounded-xl bg-white p-3"><b>{freshSummary.products}</b><br />Produk</div>
            <div class="rounded-xl bg-white p-3"><b>{freshSummary.orders}</b><br />Pesanan</div>
            <div class="rounded-xl bg-white p-3"><b>{freshSummary.chats}</b><br />Chat thread</div>
            <div class="rounded-xl bg-white p-3"><b>{freshSummary.reports}</b><br />Laporan</div>
            <div class="rounded-xl bg-white p-3"><b>{freshSummary.notifications}</b><br />Notifikasi</div>
            <div class="rounded-xl bg-white p-3"><b>{freshSummary.admins_kept}</b><br />Admin tetap</div>
          </div>
        {/if}
        <div class="mt-4 flex flex-wrap gap-2">
          <button type="button" on:click={loadFreshSummary} disabled={freshLoading} class="btn-outline btn-sm">
            <Icon name="list-checks" size={13} /> {freshLoading ? 'Memuat...' : 'Lihat ringkasan'}
          </button>
          <button type="button" on:click={runFreshStart} disabled={freshRunning} class="btn-danger btn-sm">
            <Icon name="trash-2" size={13} /> {freshRunning ? 'Membersihkan...' : 'Hapus data testing'}
          </button>
        </div>
      </div>
    </div>
  </div>
{/if}
