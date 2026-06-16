<script lang="ts">
  import { goto } from '$app/navigation';
  import { page } from '$app/stores';
  import Icon from '$lib/components/Icon.svelte';
  import { settings } from '$lib/stores.svelte';

  let q = $state('');

  const status = $derived($page.status);
  const message = $derived($page.error?.message || 'Halaman tidak ditemukan');
  const isNotFound = $derived(status === 404);
  const isStoreMissing = $derived(message.toLowerCase().includes('toko'));
  const headline = $derived(
    isNotFound
      ? isStoreMissing
        ? 'Toko ini belum bisa ditemukan'
        : 'Halaman ini belum tersedia'
      : 'Ada yang tidak berjalan lancar'
  );
  const description = $derived(
    isNotFound
      ? isStoreMissing
        ? 'Toko mungkin sudah pindah alamat, sedang ditinjau, atau tautannya kurang lengkap. Coba cari toko atau produk lain di marketplace.'
        : 'Tautan yang Anda buka mungkin berubah, sudah dihapus, atau belum pernah dibuat.'
      : 'Coba muat ulang halaman, atau kembali ke beranda untuk lanjut belanja.'
  );
  const siteName = $derived(settings.appName ?? 'MPSI Marketplace');

  function search(e: Event) {
    e.preventDefault();
    const query = q.trim();
    if (!query) return;
    goto('/search?q=' + encodeURIComponent(query));
  }
</script>

<svelte:head>
  <title>{status} - {siteName}</title>
  <meta name="robots" content="noindex,nofollow" />
</svelte:head>

<section class="relative overflow-hidden bg-gradient-to-b from-white via-ink-50/70 to-white">
  <div class="container-x grid min-h-[calc(100vh-18rem)] items-center gap-10 py-12 sm:py-16 lg:grid-cols-[1fr_0.92fr] lg:py-20">
    <div class="max-w-2xl animate-slideUp">
      <div class="mb-5 inline-flex items-center gap-2 rounded-full border border-ink-200 bg-white px-3 py-1.5 text-xs font-semibold uppercase tracking-widest text-ink-500 shadow-soft">
        <span class="grid h-5 w-5 place-items-center rounded-full bg-app-primary text-[10px] text-app-pfg">{status}</span>
        Tidak Ketemu
      </div>

      <h1 class="font-display text-4xl font-extrabold tracking-tightest text-ink-950 text-balance sm:text-5xl lg:text-6xl">
        {headline}
      </h1>
      <p class="mt-4 max-w-xl text-base leading-relaxed text-ink-600 sm:text-lg">
        {description}
      </p>

      <form on:submit={search} class="mt-7 flex max-w-xl flex-col gap-3 rounded-[1.75rem] border border-ink-200 bg-white p-2 shadow-soft sm:flex-row">
        <label class="flex min-w-0 flex-1 items-center gap-3 px-3 py-2">
          <Icon name="search" size={18} class="shrink-0 text-ink-400" />
          <input
            bind:value={q}
            type="search"
            placeholder="Cari produk, brand, atau toko"
            class="w-full bg-transparent text-sm text-ink-900 outline-none placeholder:text-ink-400"
          />
        </label>
        <button type="submit" class="btn-primary btn-md shrink-0">Cari</button>
      </form>

      <div class="mt-6 flex flex-wrap gap-2">
        <a href="/" class="btn-primary btn-md">
          <Icon name="home" size={16} />
          Beranda
        </a>
        <a href="/products" class="btn-secondary btn-md">
          <Icon name="shopping-bag" size={16} />
          Produk
        </a>
        <a href="/vendors" class="btn-outline btn-md">
          <Icon name="store" size={16} />
          Toko
        </a>
      </div>
    </div>

    <div class="relative mx-auto w-full max-w-md animate-fadeIn lg:max-w-lg" aria-hidden="true">
      <div class="absolute inset-x-8 bottom-2 h-12 rounded-full bg-ink-950/10 blur-2xl"></div>
      <div class="relative rounded-[2rem] border border-ink-200 bg-white p-5 shadow-elevated">
        <div class="mb-5 flex items-center justify-between">
          <div class="flex items-center gap-2">
            <span class="h-3 w-3 rounded-full bg-red-400"></span>
            <span class="h-3 w-3 rounded-full bg-amber-400"></span>
            <span class="h-3 w-3 rounded-full bg-emerald-400"></span>
          </div>
          <span class="rounded-full bg-ink-100 px-3 py-1 text-xs font-semibold text-ink-500">/{status}</span>
        </div>

        <div class="rounded-[1.5rem] bg-ink-50 p-4">
          <div class="grid aspect-[4/3] place-items-center rounded-[1.25rem] border border-dashed border-ink-300 bg-white">
            <div class="relative h-48 w-64 max-w-full">
              <div class="absolute left-4 top-14 h-28 w-56 rounded-2xl border border-ink-200 bg-white shadow-soft"></div>
              <div class="absolute left-8 top-8 h-20 w-48 rounded-2xl bg-app-primary text-app-pfg shadow-elevated">
                <div class="flex h-full items-center justify-center gap-3 px-5">
                  <Icon name="store" size={28} />
                  <span class="text-5xl font-black tracking-tightest">404</span>
                </div>
              </div>
              <div class="absolute bottom-8 left-10 h-3 w-44 rounded-full bg-ink-200"></div>
              <div class="absolute bottom-3 left-20 h-3 w-24 rounded-full bg-ink-100"></div>
              <div class="absolute bottom-12 right-7 grid h-16 w-16 place-items-center rounded-2xl bg-white shadow-elevated ring-1 ring-ink-100">
                <Icon name="package-search" size={28} class="text-app-primary" />
              </div>
              <div class="absolute left-3 top-36 grid h-12 w-12 place-items-center rounded-2xl bg-amber-50 text-amber-700 shadow-soft">
                <Icon name="map-pin-off" size={22} />
              </div>
            </div>
          </div>
        </div>

        <div class="mt-4 grid grid-cols-3 gap-2">
          <div class="h-16 rounded-2xl bg-ink-50"></div>
          <div class="h-16 rounded-2xl bg-ink-100"></div>
          <div class="h-16 rounded-2xl bg-ink-50"></div>
        </div>
      </div>
    </div>
  </div>
</section>
