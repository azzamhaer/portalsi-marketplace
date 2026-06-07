<script lang="ts">
  import { settings } from '$lib/stores.svelte';
</script>

<footer class="mt-16 sm:mt-24 border-t border-ink-100 bg-ink-50/50">
  <div class="container-x py-10 sm:py-14">
    <div class="grid grid-cols-2 md:grid-cols-5 gap-6 sm:gap-8">
      <div class="col-span-2 md:col-span-2">
        <a href="/" class="flex items-center gap-2 font-display font-extrabold text-xl tracking-tightest mb-3">
          {#if settings.logo}
            <img src={settings.logo} alt={settings.appName} class="w-8 h-8 rounded-lg object-cover" />
          {:else}
            <span class="w-8 h-8 bg-app-primary text-app-pfg rounded-lg grid place-items-center text-sm">{settings.appName?.[0] ?? 'P'}</span>
          {/if}
          {settings.appName ?? 'MPSI'}
        </a>
        <p class="text-sm text-ink-600 leading-relaxed max-w-sm">
          Marketplace multivendor terdepan di Indonesia. Belanja produk asli dari ribuan toko terpercaya dengan pembayaran terenkripsi.
        </p>
        <p class="text-xs text-ink-400 mt-4">support@portalsi.id · 0800-1-PORTAL</p>
      </div>

      <div>
        <h5 class="text-xs font-semibold uppercase tracking-widest text-ink-500 mb-3">Belanja</h5>
        <ul class="space-y-2 text-sm text-ink-700">
          <li><a href="/products" class="hover:text-ink-950">Semua Produk</a></li>
          <li><a href="/vendors"  class="hover:text-ink-950">Semua Toko</a></li>
          <li><a href="/products?flash=1" class="hover:text-ink-950">Flash Sale</a></li>
          <li><a href="/wishlist" class="hover:text-ink-950">Wishlist</a></li>
        </ul>
      </div>

      <div>
        <h5 class="text-xs font-semibold uppercase tracking-widest text-ink-500 mb-3">Pembayaran</h5>
        <ul class="space-y-2 text-sm text-ink-700">
          <li><a href="/payment-info"        class="hover:text-ink-950">Cara Pembayaran</a></li>
          <li><a href="/payment-info#va"     class="hover:text-ink-950">Virtual Account</a></li>
          <li><a href="/payment-info#ewallet"class="hover:text-ink-950">E-Wallet</a></li>
          <li><a href="/payment-info#qris"   class="hover:text-ink-950">QRIS</a></li>
        </ul>
      </div>

      <div>
        <h5 class="text-xs font-semibold uppercase tracking-widest text-ink-500 mb-3">Bantuan</h5>
        <ul class="space-y-2 text-sm text-ink-700">
          <li><a href="/help"            class="hover:text-ink-950">Pusat Bantuan</a></li>
          <li><a href="/about"           class="hover:text-ink-950">Tentang Kami</a></li>
          <li><a href="/seller/register" class="hover:text-ink-950">Buka Toko</a></li>
          <li><a href="/seller/dashboard"class="hover:text-ink-950">Seller Center</a></li>
        </ul>
      </div>
    </div>

    <div class="mt-10 pt-6 border-t border-ink-200 flex flex-col sm:flex-row gap-3 items-start sm:items-center justify-between text-xs text-ink-500">
      <span>© 2026 {settings.appName ?? 'MPSI'}. Semua hak dilindungi.</span>
      <span>Pembayaran diamankan dengan enkripsi end-to-end</span>
    </div>
  </div>
</footer>
