<script lang="ts">
  import ProductGrid from '$lib/components/ProductGrid.svelte';
  import { goto } from '$app/navigation';
  import { page } from '$app/stores';
  import Icon from '$lib/components/Icon.svelte';
  let { data } = $props();
  function setSort(v: string) {
    const u = new URL($page.url);
    u.searchParams.set('sort', v);
    goto(u.pathname + '?' + u.searchParams.toString());
  }
</script>

<svelte:head><title>"{data.q}" — MPSI</title></svelte:head>

<div class="container-x py-8">
  <div class="flex items-end justify-between mb-8 gap-4 flex-wrap">
    <div>
      <div class="section-eyebrow mb-2 flex items-center gap-2"><Icon name="search" size={12} /> Pencarian</div>
      <h1 class="section-title">Hasil untuk "<span class="italic">{data.q}</span>"</h1>
      <p class="text-sm text-ink-500 mt-1">{data.products.length} produk ditemukan</p>
    </div>
    {#if data.products.length}
      <select on:change={(e: any) => setSort(e.target.value)} value={data.sort} class="input-sm input w-full sm:w-56">
        <option value="popular">Terpopuler</option>
        <option value="cheap">Termurah</option>
        <option value="exp">Termahal</option>
        <option value="rating">Rating Tertinggi</option>
      </select>
    {/if}
  </div>
  <ProductGrid products={data.products} />
</div>
