<script lang="ts">
  import Icon from '$lib/components/Icon.svelte';
  import MapPicker from '$lib/components/MapPicker.svelte';
  import AddressFields from '$lib/components/AddressFields.svelte';
  import { auth, cart, wishlist, toast, confirmDialog } from '$lib/stores.svelte';
  import { apiEndpoints, setToken } from '$lib/api';
  import { goto } from '$app/navigation';
  import { onMount } from 'svelte';

  const isAdmin = $derived(auth.user?.role === 'ADMIN');

  let name = $state(''), phone = $state(''), saving = $state(false);
  let addresses = $state<any[]>([]);
  let editing = $state<any>(null);
  let addressFormOpen = $state(false);
  let formAddr = $state<any>({ recipient:'', phone:'', country:'Indonesia', province:'', city:'', district:'', village:'', full_address:'', postal_code:'', address_note:'', latitude:null, longitude:null, is_default:false });

  // Change password state
  let cpOld = $state(''), cpNew = $state(''), cpConfirm = $state(''), cpSaving = $state(false);

  // Change email state
  let newEmail = $state(''), ceSaving = $state(false);

  onMount(async () => {
    if (!auth.user) { goto('/login?next=/profile'); return; }
    name = auth.user.name; phone = auth.user.phone || '';
    if (!isAdmin) {
      try { addresses = await apiEndpoints.addresses(); } catch {}
    }
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
    setToken(null);
    auth.clear();
    cart.clear();
    wishlist.clear();
    goto('/');
  }

  async function changePassword(e: Event) {
    e.preventDefault();
    if (cpNew !== cpConfirm) { toast.error('Konfirmasi password tidak cocok'); return; }
    if (cpNew.length < 6) { toast.error('Password baru minimal 6 karakter'); return; }
    cpSaving = true;
    try {
      await apiEndpoints.changePassword(cpOld, cpNew);
      cpOld = cpNew = cpConfirm = '';
      toast.success('Password berhasil diubah');
    } catch (e: any) { toast.error(e.message); } finally { cpSaving = false; }
  }

  async function changeEmail(e: Event) {
    e.preventDefault();
    if (!newEmail.includes('@')) { toast.error('Email tidak valid'); return; }
    ceSaving = true;
    try {
      await apiEndpoints.requestChangeEmail(newEmail);
      newEmail = '';
      toast.success('Link persetujuan sudah dikirim ke email saat ini');
    } catch (e: any) { toast.error(e.message); } finally { ceSaving = false; }
  }

  function openAddrForm(a: any | null) {
    editing = a;
    addressFormOpen = true;
    formAddr = a ? { country: 'Indonesia', ...a } : { recipient: name, phone, country:'Indonesia', province:'', city:'', district:'', village:'', full_address:'', postal_code:'', address_note:'', latitude: null, longitude: null, is_default: addresses.length === 0 };
  }

  async function saveAddr(e: Event) {
    e.preventDefault();
    try {
      if (editing?.id) await apiEndpoints.updateAddress(editing.id, formAddr);
      else await apiEndpoints.saveAddress(formAddr);
      addresses = await apiEndpoints.addresses();
      editing = null;
      addressFormOpen = false;
      toast.success('Alamat disimpan');
    } catch (e: any) { toast.error(e.message); }
  }
  async function delAddr(id: number) {
    const ok = await confirmDialog.ask({
      title: 'Hapus alamat?',
      message: 'Alamat ini akan dihapus dari daftar alamat pengiriman Anda.',
      confirmText: 'Hapus alamat',
      tone: 'danger',
    });
    if (!ok) return;
    try { await apiEndpoints.deleteAddress(id); addresses = await apiEndpoints.addresses(); }
    catch (e: any) { toast.error(e.message); }
  }
</script>

<svelte:head><title>Profil</title></svelte:head>

<div class="container-x py-6 sm:py-8">
  <h1 class="section-title mb-6 sm:mb-8">Profil</h1>

  {#if isAdmin}
    <!-- Admin: hanya panel profile, tanpa sidebar buyer -->
    <div class="max-w-2xl mx-auto space-y-5">
      <div class="card">
        <h3 class="font-semibold mb-4 flex items-center gap-2"><Icon name="user" size={16} /> Data Akun Admin</h3>
        <form on:submit={save} class="space-y-4">
          <div><label class="label">Nama</label><input bind:value={name} class="input" /></div>
          <div><label class="label">Email saat ini</label><input value={auth.user?.email ?? ''} disabled class="input bg-ink-50" /></div>
          <div><label class="label">No. HP</label><input bind:value={phone} class="input" /></div>
          <button disabled={saving} class="btn-primary btn-md">{saving ? 'Menyimpan…' : 'Simpan'}</button>
        </form>
      </div>

      <div class="card">
        <h3 class="font-semibold mb-4 flex items-center gap-2"><Icon name="mail" size={16} /> Ubah Email</h3>
        <p class="text-xs text-ink-500 mb-3">Link persetujuan akan dikirim ke email saat ini. Email baru aktif setelah link disetujui.</p>
        <form on:submit={changeEmail} class="flex gap-2">
          <input type="email" bind:value={newEmail} class="input flex-1" placeholder="email-baru@contoh.com" required />
          <button disabled={ceSaving} class="btn-primary btn-md">{ceSaving ? 'Mengirim…' : 'Kirim konfirmasi'}</button>
        </form>
      </div>

      <div class="card">
        <h3 class="font-semibold mb-4 flex items-center gap-2"><Icon name="lock" size={16} /> Ubah Password</h3>
        <p class="text-xs text-ink-500 mb-3">Lupa password lama? Gunakan <a href="/forgot-password" class="link">reset password via email</a>.</p>
        <form on:submit={changePassword} class="space-y-3 max-w-md">
          <div><label class="label">Password lama</label><input type="password" bind:value={cpOld} class="input" required /></div>
          <div><label class="label">Password baru</label><input type="password" bind:value={cpNew} class="input" required minlength="6" /></div>
          <div><label class="label">Konfirmasi password baru</label><input type="password" bind:value={cpConfirm} class="input" required minlength="6" /></div>
          <button disabled={cpSaving} class="btn-primary btn-md">{cpSaving ? 'Menyimpan…' : 'Ubah Password'}</button>
        </form>
      </div>

      <div class="flex justify-end">
        <button on:click={logout} class="btn-outline btn-md text-red-600 border-red-200 hover:bg-red-50">Keluar</button>
      </div>
    </div>
  {:else}
    <!-- User biasa (BUYER/SELLER) — tanpa sidebar karena sudah ada di header dropdown -->
    <div class="max-w-2xl mx-auto">
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
          <h3 class="font-semibold mb-3 flex items-center gap-2"><Icon name="mail" size={16} /> Ubah Email</h3>
          <p class="text-xs text-ink-500 mb-3">Link persetujuan dikirim ke email saat ini. Email baru aktif setelah link disetujui.</p>
          <form on:submit={changeEmail} class="flex gap-2">
            <input type="email" bind:value={newEmail} class="input flex-1" placeholder="email-baru@contoh.com" required />
            <button disabled={ceSaving} class="btn-primary btn-md">{ceSaving ? 'Mengirim…' : 'Kirim'}</button>
          </form>
        </div>

        <div class="card">
          <h3 class="font-semibold mb-3 flex items-center gap-2"><Icon name="lock" size={16} /> Ubah Password</h3>
          <p class="text-xs text-ink-500 mb-3">Lupa password lama? Gunakan <a href="/forgot-password" class="link">reset password via email</a>.</p>
          <form on:submit={changePassword} class="space-y-3 max-w-md">
            <div><label class="label">Password lama</label><input type="password" bind:value={cpOld} class="input" required /></div>
            <div><label class="label">Password baru</label><input type="password" bind:value={cpNew} class="input" required minlength="6" /></div>
            <div><label class="label">Konfirmasi password baru</label><input type="password" bind:value={cpConfirm} class="input" required minlength="6" /></div>
            <button disabled={cpSaving} class="btn-primary btn-md">{cpSaving ? 'Menyimpan…' : 'Ubah Password'}</button>
          </form>
        </div>

        <div class="card">
          <div class="flex items-center justify-between mb-4">
            <h3 class="font-semibold">Alamat</h3>
            <button on:click={() => openAddrForm(null)} class="btn-outline btn-sm"><Icon name="plus" size={12} /> Tambah</button>
          </div>
          {#if addresses.length === 0 && !addressFormOpen}
            <p class="text-sm text-ink-500">Belum ada alamat tersimpan.</p>
          {/if}
          {#each addresses as a (a.id)}
            <div class="border border-ink-100 rounded-xl p-4 mb-2 flex items-start gap-3">
              <Icon name="map-pin" size={18} class="text-ink-500 mt-0.5" />
              <div class="flex-1">
                <div class="flex items-center gap-2"><b>{a.recipient}</b> <span class="text-xs text-ink-500">{a.phone}</span> {#if a.is_default}<span class="pill-ink">Utama</span>{/if}</div>
                <div class="text-sm text-ink-600 mt-0.5">
                  {a.full_address}, {a.village ? `${a.village}, ` : ''}{a.district ? `${a.district}, ` : ''}{a.city}{a.province ? `, ${a.province}` : ''}{a.postal_code ? ` ${a.postal_code}` : ''}
                </div>
                {#if a.latitude && a.longitude}
                  <a href={`https://www.google.com/maps?q=${a.latitude},${a.longitude}`} target="_blank" class="text-xs text-blue-600 mt-1 inline-flex items-center gap-1">Lihat di Maps <Icon name="external-link" size={10} /></a>
                {/if}
              </div>
              <button on:click={() => openAddrForm(a)} class="text-ink-700 hover:bg-ink-50 w-8 h-8 grid place-items-center rounded"><Icon name="pencil" size={14} /></button>
              <button on:click={() => delAddr(a.id)} class="text-red-600 hover:bg-red-50 w-8 h-8 grid place-items-center rounded"><Icon name="trash-2" size={14} /></button>
            </div>
          {/each}
        </div>

        {#if addressFormOpen}
          <div class="card">
            <h3 class="font-semibold mb-4">{editing?.id ? 'Edit' : 'Tambah'} Alamat</h3>
            <form on:submit={saveAddr} class="space-y-3">
              <AddressFields bind:value={formAddr} />
              <div>
                <label class="label">Pin Lokasi (opsional)</label>
                <MapPicker bind:lat={formAddr.latitude} bind:lng={formAddr.longitude} />
              </div>
              <label class="flex items-center gap-2 text-sm"><input type="checkbox" bind:checked={formAddr.is_default} /> Jadikan alamat utama</label>
              <div class="flex gap-2">
                <button class="btn-primary btn-md">Simpan</button>
                <button type="button" on:click={() => { editing = null; addressFormOpen = false; }} class="btn-outline btn-md">Batal</button>
              </div>
            </form>
          </div>
        {/if}

        <div class="flex justify-end">
          <button on:click={logout} class="btn-outline btn-md text-red-600 border-red-200 hover:bg-red-50">Keluar</button>
        </div>
      </div>
    </div>
  {/if}
</div>
