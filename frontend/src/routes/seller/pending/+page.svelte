<script lang="ts">
  import { onMount } from 'svelte';
  import Icon from '$lib/components/Icon.svelte';
  import { apiEndpoints, getToken } from '$lib/api';
  import { goto } from '$app/navigation';

  let vendor = $state<any>(null);
  let loading = $state(true);

  onMount(async () => {
    if (!getToken()) { goto('/login?next=/seller/pending'); return; }
    try {
      const data: any = await apiEndpoints.sellerDashboard();
      vendor = data.vendor;
      if (vendor.verification_status === 'APPROVED') {
        goto('/seller/dashboard');
        return;
      }
    } catch (e: any) {
      if (e.status === 404) { goto('/seller/register'); return; }
    } finally { loading = false; }
  });
</script>

<svelte:head><title>Menunggu Verifikasi — Seller</title></svelte:head>

<div class="container-x py-10 sm:py-16">
  <div class="max-w-xl mx-auto">
    {#if loading}
      <div class="card text-center text-ink-500 py-12">Memuat…</div>
    {:else if vendor}
      <div class="card text-center">
        {#if vendor.verification_status === 'PENDING'}
          <div class="w-16 h-16 rounded-full bg-amber-100 grid place-items-center mx-auto mb-4">
            <Icon name="clock" size={32} class="text-amber-600" />
          </div>
          <h1 class="font-display text-2xl font-bold tracking-tightest mb-2">Menunggu Verifikasi</h1>
          <p class="text-sm text-ink-500 mb-6">
            Toko <span class="font-semibold text-ink-900">{vendor.name}</span> sedang dalam proses verifikasi admin.
            Kami akan memeriksa data dan KTP yang Anda kirim. Mohon menunggu, biasanya 1–2 hari kerja.
          </p>
        {:else if vendor.verification_status === 'REJECTED'}
          <div class="w-16 h-16 rounded-full bg-red-100 grid place-items-center mx-auto mb-4">
            <Icon name="x-circle" size={32} class="text-red-600" />
          </div>
          <h1 class="font-display text-2xl font-bold tracking-tightest mb-2">Verifikasi Ditolak</h1>
          <p class="text-sm text-ink-500 mb-2">Verifikasi toko <span class="font-semibold text-ink-900">{vendor.name}</span> ditolak oleh admin.</p>
          {#if vendor.verification_note}
            <div class="bg-red-50 text-red-800 text-sm p-3 rounded-xl mb-6">
              <b>Alasan:</b> {vendor.verification_note}
            </div>
          {/if}
          <p class="text-sm text-ink-500 mb-6">Silakan perbarui profil toko Anda lalu hubungi admin untuk pengajuan ulang.</p>
        {/if}

        <div class="grid sm:grid-cols-2 gap-3 max-w-md mx-auto">
          <a href="/seller/profile" class="btn-outline btn-md">Lengkapi Profil</a>
          <a href="/" class="btn-primary btn-md">Kembali ke Beranda</a>
        </div>

        <div class="mt-8 pt-6 border-t border-ink-100 text-left text-sm">
          <h3 class="font-semibold mb-3">Sambil menunggu, lengkapi:</h3>
          <ul class="space-y-2 text-ink-600">
            <li class="flex items-start gap-2"><Icon name="check" size={14} class="mt-1 text-emerald-600" /> Data bank (BCA/Mandiri/BRI/dll) untuk pencairan dana</li>
            <li class="flex items-start gap-2"><Icon name="check" size={14} class="mt-1 text-emerald-600" /> Alamat & pin lokasi toko</li>
            <li class="flex items-start gap-2"><Icon name="check" size={14} class="mt-1 text-emerald-600" /> Deskripsi toko yang menarik</li>
          </ul>
        </div>
      </div>
    {/if}
  </div>
</div>
