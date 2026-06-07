<script lang="ts">
  import { onMount } from 'svelte';
  import { apiEndpoints } from '$lib/api';
  import { toast } from '$lib/stores.svelte';
  import Icon from '$lib/components/Icon.svelte';
  import VendorBadge from '$lib/components/VendorBadge.svelte';
  import { page } from '$app/stores';

  let vendors = $state<any[]>([]);
  let loading = $state(true);
  let status = $state($page.url.searchParams.get('status') ?? '');
  let active = $state<any>(null);

  async function load() {
    loading = true;
    try {
      const params = new URLSearchParams();
      if (status) params.set('status', status);
      const r: any = await apiEndpoints.adminVendors(params.toString());
      vendors = r.data ?? [];
    } finally { loading = false; }
  }
  onMount(load);

  async function verify(v: any, st: 'APPROVED'|'REJECTED') {
    const note = st === 'REJECTED' ? prompt('Alasan penolakan:') : '';
    if (st === 'REJECTED' && !note) return;
    try { await apiEndpoints.adminVerifyVendor(v.id, st, note ?? ''); toast.success('Status diperbarui'); active = null; load(); }
    catch (e: any) { toast.error(e.message); }
  }
  async function del(v: any) {
    if (!confirm(`Hapus toko "${v.name}"?`)) return;
    try { await apiEndpoints.adminDeleteVendor(v.id); toast.success('Dihapus'); load(); }
    catch (e: any) { toast.error(e.message); }
  }
  async function setBadge(v: any, badge: string | null) {
    try {
      await apiEndpoints.adminSetVendorBadge(v.id, badge);
      v.badge = badge;
      toast.success(badge ? `Badge "${badge}" diberikan` : 'Badge dilepas');
      load();
    } catch (e: any) { toast.error(e.message); }
  }
</script>

<div class="card flex items-center gap-3 mb-4 flex-wrap">
  <h3 class="font-semibold">Vendor, Verifikasi & Badge</h3>
  <select bind:value={status} on:change={load} class="input-sm input w-48 ml-auto">
    <option value="">Semua status</option>
    <option value="PENDING">Pending</option>
    <option value="APPROVED">Approved</option>
    <option value="REJECTED">Rejected</option>
  </select>
</div>

{#if loading}<div class="card text-center text-ink-500 py-10">Memuat…</div>
{:else}
  <div class="grid sm:grid-cols-2 gap-4">
    {#each vendors as v (v.id)}
      <div class="card">
        <div class="flex items-center gap-3 mb-3">
          <img src={v.avatar} alt="" class="w-12 h-12 rounded-full object-cover" />
          <div class="flex-1 min-w-0">
            <div class="font-semibold truncate flex items-center gap-1.5">
              {v.name}
              {#if v.badge}<VendorBadge badge={v.badge} size={14} />{/if}
            </div>
            <div class="text-xs text-ink-500 truncate">{v.user?.email}</div>
          </div>
          <span class="pill-{v.verification_status === 'APPROVED' ? 'green' : v.verification_status === 'PENDING' ? 'amber' : 'red'}">{v.verification_status}</span>
        </div>
        <div class="text-xs text-ink-500 mb-2 flex items-center gap-1 flex-wrap">
          <Icon name="map-pin" size={11} /> {v.city}
          <span class="mx-1">·</span>
          <Icon name="star" size={11} class="text-amber-400" fill="currentColor" /> {v.rating}
          <span class="mx-1">·</span>
          {v.total_sold} terjual
          <span class="mx-1">·</span>
          <Icon name="users" size={11} /> {v.followers ?? 0}
        </div>

        {#if v.verification_status === 'APPROVED'}
          <div class="mt-2 mb-2 flex items-center gap-1.5 flex-wrap text-xs">
            <span class="text-ink-500">Badge:</span>
            <button on:click={() => setBadge(v, null)}      class="px-2 py-0.5 rounded-full border {!v.badge ? 'bg-app-primary text-app-pfg' : 'bg-ink-50 border-ink-200'}">Tidak ada</button>
            <button on:click={() => setBadge(v, 'VERIFIED')} class="px-2 py-0.5 rounded-full border {v.badge==='VERIFIED' ? 'bg-sky-500 text-white border-sky-500' : 'bg-sky-50 text-sky-700 border-sky-200'}">Verified</button>
            <button on:click={() => setBadge(v, 'MALL')}     class="px-2 py-0.5 rounded-full border {v.badge==='MALL' ? 'bg-purple-600 text-white border-purple-600' : 'bg-purple-50 text-purple-700 border-purple-200'}">Mall</button>
            <button on:click={() => setBadge(v, 'STAR')}     class="px-2 py-0.5 rounded-full border {v.badge==='STAR' ? 'bg-amber-500 text-white border-amber-500' : 'bg-amber-50 text-amber-700 border-amber-200'}">Star</button>
          </div>
        {/if}

        <div class="flex gap-2 mt-3 flex-wrap">
          <button on:click={() => active = v} class="btn-outline btn-sm"><Icon name="id-card" size={12} /> Lihat KTP</button>
          {#if v.verification_status !== 'APPROVED'}<button on:click={() => verify(v, 'APPROVED')} class="btn-primary btn-sm"><Icon name="check" size={12} /> Approve</button>{/if}
          {#if v.verification_status !== 'REJECTED'}<button on:click={() => verify(v, 'REJECTED')} class="btn-sm btn bg-amber-100 text-amber-800 hover:bg-amber-200"><Icon name="x" size={12} /> Tolak</button>{/if}
          <button on:click={() => del(v)} class="btn-sm btn bg-red-50 text-red-700 hover:bg-red-100"><Icon name="trash-2" size={12} /></button>
        </div>
      </div>
    {/each}
  </div>
{/if}

{#if active}
  <div class="fixed inset-0 z-50 bg-black/60 grid place-items-center p-4 animate-fadeIn" on:click={() => active = null} role="dialog">
    <div class="bg-white rounded-2xl p-6 max-w-2xl w-full max-h-[90vh] overflow-y-auto" on:click|stopPropagation>
      <div class="flex items-center justify-between mb-4">
        <h3 class="font-semibold">Verifikasi KTP — {active.name}</h3>
        <button on:click={() => active = null} class="w-8 h-8 grid place-items-center rounded-full hover:bg-ink-100"><Icon name="x" size={16} /></button>
      </div>
      {#if active.ktp_image}
        <img src={active.ktp_image} alt="KTP" class="w-full rounded-xl border border-ink-200 max-h-96 object-contain bg-ink-50" />
      {:else}
        <div class="card text-center py-10 text-ink-500">KTP belum diupload.</div>
      {/if}
      <div class="mt-4 text-sm space-y-1">
        <div><b>Nama Toko:</b> {active.name}</div>
        <div><b>Pemilik:</b> {active.user?.name} ({active.user?.email})</div>
        <div><b>Bank:</b> {active.bank_name} — {active.bank_account} ({active.bank_holder})</div>
        <div><b>Deskripsi:</b> {active.description}</div>
      </div>
      <div class="flex gap-2 mt-5">
        <button on:click={() => verify(active, 'APPROVED')} class="btn-primary btn-md flex-1"><Icon name="check" size={14} /> Approve</button>
        <button on:click={() => verify(active, 'REJECTED')} class="btn-outline btn-md flex-1"><Icon name="x" size={14} /> Tolak</button>
      </div>
    </div>
  </div>
{/if}
