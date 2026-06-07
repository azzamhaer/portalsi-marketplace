<script lang="ts">
  import '../app.css';
  import Header from '$lib/components/Header.svelte';
  import Footer from '$lib/components/Footer.svelte';
  import Toaster from '$lib/components/Toaster.svelte';
  import { onMount } from 'svelte';
  import { auth, settings, wishlist, applyPalette } from '$lib/stores.svelte';
  import { apiEndpoints, getToken } from '$lib/api';

  let { children } = $props();

  onMount(async () => {
    applyPalette({ primary: settings.primary, primaryFg: settings.primaryFg, accent: settings.accent });
    try {
      const s: any = await apiEndpoints.publicSettings();
      settings.setAll(s);
    } catch {}
    if (getToken() && !auth.user) {
      try { auth.set(await apiEndpoints.me()); } catch {}
    }
  });

  // Setiap kali auth.user berubah (login/logout), sinkron wishlist dari server
  let lastSyncedUserId = $state<number | null>(null);
  $effect(() => {
    const u = auth.user;
    if (u && u.id !== lastSyncedUserId) {
      lastSyncedUserId = u.id;
      apiEndpoints.getWishlist()
        .then((list: any) => wishlist.setAll((list as any[]).map(w => w.product_id)))
        .catch(() => {});
    } else if (!u && lastSyncedUserId !== null) {
      lastSyncedUserId = null;
      wishlist.clear();
    }
  });
</script>

<svelte:head>
  <title>{settings.appName ?? 'MPSI'}</title>
  {#if settings.logo}
    <link rel="icon" href={settings.logo} />
  {:else}
    <link rel="icon" href="/favicon.svg" />
  {/if}
</svelte:head>

<div class="min-h-screen flex flex-col">
  <Header />
  <main class="flex-1">{@render children()}</main>
  <Footer />
  <Toaster />
</div>
