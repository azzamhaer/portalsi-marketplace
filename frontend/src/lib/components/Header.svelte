<script lang="ts">
  import Icon from '$lib/components/Icon.svelte';
  import { goto } from '$app/navigation';
  import { auth, cart, settings } from '$lib/stores.svelte';
  import { setToken, apiEndpoints } from '$lib/api';
  import { onMount } from 'svelte';

  let q = $state('');
  let mobileOpen = $state(false);
  let userOpen = $state(false);
  let userMenuRef: HTMLDivElement | null = $state(null);

  function search(e: Event) {
    e.preventDefault();
    if (!q.trim()) return;
    goto('/search?q=' + encodeURIComponent(q.trim()));
    mobileOpen = false;
  }

  async function logout() {
    try { await apiEndpoints.logout(); } catch {}
    setToken(null);
    auth.clear();
    userOpen = false;
    goto('/');
  }

  function nav(href: string) {
    userOpen = false;
    mobileOpen = false;
    goto(href);
  }

  onMount(() => {
    function onDocClick(e: MouseEvent) {
      if (!userOpen) return;
      if (userMenuRef && !userMenuRef.contains(e.target as Node)) {
        userOpen = false;
      }
    }
    function onEsc(e: KeyboardEvent) {
      if (e.key === 'Escape') { userOpen = false; mobileOpen = false; }
    }
    document.addEventListener('click', onDocClick);
    document.addEventListener('keydown', onEsc);
    return () => {
      document.removeEventListener('click', onDocClick);
      document.removeEventListener('keydown', onEsc);
    };
  });
</script>

<header class="sticky top-0 z-40 bg-white/80 backdrop-blur-xl border-b border-ink-100">
  <div class="container-x">
    <div class="flex items-center gap-3 sm:gap-6 h-16">
      <a href="/" class="flex items-center gap-2 font-display font-extrabold text-lg sm:text-xl tracking-tightest text-ink-950 shrink-0">
        {#if settings.logo}
          <img src={settings.logo} alt={settings.appName} class="w-8 h-8 rounded-lg object-cover" />
        {:else}
          <span class="w-8 h-8 bg-app-primary text-app-pfg rounded-lg grid place-items-center text-sm">{settings.appName?.[0] ?? 'P'}</span>
        {/if}
        <span class="hidden xs:inline">{settings.appName ?? 'MPSI'}</span>
      </a>

      <nav class="hidden lg:flex items-center gap-7 text-sm text-ink-700">
        <a href="/" class="hover:text-ink-950 transition-colors">Beranda</a>
        <a href="/products" class="hover:text-ink-950 transition-colors">Produk</a>
        <a href="/vendors" class="hover:text-ink-950 transition-colors">Toko</a>
        <a href="/payment-info" class="hover:text-ink-950 transition-colors">Pembayaran</a>
        <a href="/help" class="hover:text-ink-950 transition-colors">Bantuan</a>
      </nav>

      <form on:submit={search} class="ml-auto hidden md:flex items-center gap-2 bg-ink-50 hover:bg-ink-100 transition-colors rounded-full pl-4 pr-1.5 py-1.5 w-72 lg:w-96">
        <Icon name="search" size={16} class="text-ink-400" />
        <input bind:value={q} type="text" placeholder="Cari produk, brand, atau toko" class="flex-1 bg-transparent text-sm outline-none placeholder:text-ink-400" />
        <button type="submit" class="text-xs bg-app-primary text-app-pfg px-3.5 py-1.5 rounded-full font-medium hover:bg-ink-800 transition-colors">Cari</button>
      </form>

      <div class="hidden md:flex items-center gap-1 ml-2">
        <a href="/wishlist" class="w-10 h-10 grid place-items-center rounded-full hover:bg-ink-100 transition-colors" aria-label="Wishlist">
          <Icon name="heart" size={18} class="text-ink-700" />
        </a>
        <a href="/cart" class="w-10 h-10 grid place-items-center rounded-full hover:bg-ink-100 transition-colors relative" aria-label="Keranjang">
          <Icon name="shopping-bag" size={18} class="text-ink-700" />
          {#if cart.count > 0}
            <span class="absolute -top-0.5 -right-0.5 bg-app-primary text-app-pfg text-[10px] font-semibold rounded-full min-w-[18px] h-[18px] grid place-items-center px-1.5">{cart.count}</span>
          {/if}
        </a>
        {#if auth.user}
          <div class="relative" bind:this={userMenuRef}>
            <button type="button" on:click|stopPropagation={() => userOpen = !userOpen} class="w-10 h-10 grid place-items-center rounded-full hover:bg-ink-100 transition-colors" aria-label="Akun" aria-expanded={userOpen}>
              <Icon name="user" size={18} class="text-ink-700" />
            </button>
            {#if userOpen}
              <div class="absolute right-0 top-full mt-1 w-56 bg-white rounded-2xl shadow-elevated border border-ink-100 p-2 animate-fadeIn z-50">
                <div class="px-3 py-2 border-b border-ink-100 mb-1">
                  <div class="font-semibold text-sm">{auth.user.name}</div>
                  <div class="text-xs text-ink-500 truncate">{auth.user.email}</div>
                </div>
                <button type="button" on:click={() => nav('/profile')}  class="block w-full text-left px-3 py-2 text-sm rounded-lg hover:bg-ink-50">Profil</button>
                <button type="button" on:click={() => nav('/orders')}   class="block w-full text-left px-3 py-2 text-sm rounded-lg hover:bg-ink-50">Pesanan</button>
                <button type="button" on:click={() => nav('/wishlist')} class="block w-full text-left px-3 py-2 text-sm rounded-lg hover:bg-ink-50">Wishlist</button>
                <button type="button" on:click={() => nav('/chats')}    class="block w-full text-left px-3 py-2 text-sm rounded-lg hover:bg-ink-50">Chat</button>
                {#if auth.user.role === 'ADMIN'}
                  <button type="button" on:click={() => nav('/admin')} class="block w-full text-left px-3 py-2 text-sm rounded-lg hover:bg-ink-50 text-accent-dark font-semibold">Admin Center</button>
                {/if}
                {#if auth.user.vendor_id}
                  <button type="button" on:click={() => nav('/seller/dashboard')} class="block w-full text-left px-3 py-2 text-sm rounded-lg hover:bg-ink-50">Seller Center</button>
                {:else}
                  <button type="button" on:click={() => nav('/seller/register')} class="block w-full text-left px-3 py-2 text-sm rounded-lg hover:bg-ink-50">Buka Toko</button>
                {/if}
                <button type="button" on:click={logout} class="block w-full text-left px-3 py-2 text-sm rounded-lg hover:bg-ink-50 text-red-600">Keluar</button>
              </div>
            {/if}
          </div>
        {:else}
          <a href="/login" class="btn-primary btn-md ml-2">Masuk</a>
        {/if}
      </div>

      <button on:click={() => mobileOpen = !mobileOpen} class="md:hidden ml-auto w-10 h-10 grid place-items-center rounded-full hover:bg-ink-100" aria-label="Menu">
        <Icon name={mobileOpen ? 'x' : 'menu'} size={20} />
      </button>
    </div>
  </div>

  {#if mobileOpen}
    <div class="md:hidden border-t border-ink-100 bg-white animate-fadeIn">
      <div class="container-x py-4 space-y-3">
        <form on:submit={search} class="flex items-center gap-2 bg-ink-50 rounded-full pl-4 pr-1.5 py-1.5">
          <Icon name="search" size={16} class="text-ink-400" />
          <input bind:value={q} type="text" placeholder="Cari produk" class="flex-1 bg-transparent text-sm outline-none" />
          <button type="submit" class="text-xs bg-app-primary text-app-pfg px-3 py-1.5 rounded-full">Cari</button>
        </form>
        <nav class="grid gap-1 text-sm">
          {#each [['Beranda','/'],['Produk','/products'],['Toko','/vendors'],['Keranjang','/cart'],['Wishlist','/wishlist'],['Chat','/chats'],['Pesanan','/orders'],['Pembayaran','/payment-info'],['Bantuan','/help']] as [label, href]}
            <a {href} on:click={() => mobileOpen = false} class="px-3 py-2.5 rounded-lg hover:bg-ink-50">{label}</a>
          {/each}
          {#if auth.user}
            <a href="/profile" on:click={() => mobileOpen = false} class="px-3 py-2.5 rounded-lg hover:bg-ink-50">Profil — {auth.user.name}</a>
            {#if auth.user.role === 'ADMIN'}
              <a href="/admin" on:click={() => mobileOpen = false} class="px-3 py-2.5 rounded-lg bg-app-primary text-app-pfg">Admin Center</a>
            {/if}
            {#if auth.user.vendor_id}
              <a href="/seller/dashboard" on:click={() => mobileOpen = false} class="px-3 py-2.5 rounded-lg hover:bg-ink-50">Seller Center</a>
            {:else}
              <a href="/seller/register" on:click={() => mobileOpen = false} class="px-3 py-2.5 rounded-lg hover:bg-ink-50">Buka Toko</a>
            {/if}
            <button on:click={() => { logout(); mobileOpen = false; }} class="text-left px-3 py-2.5 rounded-lg hover:bg-ink-50 text-red-600">Keluar</button>
          {:else}
            <a href="/login" on:click={() => mobileOpen = false} class="btn-primary btn-md mt-2">Masuk</a>
          {/if}
        </nav>
      </div>
    </div>
  {/if}
</header>
