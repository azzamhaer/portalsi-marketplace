<script lang="ts">
  import { onMount } from 'svelte';
  import Icon from '$lib/components/Icon.svelte';

  type Area = { id: string; name: string };

  let {
    value = $bindable<any>({}),
    contact = true,
    title = '',
  } = $props<{ value: any; contact?: boolean; title?: string }>();

  const API = 'https://www.emsifa.com/api-wilayah-indonesia/api';

  let provinces = $state<Area[]>([]);
  let cities = $state<Area[]>([]);
  let districts = $state<Area[]>([]);
  let villages = $state<Area[]>([]);
  let loading = $state(false);
  let areaError = $state('');
  let provinceId = $state('');
  let cityId = $state('');
  let districtId = $state('');

  function patch(next: any) {
    value = { ...value, country: 'Indonesia', ...next };
  }

  async function getJson(path: string) {
    const res = await fetch(`${API}/${path}`);
    if (!res.ok) throw new Error('Gagal memuat wilayah');
    return res.json();
  }

  onMount(async () => {
    patch({ country: value.country || 'Indonesia' });
    loading = true;
    try {
      provinces = await getJson('provinces.json');
    } catch {
      areaError = 'Data wilayah otomatis belum bisa dimuat. Muat ulang halaman atau coba lagi beberapa saat.';
    } finally {
      loading = false;
    }
  });

  async function pickProvince(id: string) {
    provinceId = id;
    cityId = '';
    districtId = '';
    cities = [];
    districts = [];
    villages = [];
    const p = provinces.find((x) => x.id === id);
    patch({ province: p?.name || '', city: '', district: '', village: '', postal_code: value.postal_code || '' });
    if (!id) return;
    try { cities = await getJson(`regencies/${id}.json`); }
    catch { areaError = 'Kota/kabupaten gagal dimuat. Coba lagi atau isi manual.'; }
  }

  async function pickCity(id: string) {
    cityId = id;
    districtId = '';
    districts = [];
    villages = [];
    const c = cities.find((x) => x.id === id);
    patch({ city: c?.name || '', district: '', village: '' });
    if (!id) return;
    try { districts = await getJson(`districts/${id}.json`); }
    catch { areaError = 'Kecamatan gagal dimuat. Coba lagi atau isi manual.'; }
  }

  async function pickDistrict(id: string) {
    districtId = id;
    villages = [];
    const d = districts.find((x) => x.id === id);
    patch({ district: d?.name || '', village: '' });
    if (!id) return;
    try { villages = await getJson(`villages/${id}.json`); }
    catch { areaError = 'Kelurahan/desa gagal dimuat. Coba lagi atau isi manual.'; }
  }

  async function pickVillage(name: string) {
    patch({ village: name });
    if (!name || value.postal_code) return;
    try {
      const q = encodeURIComponent(`${name} ${value.district || ''} ${value.city || ''}`);
      const res = await fetch(`https://kodepos.vercel.app/search?q=${q}`);
      if (!res.ok) return;
      const json = await res.json();
      const code = json?.data?.[0]?.postalcode || json?.data?.[0]?.postal_code;
      if (code) patch({ postal_code: String(code) });
    } catch {
      // Kode pos tetap bisa diisi manual.
    }
  }
</script>

<div class="space-y-3">
  {#if title}
    <h3 class="font-semibold flex items-center gap-2"><Icon name="map-pin" size={16} /> {title}</h3>
  {/if}

  {#if contact}
    <div class="grid sm:grid-cols-2 gap-3">
      <div><label class="label">Nama penerima <span class="text-red-600">*</span></label><input class="input" bind:value={value.recipient} required /></div>
      <div><label class="label">Nomor telepon <span class="text-red-600">*</span></label><input class="input" bind:value={value.phone} placeholder="0812xxxxxxxx" required /></div>
    </div>
  {/if}

  <div class="grid sm:grid-cols-2 gap-3">
    <div>
      <label class="label">Negara <span class="text-red-600">*</span></label>
      <input class="input bg-ink-50" value="Indonesia" disabled />
    </div>
    <div>
      <label class="label">Provinsi <span class="text-red-600">*</span></label>
      <select class="input" bind:value={provinceId} on:change={(e) => pickProvince((e.currentTarget as HTMLSelectElement).value)} required>
        <option value="">{loading ? 'Memuat provinsi...' : value.province || 'Pilih provinsi'}</option>
        {#each provinces as p}<option value={p.id}>{p.name}</option>{/each}
      </select>
    </div>
  </div>

  <div class="grid sm:grid-cols-2 gap-3">
    <div>
      <label class="label">Kota/Kabupaten <span class="text-red-600">*</span></label>
      <select class="input" bind:value={cityId} on:change={(e) => pickCity((e.currentTarget as HTMLSelectElement).value)} disabled={!provinceId || (!!provinceId && !cities.length)} required>
        <option value="">{provinceId ? (value.city || 'Pilih kota/kabupaten') : 'Pilih provinsi dulu'}</option>
        {#each cities as c}<option value={c.id}>{c.name}</option>{/each}
      </select>
    </div>
    <div>
      <label class="label">Kecamatan <span class="text-red-600">*</span></label>
      <select class="input" bind:value={districtId} on:change={(e) => pickDistrict((e.currentTarget as HTMLSelectElement).value)} disabled={!cityId || (!!cityId && !districts.length)} required>
        <option value="">{cityId ? (value.district || 'Pilih kecamatan') : 'Pilih kota/kabupaten dulu'}</option>
        {#each districts as d}<option value={d.id}>{d.name}</option>{/each}
      </select>
    </div>
  </div>

  <div class="grid sm:grid-cols-[1fr_150px] gap-3">
    <div>
      <label class="label">Kelurahan/Desa <span class="text-red-600">*</span></label>
      <select class="input" value={value.village || ''} on:change={(e) => pickVillage((e.currentTarget as HTMLSelectElement).value)} disabled={!districtId || (!!districtId && !villages.length)} required>
        <option value="">{districtId ? (value.village || 'Pilih kelurahan/desa') : 'Pilih kecamatan dulu'}</option>
        {#each villages as v}<option value={v.name}>{v.name}</option>{/each}
      </select>
    </div>
    <div><label class="label">Kode pos <span class="text-red-600">*</span></label><input class="input" bind:value={value.postal_code} inputmode="numeric" required /></div>
  </div>

  <div><label class="label">Detail jalan, nomor rumah, RT/RW <span class="text-red-600">*</span></label><textarea class="input" rows={3} bind:value={value.full_address} required></textarea></div>
  <div><label class="label">Detail tambahan</label><textarea class="input" rows={2} bind:value={value.address_note} placeholder="Patokan rumah, catatan kurir, jam penerimaan, dan lainnya"></textarea></div>

  {#if areaError}
    <div class="rounded-xl bg-amber-50 px-3 py-2 text-xs text-amber-800">{areaError}</div>
  {/if}
</div>
