<script lang="ts">
  import ProductGrid from '$lib/components/ProductGrid.svelte';
  import Pagination from '$lib/components/Pagination.svelte';
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
      <h1 class="section-title">{data.tag ? `#${data.tag}` : 'Semua Produk'}</h1>
      <p class="text-sm text-ink-500 mt-1">{data.meta?.total ?? data.products.length} produk · halaman {data.meta?.current_page ?? 1} dari {data.meta?.last_page ?? 1}</p>
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

  {#if data.tags?.length}
    <div class="flex flex-wrap gap-2 mb-6 pb-6 border-b border-ink-100">
      {#each data.tags.slice(0, 20) as t}
        <a href={`/products?tag=${t.slug}`} class="text-xs px-3 py-1.5 rounded-full transition" class:bg-app-primary={data.tag===t.slug} class:text-app-pfg={data.tag===t.slug} class:bg-ink-100={data.tag!==t.slug} class:hover:bg-ink-200={data.tag!==t.slug}>#{t.slug}</a>
      {/each}
    </div>
  {/if}

  <ProductGrid products={data.products} />
  <Pagination current={data.meta?.current_page ?? 1} last={data.meta?.last_page ?? 1} />
</div>
