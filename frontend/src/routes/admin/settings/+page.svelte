<script lang="ts">
  import { onMount } from 'svelte';
  import { apiEndpoints, getToken } from '$lib/api';
  import { toast, settings as settingsStore } from '$lib/stores.svelte';
  import Icon from '$lib/components/Icon.svelte';

  let s = $state<any>(null);
  let palettes = $state<any[]>([]);
  let saving = $state(false);
  let logoFile = $state<File | null>(null);

  onMount(async () => {
    const data: any = await apiEndpoints.adminSettings();
    s = data; palettes = data.palettes;
  });

  async function save() {
    saving = true;
    try {
      await apiEndpoints.adminSaveSettings({
        app_name: s.app_name, palette: s.palette, primary_color: s.primary_color, primary_fg: s.primary_fg, accent_color: s.accent_color,
        tagline: s.tagline,
        tripay_mode: s.tripay_mode, tripay_api_key: s.tripay_api_key, tripay_private_key: s.tripay_private_key, tripay_merchant_code: s.tripay_merchant_code,
        commission_percent: Number(s.commission_percent),
        hero_title: s.hero_title, hero_subtitle: s.hero_subtitle,
        hero_cta_label: s.hero_cta_label, hero_cta_href: s.hero_cta_href,
        payment_intro: s.payment_intro, help_intro: s.help_intro,
      });
      const pub: any = await apiEndpoints.publicSettings();
      settingsStore.setAll(pub);
      toast.success('Pengaturan disimpan');
    } catch (e: any) { toast.error(e.message); } finally { saving = false; }
  }

  let heroFile = $state<File | null>(null);
  async function uploadHero() {
    if (!heroFile) return;
    const fd = new FormData();
    fd.append('hero', heroFile);
    try {
      const r: any = await apiEndpoints.adminUploadHero(fd);
      s.hero_image = r.hero_image;
      const pub: any = await apiEndpoints.publicSettings();
      settingsStore.setAll(pub);
      toast.success('Hero image diupload');
    } catch (e: any) { toast.error(e.message); }
  }

  function pickPalette(p: any) {
    s.palette = p.key; s.primary_color = p.primary; s.primary_fg = p.primaryFg; s.accent_color = p.accent;
  }

  async function uploadLogo() {
    if (!logoFile) return;
    const fd = new FormData();
    fd.append('logo', logoFile);
    try {
      const r: any = await apiEndpoints.adminUploadLogo(fd);
      s.logo_url = r.logo_url;
      const pub: any = await apiEndpoints.publicSettings();
      settingsStore.setAll(pub);
      toast.success('Logo diupload');
    } catch (e: any) { toast.error(e.message); }
  }
</script>

{#if !s}<div class="card text-center text-ink-500 py-10">Memuat…</div>
{:else}
  <div class="space-y-5">
    <div class="card">
      <h3 class="font-semibold mb-4">Branding</h3>
      <div class="grid sm:grid-cols-2 gap-4">
        <div><label class="label">Nama Aplikasi</label><input bind:value={s.app_name} class="input" /></div>
        <div><label class="label">Tagline</label><input bind:value={s.tagline} class="input" /></div>
      </div>
      <div class="mt-4">
        <label class="label">Logo Aplikasi</label>
        <div class="grid sm:grid-cols-[120px_1fr] gap-3 items-center">
          <div class="aspect-square w-24 rounded-2xl bg-ink-50 border border-ink-200 grid place-items-center overflow-hidden">
            {#if s.logo_url}<img src={s.logo_url} alt="" class="w-full h-full object-cover" />
            {:else}<Icon name="image" size={28} class="text-ink-300" />{/if}
          </div>
          <div>
            <input type="file" accept="image/*" on:change={(e: any) => logoFile = e.target.files?.[0] ?? null} class="text-sm mb-2" />
            <button on:click={uploadLogo} disabled={!logoFile} class="btn-outline btn-sm">Upload</button>
            <p class="helper">PNG/JPG/SVG, maks 1MB. Akan otomatis dipakai untuk navbar, footer, & favicon.</p>
          </div>
        </div>
      </div>
    </div>

    <div class="card">
      <h3 class="font-semibold mb-4">Color Palette</h3>
      <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
        {#each palettes as p}
          <button on:click={() => pickPalette(p)} class="text-left p-4 rounded-2xl border-2 transition" class:border-ink-950={s.palette===p.key} class:border-transparent={s.palette!==p.key}>
            <div class="flex gap-1 mb-2">
              <div class="w-8 h-8 rounded" style:background={p.primary}></div>
              <div class="w-8 h-8 rounded" style:background={p.accent}></div>
              <div class="w-8 h-8 rounded border border-ink-200" style:background={p.primaryFg}></div>
            </div>
            <div class="font-semibold text-sm">{p.name}</div>
            <div class="text-xs text-ink-500">{p.key}</div>
          </button>
        {/each}
      </div>
      <div class="grid sm:grid-cols-3 gap-3 mt-4">
        <div><label class="label">Primary</label><input type="color" bind:value={s.primary_color} class="w-full h-12 rounded-xl border border-ink-200" /></div>
        <div><label class="label">Foreground</label><input type="color" bind:value={s.primary_fg} class="w-full h-12 rounded-xl border border-ink-200" /></div>
        <div><label class="label">Accent</label><input type="color" bind:value={s.accent_color} class="w-full h-12 rounded-xl border border-ink-200" /></div>
      </div>
    </div>

    <div class="card">
      <h3 class="font-semibold mb-4">Halaman Beranda — Hero Section</h3>
      <div class="grid sm:grid-cols-2 gap-4">
        <div><label class="label">Judul Hero</label><input bind:value={s.hero_title} class="input" /></div>
        <div><label class="label">Subjudul Hero</label><input bind:value={s.hero_subtitle} class="input" /></div>
        <div><label class="label">Teks tombol CTA</label><input bind:value={s.hero_cta_label} class="input" /></div>
        <div><label class="label">Link tombol CTA</label><input bind:value={s.hero_cta_href} class="input" placeholder="/products" /></div>
      </div>
      <div class="mt-4">
        <label class="label">Gambar Hero (opsional)</label>
        <div class="grid sm:grid-cols-[180px_1fr] gap-3 items-center">
          <div class="aspect-[4/3] rounded-2xl bg-ink-50 border border-ink-200 grid place-items-center overflow-hidden">
            {#if s.hero_image}<img src={s.hero_image} alt="" class="w-full h-full object-cover" />
            {:else}<Icon name="image" size={28} class="text-ink-300" />{/if}
          </div>
          <div>
            <input type="file" accept="image/*" on:change={(e: any) => heroFile = e.target.files?.[0] ?? null} class="text-sm mb-2" />
            <button on:click={uploadHero} disabled={!heroFile} class="btn-outline btn-sm">Upload Hero</button>
            {#if s.hero_image}<button type="button" on:click={() => s.hero_image = ''} class="btn-sm bg-red-50 text-red-700 hover:bg-red-100 ml-2">Hapus</button>{/if}
            <p class="helper">JPG/PNG, maks 4MB. Tampil di halaman beranda. Kalau kosong, default ke pola gradient.</p>
          </div>
        </div>
      </div>
    </div>

    <div class="card">
      <h3 class="font-semibold mb-4">Halaman Pembayaran — Intro</h3>
      <p class="text-xs text-ink-500 mb-2">Teks tambahan di atas daftar metode pembayaran. Kosongkan untuk tidak tampil.</p>
      <textarea bind:value={s.payment_intro} class="input" rows={4} placeholder="Contoh: Semua metode pembayaran kami diamankan dengan enkripsi end-to-end..."></textarea>
    </div>

    <div class="card">
      <h3 class="font-semibold mb-4">Halaman Bantuan — Intro</h3>
      <p class="text-xs text-ink-500 mb-2">Teks tambahan di atas section FAQ. Kosongkan untuk tidak tampil.</p>
      <textarea bind:value={s.help_intro} class="input" rows={4} placeholder="Contoh: Butuh bantuan? Tim CS kami online 24/7..."></textarea>
    </div>

    <div class="card">
      <h3 class="font-semibold mb-4">Komisi Platform</h3>
      <p class="text-xs text-ink-500 mb-4">Persen yang dipotong dari setiap pesanan selesai sebelum dana bisa ditarik oleh seller.</p>
      <div class="grid sm:grid-cols-[200px_1fr] gap-3 items-center">
        <div>
          <label class="label">Persen (0–50%)</label>
          <div class="flex items-center gap-2">
            <input type="number" min="0" max="50" step="0.5" bind:value={s.commission_percent} class="input" />
            <span class="font-semibold">%</span>
          </div>
        </div>
        <div class="bg-ink-50 p-3 rounded-xl text-xs text-ink-600">
          Contoh: pesanan Rp 100.000 dengan komisi {s.commission_percent ?? 5}% berarti seller mendapat
          <span class="font-bold text-ink-950">Rp {(100000 - 100000 * (Number(s.commission_percent ?? 5)) / 100).toLocaleString('id-ID')}</span>
          ke saldo penarikan.
        </div>
      </div>
    </div>

    <div class="card">
      <h3 class="font-semibold mb-4">Tripay Payment Gateway</h3>
      <p class="text-xs text-ink-500 mb-4">Settings ini override .env. Kosongkan untuk pakai .env. Daftar credentials di <a href="https://tripay.co.id/member/merchant" target="_blank" class="link">tripay.co.id</a>.</p>
      <div class="grid sm:grid-cols-2 gap-4">
        <div><label class="label">Mode</label>
          <select bind:value={s.tripay_mode} class="input">
            <option value="sandbox">Sandbox (testing)</option>
            <option value="production">Production (live)</option>
          </select>
        </div>
        <div><label class="label">Merchant Code</label><input bind:value={s.tripay_merchant_code} class="input" placeholder="T0001" /></div>
        <div><label class="label">API Key</label><input bind:value={s.tripay_api_key} class="input font-mono text-xs" /></div>
        <div><label class="label">Private Key</label><input type="password" bind:value={s.tripay_private_key} class="input font-mono text-xs" /></div>
      </div>
    </div>

    <div class="flex gap-2">
      <button on:click={save} disabled={saving} class="btn-primary btn-lg">{saving ? 'Menyimpan…' : 'Simpan Pengaturan'}</button>
    </div>
  </div>
{/if}
