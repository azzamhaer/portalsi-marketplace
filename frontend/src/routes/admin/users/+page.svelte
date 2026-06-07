<script lang="ts">
  import { onMount } from 'svelte';
  import { apiEndpoints } from '$lib/api';
  import { toast } from '$lib/stores.svelte';
  import Icon from '$lib/components/Icon.svelte';

  let users = $state<any[]>([]);
  let loading = $state(true);
  let search = $state('');
  let role = $state('');

  async function load() {
    loading = true;
    try {
      const params = new URLSearchParams();
      if (search) params.set('search', search);
      if (role) params.set('role', role);
      const r: any = await apiEndpoints.adminUsers(params.toString());
      users = r.data ?? [];
    } finally { loading = false; }
  }
  onMount(load);

  async function changeRole(u: any, newRole: string) {
    try { await apiEndpoints.adminUpdateUser(u.id, { role: newRole }); toast.success('Role diubah'); load(); }
    catch (e: any) { toast.error(e.message); }
  }
  async function del(u: any) {
    if (!confirm(`Hapus user "${u.name}"?`)) return;
    try { await apiEndpoints.adminDeleteUser(u.id); toast.success('Dihapus'); load(); }
    catch (e: any) { toast.error(e.message); }
  }
</script>

<div class="card flex items-center gap-3 mb-4 flex-wrap">
  <h3 class="font-semibold">Users</h3>
  <input bind:value={search} on:input={() => { clearTimeout((window as any).__t); (window as any).__t = setTimeout(load, 400); }} class="input-sm input flex-1 min-w-[200px]" placeholder="Cari nama atau email" />
  <select bind:value={role} on:change={load} class="input-sm input w-40">
    <option value="">Semua role</option>
    <option value="BUYER">Buyer</option>
    <option value="SELLER">Seller</option>
    <option value="ADMIN">Admin</option>
  </select>
</div>

{#if loading}<div class="card text-center text-ink-500 py-10">Memuat…</div>
{:else}
  <div class="card overflow-x-auto">
    <table class="w-full text-sm">
      <thead class="text-xs text-ink-500 border-b border-ink-100">
        <tr><th class="text-left py-2">User</th><th class="text-left py-2">Role</th><th class="text-left py-2">Toko</th><th class="text-left py-2">Daftar</th><th class="text-left py-2">Aksi</th></tr>
      </thead>
      <tbody>
        {#each users as u (u.id)}
          <tr class="border-b border-ink-100 last:border-0">
            <td class="py-2"><div class="font-medium">{u.name}</div><div class="text-xs text-ink-500">{u.email}</div></td>
            <td class="py-2"><span class="pill-ink">{u.role}</span></td>
            <td class="py-2 text-xs">
              {#if u.vendor}
                {u.vendor.name} <span class="pill-{u.vendor.verification_status === 'APPROVED' ? 'green' : u.vendor.verification_status === 'PENDING' ? 'amber' : 'red'} text-[10px] ml-1">{u.vendor.verification_status}</span>
              {:else}<span class="text-ink-400">—</span>{/if}
            </td>
            <td class="py-2 text-xs text-ink-500">{new Date(u.created_at).toLocaleDateString('id-ID')}</td>
            <td class="py-2">
              <div class="flex gap-1">
                <select on:change={(e: any) => changeRole(u, e.target.value)} value={u.role} class="text-xs border border-ink-200 rounded px-2 py-1">
                  <option value="BUYER">Buyer</option>
                  <option value="SELLER">Seller</option>
                  <option value="ADMIN">Admin</option>
                </select>
                {#if u.role !== 'ADMIN'}
                  <button on:click={() => del(u)} class="text-xs px-2 py-1 rounded bg-red-50 text-red-700 hover:bg-red-100"><Icon name="trash-2" size={12} /></button>
                {/if}
              </div>
            </td>
          </tr>
        {/each}
      </tbody>
    </table>
  </div>
{/if}
