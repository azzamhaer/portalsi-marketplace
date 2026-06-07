<script lang="ts">
  import Icon from '$lib/components/Icon.svelte';
  import VendorBadge from '$lib/components/VendorBadge.svelte';
  let { data } = $props();
  const filters = [
    { v:'all',      l:'Semua' },
    { v:'official', l:'Resmi' },
    { v:'rating',   l:'Rating Tertinggi' },
    { v:'sold',     l:'Penjualan Terbanyak' }
  ];
</script>

<svelte:head><title>Daftar Toko — MPSI</title></svelte:head>

<div class="container-x py-8">
  <div class="section-eyebrow mb-2">Toko</div>
  <h1 class="section-title mb-2">Penjual tepercaya kami</h1>
  <p class="text-ink-500 mb-8">{data.vendors.length} toko terdaftar</p>

  <div class="flex gap-2 mb-8 flex-wrap">
    {#each filters as f}
      <a href={`/vendors?f=${f.v}`} class="btn-sm" class:btn-primary={f.v===data.filter} class:btn-outline={f.v!==data.filter}>{f.l}</a>
    {/each}
  </div>

  <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
    {#each data.vendors as v (v.id)}
      <a href={v.username ? `/${v.username}` : `/vendors/${v.id}`} class="card-hover group">
        <div class="flex items-center gap-4">
          <img src={v.avatar} alt="" class="w-16 h-16 rounded-full object-cover" />
          <div class="flex-1 min-w-0">
            <div class="flex items-center gap-1.5">
              <h3 class="font-semibold truncate">{v.name}</h3>
              {#if v.badge}<VendorBadge badge={v.badge} size={14} />{/if}
              {#if v.is_official}<span class="pill-ink text-[10px]">RESMI</span>{/if}
            </div>
            <div class="text-xs text-ink-500 mt-0.5">{v.city}</div>
            <div class="text-xs text-ink-700 mt-1 flex items-center gap-3">
              <span class="flex items-center gap-1"><Icon name="star" size={11} class="text-amber-400" fill="currentColor" /> {v.rating}</span>
              <span>{v.total_sold.toLocaleString('id-ID')} terjual</span>
            </div>
          </div>
        </div>
      </a>
    {/each}
  </div>
</div>
