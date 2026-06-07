<script lang="ts">
  import Icon from '$lib/components/Icon.svelte';
  import MapPicker from '$lib/components/MapPicker.svelte';
  import { auth, toast } from '$lib/stores.svelte';
  import { apiEndpoints, setToken } from '$lib/api';
  import { goto } from '$app/navigation';
  import { onMount } from 'svelte';

  let name = $state(''), phone = $state(''), saving = $state(false);
  let addresses = $state<any[]>([]);
  let editing = $state<any>(null);
  let formAddr = $state<any>({ recipient:'', phone:'', city:'', full_address:'', postal_code:'', latitude:null, longitude:null, is_default:false });

  onMount(async () => {
    if (!auth.user) { goto('/login?next=/profile'); return; }
    name = auth.user.name; phone = auth.user.phone || '';
    try { addresses = await apiEndpoints.addresses(); } catch {}
  });

  async function save(e: Event) {
    e.preventDefault();
    saving = true;
    try {
      const u = await apiEndpoints.updateProfile({ name, phone });
      auth.set(u);
      toast.success('Profil disimpan');
    } catch (e: any) { toast.error(e.message); } finally { saving = false; }
  }
  async function logout() {
    try { await apiEndpoints.logout(); } catch {}
    setToken(null); auth.clear(); goto('/');
  }

  function openAddrForm(a: any | null) {
    editing = a;
    formAddr = a ? { ...a } : { recipient: name, phone, city:'', full_address:'', postal_code:'', latitude: null, longitude: null, is_default: addresses.length === 0 };
  }

  async function saveAddr(e: Event) {
    e.preventDefault();
    try {
      if (editing?.id) await apiEndpoints.updateAddress(editing.id, formAddr);
      else await apiEndpoints.saveAddress(formAddr);
      addresses = await apiEndpoints.addresses();
      editing = null;
      toast.success('Alamat disimpan');
    } catch (e: any) { toast.error(e.message); }
  }
  async function delAddr(id: number) {
    if (!confirm('Hapus alamat?')) return;
    try { await apiEndpoints.deleteAddress(id); addresses = await apiEndpoints.addresses(); }
    catch (e: any) { toast.error(e.message); }
  }
</script>

<svelte:head><title>Profil</title></svelte:head>

<div class="container-x py-6 sm:py-8">
  <h1 class="section-title mb-6 sm:mb-8">Profil</h1>

  <div class="grid lg:grid-cols-[260px_1fr] gap-6">
    <aside>
      <ul class="space-y-1 text-sm">
        <li><span class="block px-3 py-2 rounded-lg bg-app-primary text-app-pfg">Profil</span></li>
        <li><a href="/orders" class="block px-3 py-2 rounded-lg hover:bg-ink-50">Pesanan</a></li>
        <li><a href="/wishlist" class="block px-3 py-2 rounded-lg hover:bg-ink-50">Wishlist</a></li>
        <li><a href="/chats" class="block px-3 py-2 rounded-lg hover:bg-ink-50">Chat</a></li>
        {#if auth.user?.vendor_id}
          <li><a href="/seller/dashboard" class="block px-3 py-2 rounded-lg hover:bg-ink-50">Seller Center</a></li>
        {:else}
          <li><a href="/seller/register" class="block px-3 py-2 rounded-lg hover:bg-ink-50">Buka Toko</a></li>
        {/if}
        {#if auth.user?.role === 'ADMIN'}
          <li><a href="/admin" class="block px-3 py-2 rounded-lg hover:bg-ink-50 text-accent-dark">Admin Center</a></li>
        {/if}
        <li><button on:click={logout} class="w-full text-left block px-3 py-2 rounded-lg hover:bg-red-50 text-red-600">Keluar</button></li>
      </ul>
    </aside>

    <div class="space-y-5">
      <div class="card">
        <h3 class="font-semibold mb-4">Data Pribadi</h3>
        <form on:submit={save} class="space-y-4 max-w-lg">
          <div><label class="label">Nama</label><input bind:value={name} class="input" /></div>
          <div><label class="label">Email</label><input value={auth.user?.email ?? ''} disabled class="input bg-ink-50" /></div>
          <div><label class="label">No. HP</label><input bind:value={phone} class="input" /></div>
          <button disabled={saving} class="btn-primary btn-md">{saving ? 'Menyimpan…' : 'Simpan'}</button>
        </form>
      </div>

      <div class="card">
        <div class="flex items-center justify-between mb-4">
          <h3 class="font-semibold">Alamat</h3>
          <button on:click={() => openAddrForm(null)} class="btn-outline btn-sm"><Icon name="plus" size={12} /> Tambah</button>
        </div>
        {#if addresses.length === 0 && !editing}
          <p class="text-sm text-ink-500">Belum ada alamat tersimpan.</p>
        {/if}
        {#each addresses as a (a.id)}
          <div class="border border-ink-100 rounded-xl p-4 mb-2 flex items-start gap-3">
            <Icon name="map-pin" size={18} class="text-ink-500 mt-0.5" />
            <div class="flex-1">
              <div class="flex items-center gap-2"><b>{a.recipient}</b> <span class="text-xs text-ink-500">{a.phone}</span> {#if a.is_default}<span class="pill-ink">Utama</span>{/if}</div>
              <div class="text-sm text-ink-600 mt-0.5">{a.full_address}, {a.city}</div>
              {#if a.latitude && a.longitude}
                <a href={`https://www.google.com/maps?q=${a.latitude},${a.longitude}`} target="_blank" class="text-xs text-blue-600 mt-1 inline-flex items-center gap-1">Lihat di Maps <Icon name="external-link" size={10} /></a>
              {/if}
            </div>
            <button on:click={() => openAddrForm(a)} class="text-ink-700 hover:bg-ink-50 w-8 h-8 grid place-items-center rounded"><Icon name="pencil" size={14} /></button>
            <button on:click={() => delAddr(a.id)} class="text-red-600 hover:bg-red-50 w-8 h-8 grid place-items-center rounded"><Icon name="trash-2" size={14} /></button>
          </div>
        {/each}
      </div>

      {#if editing !== null || (editing === null && addresses.length === 0)}
        {#if editing !== undefined && (editing !== null || addresses.length === 0)}
        {/if}
      {/if}

      {#if editing !== null}
        <div class="card">
          <h3 class="font-semibold mb-4">{editing?.id ? 'Edit' : 'Tambah'} Alamat</h3>
          <form on:submit={saveAddr} class="space-y-3">
            <div class="grid sm:grid-cols-2 gap-3">
              <div><label class="label">Penerima</label><input bind:value={formAddr.recipient} class="input" required /></div>
              <div><label class="label">No. HP</label><input bind:value={formAddr.phone} class="input" required /></div>
            </div>
            <div class="grid sm:grid-cols-[1fr_140px] gap-3">
              <div><label class="label">Kota</label><input bind:value={formAddr.city} class="input" required /></div>
              <div><label class="label">Kode Pos</label><input bind:value={formAddr.postal_code} class="input" /></div>
            </div>
            <div><label class="label">Alamat Lengkap</label><textarea bind:value={formAddr.full_address} class="input" rows={3} required></textarea></div>
            <div>
              <label class="label">Pin Lokasi (opsional)</label>
              <MapPicker bind:lat={formAddr.latitude} bind:lng={formAddr.longitude} />
            </div>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" bind:checked={formAddr.is_default} /> Jadikan alamat utama</label>
            <div class="flex gap-2">
              <button class="btn-primary btn-md">Simpan</button>
              <button type="button" on:click={() => editing = null} class="btn-outline btn-md">Batal</button>
            </div>
          </form>
        </div>
      {/if}
    </div>
  </div>
</div>
