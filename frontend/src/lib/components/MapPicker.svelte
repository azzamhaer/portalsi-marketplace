<script lang="ts">
  import { onMount, onDestroy } from 'svelte';
  import Icon from './Icon.svelte';

  let {
    lat = $bindable<number | null>(null),
    lng = $bindable<number | null>(null),
    height = '300px',
    query = '',
  } = $props<{ lat: number | null; lng: number | null; height?: string; query?: string }>();

  let mapEl: HTMLDivElement | null = $state(null);
  let map: any = null;
  let L: any = null;
  let ready = $state(false);
  let searching = $state(false);
  let mapError = $state('');
  let lastQuery = $state('');
  const fallback = { lat: -6.2, lng: 106.816666 };

  const gmapsLink = $derived(lat != null && lng != null ? `https://www.google.com/maps?q=${lat},${lng}` : '');

  function loadScript(src: string) {
    return new Promise<void>((resolve, reject) => {
      if (document.querySelector(`script[src="${src}"]`)) return resolve();
      const s = document.createElement('script');
      s.src = src;
      s.onload = () => resolve();
      s.onerror = () => reject(new Error('Gagal memuat map'));
      document.head.appendChild(s);
    });
  }

  function loadCss(href: string) {
    if (document.querySelector(`link[href="${href}"]`)) return;
    const l = document.createElement('link');
    l.rel = 'stylesheet';
    l.href = href;
    document.head.appendChild(l);
  }

  async function initMap() {
    if (!mapEl) return;
    loadCss('https://unpkg.com/leaflet@1.9.4/dist/leaflet.css');
    await loadScript('https://unpkg.com/leaflet@1.9.4/dist/leaflet.js');
    L = (window as any).L;
    const start = [lat ?? fallback.lat, lng ?? fallback.lng];
    map = L.map(mapEl, { zoomControl: true, attributionControl: true }).setView(start, lat && lng ? 16 : 12);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      maxZoom: 19,
      attribution: '&copy; OpenStreetMap',
    }).addTo(map);
    map.on('moveend', () => {
      const c = map.getCenter();
      lat = Number(c.lat.toFixed(7));
      lng = Number(c.lng.toFixed(7));
    });
    ready = true;
    if ((!lat || !lng) && query) void searchAddress();
  }

  async function searchAddress() {
    const q = query.trim();
    if (!q || q === lastQuery) return;
    lastQuery = q;
    searching = true;
    mapError = '';
    try {
      const url = `https://nominatim.openstreetmap.org/search?format=json&limit=1&countrycodes=id&q=${encodeURIComponent(q)}`;
      const res = await fetch(url, { headers: { Accept: 'application/json' } });
      if (!res.ok) throw new Error('Geocode gagal');
      const rows = await res.json();
      if (!rows?.[0]) return;
      const nextLat = Number(rows[0].lat);
      const nextLng = Number(rows[0].lon);
      lat = nextLat;
      lng = nextLng;
      map?.setView([nextLat, nextLng], 16);
    } catch {
      mapError = 'Lokasi belum bisa dicari otomatis. Geser peta ke titik yang sesuai.';
    } finally {
      searching = false;
    }
  }

  function getCurrent() {
    if (!navigator.geolocation) return alert('Geolocation tidak didukung browser');
    navigator.geolocation.getCurrentPosition(
      (p) => {
        lat = Number(p.coords.latitude.toFixed(7));
        lng = Number(p.coords.longitude.toFixed(7));
        map?.setView([lat, lng], 17);
      },
      (e) => alert('Gagal: ' + e.message)
    );
  }

  onMount(() => {
    initMap().catch(() => {
      mapError = 'Map gagal dimuat. Periksa koneksi internet lalu muat ulang halaman.';
    });
  });

  onDestroy(() => {
    map?.remove();
    map = null;
  });

  $effect(() => {
    if (ready && lat != null && lng != null) {
      const c = map.getCenter();
      if (Math.abs(c.lat - lat) > 0.00001 || Math.abs(c.lng - lng) > 0.00001) {
        map.setView([lat, lng], map.getZoom() || 16);
      }
    }
  });

  $effect(() => {
    if (ready && query) void searchAddress();
  });
</script>

<div class="space-y-2">
  <div class="relative overflow-hidden rounded-2xl border border-ink-200 bg-ink-50" style:height>
    <div bind:this={mapEl} class="h-full w-full"></div>
    <div class="pointer-events-none absolute inset-0 grid place-items-center">
      <div class="-mt-8 grid place-items-center text-red-600 drop-shadow">
        <Icon name="map-pin" size={38} fill="currentColor" />
      </div>
    </div>
    <div class="pointer-events-none absolute bottom-2 left-2 rounded-full bg-white/90 px-2.5 py-1 text-[11px] text-ink-600 shadow-soft">
      Geser peta, pin tengah menjadi titik alamat
    </div>
    {#if searching}
      <div class="absolute right-2 top-2 rounded-full bg-white/95 px-2.5 py-1 text-[11px] text-ink-600 shadow-soft">Mencari lokasi...</div>
    {/if}
  </div>
  <div class="flex gap-2 flex-wrap items-center text-xs">
    <button type="button" on:click={getCurrent} class="btn-outline btn-sm"><Icon name="locate" size={12} /> Lokasi Saya</button>
    {#if query}
      <button type="button" on:click={() => { lastQuery = ''; searchAddress(); }} class="btn-outline btn-sm"><Icon name="search" size={12} /> Cari dari alamat</button>
    {/if}
    {#if gmapsLink}
      <a href={gmapsLink} target="_blank" rel="noreferrer" class="text-ink-700 hover:text-ink-950 link-quiet">Buka di Google Maps</a>
      <span class="ml-auto text-ink-500 font-mono">{lat?.toFixed(6)}, {lng?.toFixed(6)}</span>
    {/if}
  </div>
  {#if mapError}
    <div class="rounded-xl bg-amber-50 px-3 py-2 text-xs text-amber-800">{mapError}</div>
  {/if}
</div>
