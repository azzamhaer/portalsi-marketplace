<script lang="ts">
  import Icon from './Icon.svelte';
  import { toast } from '$lib/stores.svelte';
  import { apiEndpoints } from '$lib/api';
  import { goto } from '$app/navigation';

  let { mode = 'create', product = null }: { mode?: 'create'|'edit'; product?: any } = $props();

  let name = $state(product?.name ?? '');
  let description = $state(product?.description ?? '');
  let price = $state(product?.price?.toString() ?? '');
  let original_price = $state(product?.original_price?.toString() ?? '');
  let stock = $state(product?.stock?.toString() ?? '10');
  let image = $state<string>(product?.image ?? '');
  let is_active = $state(product?.is_active ?? true);
  let saving = $state(false);
  let tagInput = $state('');
  let tags = $state<string[]>(product?.tags ?? []);

  function addTag() {
    const t = tagInput.toLowerCase().trim().replace(/[^a-z0-9-]/g, '-').replace(/-+/g, '-').replace(/^-|-$/g, '');
    if (t && !tags.includes(t)) tags = [...tags, t];
    tagInput = '';
  }
  function removeTag(t: string) { tags = tags.filter(x => x !== t); }

  function onFile(e: any) {
    const file = e.target.files?.[0];
    if (!file) return;
    if (file.size > 1024 * 1024) { toast.warn('Maks 1MB'); return; }
    const r = new FileReader();
    r.onload = () => { image = r.result as string; };
    r.readAsDataURL(file);
  }

  async function submit(e: Event) {
    e.preventDefault();
    if (!name || !price) { toast.warn('Lengkapi data'); return; }
    if (tags.length === 0) { toast.warn('Tambah minimal 1 tag'); return; }
    saving = true;
    try {
      const body: any = { name, description, price: +price, original_price: original_price ? +original_price : null,
                          stock: +stock, image, is_active, tags };
      if (mode === 'create') await apiEndpoints.sellerCreateProduct(body);
      else await apiEndpoints.sellerUpdateProduct(product.id, body);
      toast.success(mode === 'create' ? 'Produk dibuat' : 'Produk diperbarui');
      goto('/seller/products');
    } catch (e: any) { toast.error(e.message); } finally { saving = false; }
  }
</script>

<form on:submit={submit} class="space-y-5">
  <div><label class="label">Nama Produk</label><input bind:value={name} class="input" required /></div>

  <div>
    <label class="label">Tag <span class="text-red-600">*</span></label>
    <p class="helper mb-2">Gunakan tag untuk mengkategorikan produk. Otomatis lowercase, contoh: <code>elektronik</code>, <code>kemeja-pria</code>.</p>
    <div class="flex flex-wrap gap-1.5 mb-2">
      {#each tags as t}
        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-app-primary text-app-pfg text-xs">
          #{t}
          <button type="button" on:click={() => removeTag(t)} class="hover:text-red-300"><Icon name="x" size={10} /></button>
        </span>
      {/each}
    </div>
    <div class="flex gap-2">
      <input bind:value={tagInput} on:keydown={(e) => { if (e.key === 'Enter') { e.preventDefault(); addTag(); } }} class="input flex-1" placeholder="ketik tag lalu Enter" />
      <button type="button" on:click={addTag} class="btn-outline btn-md">Tambah</button>
    </div>
  </div>

  <div class="grid sm:grid-cols-3 gap-3">
    <div><label class="label">Harga (Rp)</label><input type="number" min="1" bind:value={price} class="input" required /></div>
    <div><label class="label">Harga Coret</label><input type="number" min="0" bind:value={original_price} class="input" /></div>
    <div><label class="label">Stok</label><input type="number" min="0" bind:value={stock} class="input" required /></div>
  </div>

  <div><label class="label">Deskripsi</label><textarea rows={4} bind:value={description} class="input" required /></div>

  <div>
    <label class="label">Gambar Produk</label>
    <div class="grid sm:grid-cols-[160px_1fr] gap-3 items-start">
      <div class="aspect-square rounded-2xl border border-ink-200 bg-ink-50 grid place-items-center overflow-hidden">
        {#if image}<img src={image} alt="" class="w-full h-full object-cover" />
        {:else}<Icon name="image" size={36} class="text-ink-300" />{/if}
      </div>
      <div>
        <input type="file" accept="image/*" on:change={onFile} class="text-sm" />
        <p class="helper">Maks 1MB. Jika kosong, sistem generate otomatis.</p>
      </div>
    </div>
  </div>

  <label class="flex items-center gap-2 text-sm">
    <input type="checkbox" bind:checked={is_active} />
    Aktifkan produk (tampil di etalase)
  </label>

  <div class="flex gap-2 pt-3 border-t border-ink-100">
    <button disabled={saving} class="btn-primary btn-md">{saving ? 'Menyimpan…' : (mode === 'create' ? 'Buat Produk' : 'Simpan Perubahan')}</button>
    <button type="button" on:click={() => history.back()} class="btn-outline btn-md">Batal</button>
  </div>
</form>
