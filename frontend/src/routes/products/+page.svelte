<script lang="ts">
  import ProductGrid from '$lib/components/ProductGrid.svelte';
  import ProductGridSkeleton from '$lib/components/ProductGridSkeleton.svelte';
  import Pagination from '$lib/components/Pagination.svelte';
  import ProductFilterPanel from '$lib/components/ProductFilterPanel.svelte';
  import SmartSearch from '$lib/components/SmartSearch.svelte';
  import Icon from '$lib/components/Icon.svelte';
  import { goto } from '$app/navigation';
  import { page } from '$app/stores';
  let { data } = $props();
  let filtersOpen = $state(false);
  const sorts = [
    ['popular', 'Terpopuler', 'flame'],
    ['newest', 'Terbaru', 'sparkles'],
    ['cheap', 'Termurah', 'arrow-down'],
    ['exp', 'Termahal', 'arrow-up'],
    ['rating', 'Rating', 'star'],
  ];

  function setSort(v: string) {
    const u = new URL($page.url);
    u.searchParams.set('sort', v);
    u.searchParams.set('page', '1');
    goto(u.pathname + '?' + u.searchParams.toString());
  }
  function clearTag() {
    const u = new URL($page.url);
    u.searchParams.delete('tag');
    goto(u.pathname + '?' + u.searchParams.toString());
  }
</script>

<svelte:head><title>Semua Produk</title></svelte:head>

<div class="container-x py-6 sm:py-8">
  <div class="mb-6 max-w-3xl">
    <SmartSearch placeholder="Cari produk di katalog" />
  </div>

  <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4 mb-4 sm:mb-6">
    <div>
      <div class="section-eyebrow mb-2">Katalog</div>
      <h1 class="section-title">{data.search ? `Hasil "${data.search}"` : data.tag ? `#${data.tag}` : 'Semua Produk'}</h1>
      {#await data.streamed.result}
        <p class="text-sm text-ink-500 mt-1">Memuat produk…</p>
      {:then r}
        <p class="text-sm text-ink-500 mt-1">{r.meta?.total ?? r.products.length} produk · halaman {r.meta?.current_page ?? 1} dari {r.meta?.last_page ?? 1}</p>
      {/await}
    </div>
    <div class="flex gap-2">
      {#if data.tag}<button on:click={clearTag} class="btn-outline btn-sm">Hapus tag</button>{/if}
    </div>
  </div>

  <div class="mb-6 flex flex-col gap-3 rounded-2xl border border-ink-100 bg-white p-2 shadow-soft sm:flex-row sm:items-center sm:justify-between">
    <div class="flex gap-1 overflow-x-auto pb-1 sm:pb-0">
      {#each sorts as [value, label, icon]}
        <button
          type="button"
          on:click={() => setSort(value)}
          class="flex shrink-0 items-center gap-1.5 rounded-xl px-3 py-2 text-xs font-semibold transition"
          class:bg-app-primary={data.sort === value}
          class:text-app-pfg={data.sort === value}
          class:bg-ink-50={data.sort !== value}
          class:text-ink-600={data.sort !== value}
          class:hover:bg-ink-100={data.sort !== value}
        >
          <Icon name={icon} size={13} /> {label}
        </button>
      {/each}
    </div>
    <button type="button" on:click={() => filtersOpen = !filtersOpen} class="flex items-center justify-center gap-2 rounded-xl border border-ink-100 px-4 py-2 text-sm font-semibold transition hover:bg-ink-50 lg:hidden">
      <Icon name="sliders-horizontal" size={16} />
      Filter produk
      <Icon name={filtersOpen ? 'chevron-up' : 'chevron-down'} size={16} />
    </button>
  </div>

  <div class="grid gap-8 lg:grid-cols-[270px_1fr]">
    <div class="lg:sticky lg:top-24 lg:h-fit">
      {#await data.streamed.filters}
        <div class="{filtersOpen ? 'block' : 'hidden'} rounded-2xl border border-ink-100 bg-white p-4 lg:block">
          <div class="h-5 w-28 bg-ink-100 rounded animate-pulse mb-4"></div>
          <div class="space-y-3">{#each Array(6) as _}<div class="h-10 bg-ink-100 rounded-xl animate-pulse"></div>{/each}</div>
        </div>
      {:then filters}
        <div class="{filtersOpen ? 'block' : 'hidden'} lg:block">
          <ProductFilterPanel tags={filters.tags} categories={filters.categories} />
        </div>
      {/await}
    </div>

    <div>
      {#await data.streamed.result}
        <ProductGridSkeleton count={24} />
      {:then r}
        <ProductGrid products={r.products} />
        <Pagination current={r.meta?.current_page ?? 1} last={r.meta?.last_page ?? 1} />
      {/await}
    </div>
  </div>
</div>
