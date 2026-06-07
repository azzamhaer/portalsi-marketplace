<script lang="ts">
  import Icon from './Icon.svelte';
  let { lat = $bindable<number | null>(null), lng = $bindable<number | null>(null), height = '260px' } = $props();

  const url = $derived(
    lat != null && lng != null
      ? `https://www.openstreetmap.org/export/embed.html?bbox=${lng-0.01}%2C${lat-0.01}%2C${lng+0.01}%2C${lat+0.01}&layer=mapnik&marker=${lat}%2C${lng}`
      : `https://www.openstreetmap.org/export/embed.html?bbox=106.8%2C-6.3%2C107.0%2C-6.1&layer=mapnik`
  );
  const gmapsLink = $derived(lat != null && lng != null ? `https://www.google.com/maps?q=${lat},${lng}` : '');

  function getCurrent() {
    if (!navigator.geolocation) return alert('Geolocation tidak didukung browser');
    navigator.geolocation.getCurrentPosition(
      (p) => { lat = p.coords.latitude; lng = p.coords.longitude; },
      (e) => alert('Gagal: ' + e.message)
    );
  }

  function setManual() {
    const v = prompt('Tempel koordinat (format: lat,lng) atau Google Maps URL', lat && lng ? `${lat},${lng}` : '');
    if (!v) return;
    const m = v.match(/(-?\d+\.\d+)[\s,]+(-?\d+\.\d+)/);
    if (!m) return alert('Format salah');
    lat = parseFloat(m[1]); lng = parseFloat(m[2]);
  }
</script>

<div class="space-y-2">
  <div class="rounded-2xl overflow-hidden border border-ink-200" style:height>
    <iframe src={url} class="w-full h-full" loading="lazy" title="map"></iframe>
  </div>
  <div class="flex gap-2 flex-wrap items-center text-xs">
    <button type="button" on:click={getCurrent} class="btn-outline btn-sm"><Icon name="locate" size={12} /> Lokasi Saya</button>
    <button type="button" on:click={setManual} class="btn-outline btn-sm"><Icon name="map-pin" size={12} /> Set manual</button>
    {#if gmapsLink}
      <a href={gmapsLink} target="_blank" rel="noreferrer" class="text-ink-700 hover:text-ink-950 link-quiet">Buka di Google Maps</a>
      <span class="ml-auto text-ink-500 font-mono">{lat?.toFixed(6)}, {lng?.toFixed(6)}</span>
    {/if}
  </div>
</div>
