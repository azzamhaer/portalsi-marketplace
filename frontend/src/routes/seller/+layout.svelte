<script lang="ts">
  import { onMount } from 'svelte';
  import { goto } from '$app/navigation';
  import { page } from '$app/stores';
  import { auth, toast } from '$lib/stores.svelte';
  import { apiEndpoints, getToken } from '$lib/api';

  let { children } = $props();
  let checked = $state(false);
  let mounted = $state(false);

  onMount(async () => {
    mounted = true;
    if (!getToken() || !auth.user) {
      goto('/login?next=' + $page.url.pathname);
      return;
    }

    const path = $page.url.pathname;
    const exempt = ['/seller/register', '/seller/pending', '/seller/profile'];
    if (exempt.includes(path)) { checked = true; return; }

    try {
      const data: any = await apiEndpoints.sellerDashboard();
      const status = data?.vendor?.verification_status;
      if (status !== 'APPROVED') {
        goto('/seller/pending');
        return;
      }
      checked = true;
    } catch (e: any) {
      if (e.status === 404) goto('/seller/register');
      else { toast.error(e.message); goto('/'); }
    }
  });
</script>

{#if checked}
  {@render children()}
{:else}
  <div class="container-x py-16">
    <div class="card text-center text-ink-500">{mounted ? 'Memeriksa akses…' : 'Memuat…'}</div>
  </div>
{/if}
