<script lang="ts">
  import { page } from '$app/stores';
  import { auth } from '$lib/stores.svelte';
  import Icon from './Icon.svelte';

  const items = $derived([
    { label: 'Beranda', href: '/', icon: 'home' },
    { label: 'Produk', href: '/products', icon: 'package-search' },
    { label: 'Notifikasi', href: auth.user ? '/notifications' : '/login', icon: 'bell' },
    { label: 'Profil', href: auth.user ? '/profile' : '/login', icon: 'user-round' },
  ]);

  function active(href: string) {
    if (href === '/') return $page.url.pathname === '/';
    return $page.url.pathname === href || $page.url.pathname.startsWith(href + '/');
  }
</script>

<nav class="fixed inset-x-0 bottom-0 z-50 px-3 pb-3 md:hidden pointer-events-none" aria-label="Navigasi utama mobile">
  <div class="pointer-events-auto mx-auto max-w-md rounded-[28px] border border-white/60 bg-white/72 p-1.5 shadow-[0_18px_50px_rgba(0,0,0,0.18)] backdrop-blur-2xl supports-[backdrop-filter]:bg-white/60">
    <div class="grid grid-cols-4 gap-1">
      {#each items as item}
        {@const isActive = active(item.href)}
        <a
          href={item.href}
          class="relative flex h-14 min-w-0 flex-col items-center justify-center gap-0.5 rounded-[22px] text-[11px] font-medium transition-all"
          class:bg-app-primary={isActive}
          class:text-app-pfg={isActive}
          class:text-ink-500={!isActive}
          class:hover:bg-ink-100={!isActive}
          aria-current={isActive ? 'page' : undefined}
        >
          <Icon name={item.icon} size={19} />
          <span class="max-w-full truncate px-1">{item.label}</span>
        </a>
      {/each}
    </div>
  </div>
</nav>
