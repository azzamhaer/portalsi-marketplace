<script lang="ts">
  import { onMount } from 'svelte';
  import { goto } from '$app/navigation';
  import { page } from '$app/stores';
  import { auth, toast } from '$lib/stores.svelte';
  import { apiEndpoints, getToken } from '$lib/api';
  import AdminBlock from '$lib/components/AdminBlock.svelte';
  import Icon from '$lib/components/Icon.svelte';

  let { children } = $props();
  let mounted = $state(false);
  let serverChecked = $state(false);

  const isAdmin = $derived(auth.user?.role === 'ADMIN');
  // Cek dari auth.user dulu (cache) - INSTAN, no flash
  const vendorStatus = $derived(auth.user?.vendor_status); // APPROVED | PENDING | REJECTED | undefined
  const hasVendor = $derived(!!auth.user?.vendor_id);

  const path = $derived($page.url.pathname);
  const exempt = ['/seller/register', '/seller/pending', '/seller/profile'];
  const isExempt = $derived(exempt.includes(path));

  onMount(async () => {
    mounted = true;
    if (!getToken() || !auth.user) {
      goto('/login?next=' + path);
      return;
    }
    if (auth.user.role === 'ADMIN') return;

    // Jalur exempt: tidak butuh server check
    if (isExempt) { serverChecked = true; return; }

    // Sudah punya status dari cache → langsung putuskan
    if (hasVendor) {
      if (vendorStatus === 'APPROVED') { serverChecked = true; return; }
      // PENDING / REJECTED → redirect ke pending
      goto('/seller/pending');
      return;
    }

    // Tidak punya vendor_id → coba server check dulu (mungkin cache outdated)
    try {
      const data: any = await apiEndpoints.sellerDashboard();
      const status = data?.vendor?.verification_status;
      // Sync auth cache
      auth.set({ ...auth.user, vendor_id: data.vendor.id, vendor_status: status, vendor_username: data.vendor.username });
      if (status !== 'APPROVED') { goto('/seller/pending'); return; }
      serverChecked = true;
    } catch (e: any) {
      if (e.status === 404) goto('/seller/register');
      else { toast.error(e.message); goto('/'); }
    }
  });
</script>

{#if isAdmin}
  <AdminBlock title="Admin tidak bisa menjadi seller" description="Akun admin dipakai untuk mengelola platform, bukan untuk berjualan." />
{:else if isExempt && mounted}
  {@render children()}
{:else if serverChecked}
  {@render children()}
{:else if hasVendor && vendorStatus && vendorStatus !== 'APPROVED'}
  <!-- Cache tahu status → langsung tampil pesan tanpa flash -->
  <div class="container-x py-16">
    <div class="max-w-md mx-auto card text-center">
      {#if vendorStatus === 'PENDING'}
        <div class="w-16 h-16 rounded-full bg-amber-100 grid place-items-center mx-auto mb-4">
          <Icon name="clock" size={28} class="text-amber-600" />
        </div>
        <h2 class="font-display text-xl font-bold tracking-tightest mb-2">Menunggu verifikasi admin</h2>
        <p class="text-sm text-ink-500 mb-5">Toko Anda sedang diverifikasi oleh admin. Anda belum bisa mengakses Seller Center.</p>
      {:else}
        <div class="w-16 h-16 rounded-full bg-red-100 grid place-items-center mx-auto mb-4">
          <Icon name="x-circle" size={28} class="text-red-600" />
        </div>
        <h2 class="font-display text-xl font-bold tracking-tightest mb-2">Verifikasi ditolak</h2>
        <p class="text-sm text-ink-500 mb-5">Pengajuan toko Anda ditolak. Buka detail untuk melihat alasan & perbarui profil.</p>
      {/if}
      <div class="flex gap-2 justify-center">
        <a href="/seller/pending" class="btn-primary btn-md">Lihat detail</a>
        <a href="/seller/profile" class="btn-outline btn-md">Lengkapi profil</a>
      </div>
    </div>
  </div>
{:else}
  <div class="container-x py-16">
    <div class="card text-center text-ink-500">{mounted ? 'Memuat…' : 'Memuat…'}</div>
  </div>
{/if}
