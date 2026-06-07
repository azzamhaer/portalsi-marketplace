<script lang="ts">
  import AdminSidebar from '$lib/components/AdminSidebar.svelte';
  import { auth } from '$lib/stores.svelte';
  import { goto } from '$app/navigation';
  import { onMount } from 'svelte';
  import { page } from '$app/stores';

  let { children } = $props();

  const access = $derived(!!auth.user && auth.user.role === 'ADMIN');
  let mounted = $state(false);

  onMount(() => {
    mounted = true;
    if (!auth.user) { goto('/login?next=' + ($page.url.pathname || '/admin')); return; }
    if (auth.user.role !== 'ADMIN') goto('/');
  });
</script>

{#if access}
  <div class="container-x py-6 sm:py-8">
    <h1 class="section-title mb-6 sm:mb-8">Admin Center</h1>
    <div class="grid lg:grid-cols-[230px_1fr] gap-6">
      <AdminSidebar />
      <div>{@render children()}</div>
    </div>
  </div>
{:else}
  <div class="container-x py-16">
    <div class="card text-center text-ink-500">
      {mounted ? 'Mengalihkan…' : 'Memeriksa akses…'}
    </div>
  </div>
{/if}
