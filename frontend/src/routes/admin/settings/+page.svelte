<script lang="ts">
  import { onMount } from 'svelte';
  import { apiEndpoints } from '$lib/api';
  import { toast, settings as settingsStore, confirmDialog } from '$lib/stores.svelte';
  import Icon from '$lib/components/Icon.svelte';

  let s = $state<any>(null);
  let saving = $state(false);
  let freshSummary = $state<any>(null);
  let freshLoading = $state(false);
  let freshRunning = $state(false);
  let freshPassword = $state('');

  onMount(async () => {
    s = await apiEndpoints.adminSettings();
  });

  async function save() {
    saving = true;
    try {
      await apiEndpoints.adminSaveSettings({
        tripay_mode: s.tripay_mode,
        tripay_api_key: s.tripay_api_key,
        tripay_private_key: s.tripay_private_key,
        tripay_merchant_code: s.tripay_merchant_code,
        rajaongkir_mode: s.rajaongkir_mode,
        rajaongkir_api_key: s.rajaongkir_api_key,
        rajaongkir_tariff_base_url: s.rajaongkir_tariff_base_url,
        rajaongkir_order_base_url: s.rajaongkir_order_base_url,
        commission_percent: Number(s.commission_percent),
        brevo_api_key: s.brevo_api_key,
        brevo_sender_email: s.brevo_sender_email,
        brevo_sender_name: s.brevo_sender_name,
      });
      const pub: any = await apiEndpoints.publicSettings();
      settingsStore.setAll(pub);
      toast.success('Pengaturan disimpan');
    } catch (e: any) { toast.error(e.message); } finally { saving = false; }
  }

  async function loadFreshSummary() {
    freshLoading = true;
    try {
      freshSummary = await apiEndpoints.adminFreshStartSummary();
    } catch (e: any) {
      toast.error(e.message || 'Gagal memuat ringkasan migrate fresh');
    } finally {
      freshLoading = false;
    }
  }

  async function runMigrateFresh() {
    if (!freshPassword) { toast.warn('Masukkan password admin'); return; }
    const ok = await confirmDialog.ask({
      title: 'Jalankan Migrate Fresh?',
      message: 'Aksi ini menghapus seluruh data transaksi/testing non-konfigurasi. Admin, settings, kategori, tag, FAQ, kurir, dan payment method tetap disimpan.',
      confirmText: 'Migrate Fresh',
      tone: 'danger',
    });
    if (!ok) return;
    freshRunning = true;
    try {
      await apiEndpoints.adminFreshStart(freshPassword);
      freshPassword = '';
      toast.success('Migrate fresh selesai');
      freshSummary = await apiEndpoints.adminFreshStartSummary();
    } catch (e: any) {
      toast.error(e.message || 'Migrate fresh gagal');
    } finally {
      freshRunning = false;
    }
  }
</script>

{#if !s}<div class="card text-center text-ink-500 py-10">Memuat…</div>
{:else}
  <div class="space-y-5">
    <div class="card bg-sky-50 text-sky-800 text-xs p-3 rounded-xl flex items-start gap-2">
      <Icon name="info" size={14} class="mt-0.5 shrink-0" />
      <div>
        Halaman ini untuk konfigurasi sistem (komisi, payment gateway, email). Untuk mengubah tampilan UI, logo, hero, FAQ, atau metode pembayaran yang ditampilkan, buka <a href="/admin/appearance" class="underline font-semibold">Tampilan</a>.
      </div>
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
      <h3 class="font-semibold mb-4 flex items-center gap-2"><Icon name="mail" size={18} /> Email Notifikasi (Brevo)</h3>
      <p class="text-xs text-ink-500 mb-4">
        Konfigurasi <b>Brevo</b> (formerly Sendinblue) untuk mengirim email transaksional otomatis:
        email selamat datang saat daftar, konfirmasi pesanan, update status, reset password, dll.
        Daftar gratis di <a href="https://app.brevo.com" target="_blank" class="link">brevo.com</a>.
        Kalau dikosongkan, email tidak terkirim — sistem tetap jalan.
      </p>
      <div class="mb-4 rounded-xl bg-amber-50 px-3 py-2 text-xs text-amber-800">
        Agar email tidak mudah masuk Spam, pastikan domain pengirim sudah diverifikasi di Brevo dan DNS SPF, DKIM, serta DMARC sudah aktif.
      </div>
      <div class="grid sm:grid-cols-2 gap-3">
        <div class="sm:col-span-2">
          <label class="label">API Key</label>
          <input type="password" bind:value={s.brevo_api_key} class="input font-mono text-xs" placeholder="xkeysib-..." />
        </div>
        <div>
          <label class="label">Sender Email</label>
          <input bind:value={s.brevo_sender_email} class="input" placeholder="noreply@yourdomain.com" />
        </div>
        <div>
          <label class="label">Sender Name</label>
          <input bind:value={s.brevo_sender_name} class="input" placeholder="MPSI" />
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

    <div class="card">
      <h3 class="font-semibold mb-4">RajaOngkir / Komerce Shipping</h3>
      <p class="text-xs text-ink-500 mb-4">API ini dipakai untuk cari destinasi, hitung ongkir live saat checkout, dan mencoba membuat data order/AWB saat seller mengirim pesanan.</p>
      <div class="grid sm:grid-cols-2 gap-4">
        <div>
          <label class="label">Mode</label>
          <select bind:value={s.rajaongkir_mode} class="input">
            <option value="sandbox">Sandbox</option>
            <option value="production">Production</option>
          </select>
        </div>
        <div><label class="label">API Key</label><input type="password" bind:value={s.rajaongkir_api_key} class="input font-mono text-xs" /></div>
        <div><label class="label">Tariff Base URL (opsional)</label><input bind:value={s.rajaongkir_tariff_base_url} class="input font-mono text-xs" placeholder="default otomatis sesuai mode" /></div>
        <div><label class="label">Order Base URL (opsional)</label><input bind:value={s.rajaongkir_order_base_url} class="input font-mono text-xs" placeholder="default otomatis sesuai mode" /></div>
      </div>
    </div>

    <div class="card border-red-100 bg-red-50/40">
      <div class="flex items-start gap-3">
        <div class="grid h-10 w-10 place-items-center rounded-xl bg-red-100 text-red-700">
          <Icon name="database-zap" size={18} />
        </div>
        <div class="min-w-0 flex-1">
          <h3 class="font-semibold text-red-900">Migrate Fresh</h3>
          <p class="mt-1 text-sm text-red-800/80">
            Membersihkan data testing seperti user non-admin, vendor, produk, order, chat, laporan, wishlist, review, voucher, withdrawal, dan notifikasi. Masukkan password admin untuk menjalankan.
          </p>
          {#if freshSummary}
            <div class="mt-4 grid grid-cols-2 sm:grid-cols-4 gap-2 text-xs">
              <div class="rounded-xl bg-white p-3"><b>{freshSummary.users_removed}</b><br />User non-admin</div>
              <div class="rounded-xl bg-white p-3"><b>{freshSummary.vendors}</b><br />Vendor</div>
              <div class="rounded-xl bg-white p-3"><b>{freshSummary.products}</b><br />Produk</div>
              <div class="rounded-xl bg-white p-3"><b>{freshSummary.orders}</b><br />Pesanan</div>
              <div class="rounded-xl bg-white p-3"><b>{freshSummary.chats}</b><br />Chat thread</div>
              <div class="rounded-xl bg-white p-3"><b>{freshSummary.reports}</b><br />Laporan</div>
              <div class="rounded-xl bg-white p-3"><b>{freshSummary.notifications}</b><br />Notifikasi</div>
              <div class="rounded-xl bg-white p-3"><b>{freshSummary.admins_kept}</b><br />Admin tetap</div>
            </div>
          {/if}
          <div class="mt-4 grid gap-2 sm:grid-cols-[1fr_auto_auto]">
            <input type="password" bind:value={freshPassword} class="input bg-white" placeholder="Password admin" />
            <button type="button" on:click={loadFreshSummary} disabled={freshLoading} class="btn-outline btn-sm bg-white">
              <Icon name="list-checks" size={13} /> {freshLoading ? 'Memuat...' : 'Ringkasan'}
            </button>
            <button type="button" on:click={runMigrateFresh} disabled={freshRunning || !freshPassword} class="btn-danger btn-sm">
              <Icon name="trash-2" size={13} /> {freshRunning ? 'Memproses...' : 'Migrate Fresh'}
            </button>
          </div>
        </div>
      </div>
    </div>

    <div class="flex justify-end">
      <button on:click={save} disabled={saving} class="btn-primary btn-lg">{saving ? 'Menyimpan…' : 'Simpan Pengaturan'}</button>
    </div>
  </div>
{/if}
