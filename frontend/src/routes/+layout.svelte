<script lang="ts">
  import '../app.css';
  import Header from '$lib/components/Header.svelte';
  import Footer from '$lib/components/Footer.svelte';
  import Toaster from '$lib/components/Toaster.svelte';
  import EmailVerificationGate from '$lib/components/EmailVerificationGate.svelte';
  import { onMount } from 'svelte';
  import { auth, settings, wishlist, applyPalette } from '$lib/stores.svelte';
  import { apiEndpoints, getToken } from '$lib/api';
  import { page } from '$app/stores';

  let { data, children } = $props();

  // Pages yang TIDAK perlu email verifikasi (selalu boleh diakses)
  const PUBLIC_PATHS = ['/login', '/register', '/forgot-password', '/reset-password', '/verify-email', '/confirm-email'];
  const isExempt = $derived(PUBLIC_PATHS.some((p) => $page.url.pathname === p || $page.url.pathname.startsWith(p + '/')));
  const needsVerify = $derived(!!auth.user && !auth.user.email_verified_at && !isExempt);

  // Apply server-loaded settings IMMEDIATELY (no flash)
  if (data?.settings) settings.setAll(data.settings);

  // Generate fallback favicon dari nama + palette (SVG data URI)
  function makeFallbackFavicon(name: string, bg: string, fg: string): string {
    const letter = (name?.[0] ?? 'M').toUpperCase();
    const svg = `<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'>
      <rect width='100' height='100' rx='22' fill='${bg}'/>
      <text x='50' y='72' text-anchor='middle' font-size='62' font-weight='800' fill='${fg}' font-family='Inter,sans-serif'>${letter}</text>
    </svg>`;
    return 'data:image/svg+xml;utf8,' + encodeURIComponent(svg);
  }
  const faviconHref = $derived(
    settings.logo || makeFallbackFavicon(settings.appName, settings.primary || '#0a0a0a', settings.primaryFg || '#ffffff')
  );

  onMount(async () => {
    applyPalette({ primary: settings.primary, primaryFg: settings.primaryFg, accent: settings.accent });
    // Re-fetch settings client-side untuk sinkronisasi terbaru (background)
    try {
      const s: any = await apiEndpoints.publicSettings();
      settings.setAll(s);
    } catch {}
    if (getToken() && !auth.user) {
      try { auth.set(await apiEndpoints.me()); } catch {}
    }
  });

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
  <link rel="icon" type="image/svg+xml" href={faviconHref} />
  <link rel="apple-touch-icon" href={faviconHref} />
  <meta name="theme-color" content={settings.primary || '#0a0a0a'} />
  <style>
    :root {
      --app-primary: {settings.primary || '#0a0a0a'};
      --app-primary-fg: {settings.primaryFg || '#ffffff'};
      --app-accent: {settings.accent || '#6366f1'};
    }
  </style>
</svelte:head>

<div class="min-h-screen flex flex-col">
  <Header />
  <main class="flex-1">
    {#if needsVerify}
      <EmailVerificationGate />
    {:else}
      {@render children()}
    {/if}
  </main>
  <Footer />
  <Toaster />
</div>
