<script lang="ts">
  import ProductGrid from '$lib/components/ProductGrid.svelte';
  import ProductGridSkeleton from '$lib/components/ProductGridSkeleton.svelte';
  import Pagination from '$lib/components/Pagination.svelte';
  import ProductFilterPanel from '$lib/components/ProductFilterPanel.svelte';
  import { goto } from '$app/navigation';
  import { page } from '$app/stores';
  let { data } = $props();

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
  <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4 mb-6 sm:mb-8">
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
      <select on:change={(e: any) => setSort(e.target.value)} value={data.sort} class="input-sm input w-full sm:w-56">
        <option value="popular">Terpopuler</option>
        <option value="newest">Terbaru</option>
        <option value="cheap">Termurah</option>
        <option value="exp">Termahal</option>
        <option value="rating">Rating Tertinggi</option>
      </select>
    </div>
  </div>

  {#await data.streamed.filters}
    <div class="flex flex-wrap gap-2 mb-6 pb-6 border-b border-ink-100">
      {#each Array(8) as _}
        <span class="h-6 w-16 bg-ink-100 animate-pulse rounded-full"></span>
      {/each}
    </div>
  {:then filters}
    {#if filters.tags?.length}
      <div class="flex flex-wrap gap-2 mb-6 pb-6 border-b border-ink-100">
        {#each filters.tags.slice(0, 16) as t}
          <a href={`/products?tag=${t.slug}`} class="text-xs px-3 py-1.5 rounded-full transition" class:bg-app-primary={data.tag===t.slug} class:text-app-pfg={data.tag===t.slug} class:bg-ink-100={data.tag!==t.slug} class:hover:bg-ink-200={data.tag!==t.slug}>#{t.slug}</a>
        {/each}
      </div>
    {/if}
  {/await}

  <div class="grid gap-8 lg:grid-cols-[270px_1fr]">
    <div class="lg:sticky lg:top-24 lg:h-fit">
      {#await data.streamed.filters}
        <div class="rounded-2xl border border-ink-100 bg-white p-4">
          <div class="h-5 w-28 bg-ink-100 rounded animate-pulse mb-4"></div>
          <div class="space-y-3">{#each Array(6) as _}<div class="h-10 bg-ink-100 rounded-xl animate-pulse"></div>{/each}</div>
        </div>
      {:then filters}
        <ProductFilterPanel tags={filters.tags} categories={filters.categories} />
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
