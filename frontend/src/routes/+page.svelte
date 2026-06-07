<script lang="ts">
  import Hero from '$lib/components/Hero.svelte';
  import ProductGrid from '$lib/components/ProductGrid.svelte';
  import Icon from '$lib/components/Icon.svelte';
  let { data } = $props();
</script>

<svelte:head><title>Beranda</title></svelte:head>

<div class="container-x py-6 md:py-10 space-y-12 md:space-y-20">
  <Hero />

  <!-- Tags -->
  {#if data.tags?.length}
    <section>
      <div class="flex items-end justify-between mb-6">
        <div>
          <div class="section-eyebrow mb-2">Jelajahi</div>
          <h2 class="section-title">Tag populer</h2>
        </div>
        <a href="/products" class="hidden sm:flex items-center gap-1 text-sm text-ink-700 hover:text-ink-950">Semua produk <Icon name="arrow-right" size={14} /></a>
      </div>
      <div class="flex flex-wrap gap-2">
        {#each data.tags as t}
          <a href={`/products?tag=${t.slug}`} class="px-4 py-2 rounded-full bg-ink-100 hover:bg-app-primary hover:text-app-pfg text-sm transition-colors">
            #{t.slug} <span class="text-xs opacity-60">({t.count})</span>
          </a>
        {/each}
      </div>
    </section>
  {/if}

  <!-- Flash Sale -->
  {#if data.flashSale?.length}
    <section>
      <div class="flex items-end justify-between mb-6 sm:mb-8">
        <div>
          <div class="section-eyebrow text-amber-600 mb-2 flex items-center gap-2">
            <Icon name="sparkles" size={12} /> Penawaran Terbatas
          </div>
          <h2 class="section-title">Penawaran terbaik hari ini</h2>
        </div>
        <a href="/products?flash=1" class="hidden sm:flex items-center gap-1 text-sm text-ink-700 hover:text-ink-950">Lihat semua <Icon name="arrow-right" size={14} /></a>
      </div>
      <ProductGrid products={data.flashSale} />
    </section>
  {/if}

  <!-- Official Stores -->
  {#if data.official?.length}
    <section>
      <div class="flex items-end justify-between mb-6 sm:mb-8">
        <div>
          <div class="section-eyebrow mb-2">Toko Resmi</div>
          <h2 class="section-title">Brand pilihan, terverifikasi</h2>
        </div>
        <a href="/vendors?f=official" class="hidden sm:flex items-center gap-1 text-sm text-ink-700 hover:text-ink-950">Lihat semua <Icon name="arrow-right" size={14} /></a>
      </div>
      <div class="grid grid-cols-3 sm:grid-cols-4 lg:grid-cols-6 gap-3 sm:gap-4">
        {#each data.official as v (v.id)}
          <a href={v.username ? `/${v.username}` : `/vendors/${v.id}`} class="group block">
            <div class="aspect-square rounded-2xl bg-ink-100 overflow-hidden mb-2 group-hover:scale-[1.02] transition-transform">
              <img src={v.avatar} alt={v.name} class="w-full h-full object-cover" />
            </div>
            <div class="font-medium text-sm text-ink-900 truncate">{v.name}</div>
            <div class="text-xs text-ink-500">{v.city}</div>
          </a>
        {/each}
      </div>
    </section>
  {/if}

  <!-- Recommendations -->
  {#if data.recommended?.length}
    <section>
      <div class="flex items-end justify-between mb-6 sm:mb-8">
        <div>
          <div class="section-eyebrow mb-2">Rekomendasi</div>
          <h2 class="section-title">Produk pilihan untuk Anda</h2>
        </div>
      </div>
      <ProductGrid products={data.recommended} />
    </section>
  {/if}

  <!-- Trust strip -->
  <section class="border-t border-ink-100 pt-12 sm:pt-16">
    <div class="grid sm:grid-cols-3 gap-8 text-center">
      {#each [{ i:'shield', t:'Pembayaran terjamin', d:'Dana ditahan hingga barang Anda terima.' },
              { i:'truck', t:'Pengiriman cepat', d:'6 kurir resmi, tracking real-time.' },
              { i:'headphones', t:'Dukungan 24/7', d:'Tim siap membantu kapan saja.' }] as item}
        <div>
          <div class="w-12 h-12 mx-auto mb-3 rounded-2xl bg-ink-100 grid place-items-center">
            <Icon name={item.i} size={20} class="text-ink-950" />
          </div>
          <h3 class="font-semibold mb-1">{item.t}</h3>
          <p class="text-sm text-ink-500">{item.d}</p>
        </div>
      {/each}    </div>
  </section>
</div>
