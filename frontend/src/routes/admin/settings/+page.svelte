<script lang="ts">
  import { onMount } from 'svelte';
  import { apiEndpoints } from '$lib/api';
  import { toast, settings as settingsStore } from '$lib/stores.svelte';
  import Icon from '$lib/components/Icon.svelte';

  let s = $state<any>(null);
  let saving = $state(false);

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

    <div class="flex justify-end">
      <button on:click={save} disabled={saving} class="btn-primary btn-lg">{saving ? 'Menyimpan…' : 'Simpan Pengaturan'}</button>
    </div>
  </div>
{/if}
