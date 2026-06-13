<script lang="ts">
  import { onMount } from 'svelte';
  import Icon from '$lib/components/Icon.svelte';
  import { apiEndpoints } from '$lib/api';
  import { toast, confirmDialog } from '$lib/stores.svelte';
  import { fmtRp } from '$lib/utils';

  let vouchers = $state<any[]>([]);
  let products = $state<any[]>([]);
  let loading = $state(true);
  let saving = $state(false);
  let editingId = $state<number | null>(null);
  let form = $state<any>({
    code: '',
    type: 'FIXED',
    value: 10000,
    min_subtotal: 0,
    max_discount: '',
    usage_limit: '',
    is_active: true,
    product_ids: [],
  });

  async function load() {
    loading = true;
    try {
      const [vs, ps]: any[] = await Promise.all([apiEndpoints.sellerVouchers(), apiEndpoints.sellerProducts()]);
      vouchers = vs;
      products = ps;
    } catch (e: any) {
      toast.error(e.message);
    } finally {
      loading = false;
    }
  }

  onMount(load);

  function reset() {
    editingId = null;
    form = { code: '', type: 'FIXED', value: 10000, min_subtotal: 0, max_discount: '', usage_limit: '', is_active: true, product_ids: [] };
  }

  function edit(v: any) {
    editingId = v.id;
    form = {
      code: v.code,
      type: v.type,
      value: v.value,
      min_subtotal: v.min_subtotal ?? 0,
      max_discount: v.max_discount ?? '',
      usage_limit: v.usage_limit ?? '',
      is_active: v.is_active,
      product_ids: (v.products ?? []).map((p: any) => p.id),
    };
  }

  function toggleProduct(id: number) {
    form.product_ids = form.product_ids.includes(id)
      ? form.product_ids.filter((x: number) => x !== id)
      : [...form.product_ids, id];
  }

  async function save() {
    if (!form.code.trim()) return toast.warn('Kode voucher wajib diisi');
    saving = true;
    try {
      const body = {
        ...form,
        code: form.code.toUpperCase(),
        value: Number(form.value),
        min_subtotal: Number(form.min_subtotal || 0),
        max_discount: form.max_discount === '' ? null : Number(form.max_discount),
        usage_limit: form.usage_limit === '' ? null : Number(form.usage_limit),
      };
      if (editingId) await apiEndpoints.sellerUpdateVoucher(editingId, body);
      else await apiEndpoints.sellerCreateVoucher(body);
      toast.success(editingId ? 'Voucher diperbarui' : 'Voucher dibuat');
      reset();
      await load();
    } catch (e: any) {
      toast.error(e.message);
    } finally {
      saving = false;
    }
  }

  async function remove(id: number) {
    const ok = await confirmDialog.ask({ title: 'Hapus voucher?', message: 'Voucher ini tidak bisa dipakai lagi setelah dihapus.', confirmText: 'Hapus', tone: 'danger' });
    if (!ok) return;
    try {
      await apiEndpoints.sellerDeleteVoucher(id);
      toast.success('Voucher dihapus');
      await load();
    } catch (e: any) {
      toast.error(e.message);
    }
  }
</script>

<svelte:head><title>Voucher Seller</title></svelte:head>

<div class="space-y-6">
  <div>
    <div class="section-eyebrow mb-2">Promo</div>
    <h1 class="section-title">Voucher Toko</h1>
    <p class="mt-1 text-sm text-ink-500">Buat kode diskon untuk produk tertentu atau semua produk toko.</p>
  </div>

  {#if loading}
    <div class="card py-10 text-center text-ink-500">Memuat...</div>
  {:else}
    <div class="grid gap-5 lg:grid-cols-[360px_1fr]">
      <div class="card h-fit">
        <h2 class="mb-4 font-semibold">{editingId ? 'Edit Voucher' : 'Buat Voucher'}</h2>
        <div class="space-y-3">
          <div><label class="label">Kode</label><input bind:value={form.code} class="input uppercase" placeholder="HEMAT10" /></div>
          <div class="grid grid-cols-2 gap-2">
            <div>
              <label class="label">Tipe</label>
              <select bind:value={form.type} class="input">
                <option value="FIXED">Potongan Rp</option>
                <option value="PERCENT">Persen</option>
              </select>
            </div>
            <div><label class="label">Nilai</label><input type="number" min="1" bind:value={form.value} class="input" /></div>
          </div>
          <div><label class="label">Minimum subtotal item</label><input type="number" min="0" bind:value={form.min_subtotal} class="input" /></div>
          <div class="grid grid-cols-2 gap-2">
            <div><label class="label">Maks diskon</label><input type="number" min="0" bind:value={form.max_discount} class="input" placeholder="Opsional" /></div>
            <div><label class="label">Limit pakai</label><input type="number" min="1" bind:value={form.usage_limit} class="input" placeholder="Opsional" /></div>
          </div>
          <label class="flex items-center gap-2 text-sm"><input type="checkbox" bind:checked={form.is_active} /> Aktif</label>

          <div>
            <label class="label">Produk berlaku</label>
            <p class="helper mb-2">Kosongkan pilihan untuk berlaku di semua produk toko.</p>
            <div class="max-h-56 space-y-1 overflow-y-auto rounded-2xl border border-ink-100 p-2">
              {#each products as p}
                <label class="flex cursor-pointer items-center gap-2 rounded-xl p-2 text-sm hover:bg-ink-50">
                  <input type="checkbox" checked={form.product_ids.includes(p.id)} on:change={() => toggleProduct(p.id)} />
                  <img src={p.image} alt="" class="h-8 w-8 rounded-lg object-cover" />
                  <span class="line-clamp-1">{p.name}</span>
                </label>
              {/each}
            </div>
          </div>

          <div class="flex gap-2">
            <button type="button" on:click={save} disabled={saving} class="btn-primary btn-sm">{saving ? 'Menyimpan...' : 'Simpan'}</button>
            {#if editingId}<button type="button" on:click={reset} class="btn-outline btn-sm">Batal</button>{/if}
          </div>
        </div>
      </div>

      <div class="space-y-3">
        {#if vouchers.length === 0}
          <div class="card py-12 text-center text-ink-500">Belum ada voucher.</div>
        {:else}
          {#each vouchers as v}
            <div class="card">
              <div class="flex items-start justify-between gap-3">
                <div>
                  <div class="flex flex-wrap items-center gap-2">
                    <span class="rounded-xl bg-app-primary px-3 py-1.5 text-sm font-bold text-app-pfg">{v.code}</span>
                    <span class={v.is_active ? 'pill-green' : 'pill-red'}>{v.is_active ? 'Aktif' : 'Nonaktif'}</span>
                  </div>
                  <div class="mt-2 text-sm text-ink-700">
                    {v.type === 'PERCENT' ? `${v.value}%` : fmtRp(v.value)}
                    {#if v.max_discount} · maks {fmtRp(v.max_discount)}{/if}
                    {#if v.min_subtotal} · min {fmtRp(v.min_subtotal)}{/if}
                  </div>
                  <div class="mt-1 text-xs text-ink-500">Dipakai {v.used_count ?? 0}{#if v.usage_limit} / {v.usage_limit}{/if} kali</div>
                  <div class="mt-3 flex flex-wrap gap-1.5">
                    {#if v.products?.length}
                      {#each v.products.slice(0, 6) as p}<span class="rounded-full bg-ink-100 px-2 py-1 text-[11px]">#{p.name}</span>{/each}
                    {:else}
                      <span class="rounded-full bg-ink-100 px-2 py-1 text-[11px]">Semua produk</span>
                    {/if}
                  </div>
                </div>
                <div class="flex gap-1">
                  <button type="button" on:click={() => edit(v)} class="btn-outline btn-sm"><Icon name="pencil" size={12} /></button>
                  <button type="button" on:click={() => remove(v.id)} class="btn-sm bg-red-50 text-red-700 hover:bg-red-100"><Icon name="trash-2" size={12} /></button>
                </div>
              </div>
            </div>
          {/each}
        {/if}
      </div>
    </div>
  {/if}
</div>
