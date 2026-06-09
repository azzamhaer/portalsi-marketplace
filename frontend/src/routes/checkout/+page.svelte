<script lang="ts">
  import Icon from '$lib/components/Icon.svelte';
  import { cart, auth, toast } from '$lib/stores.svelte';
  import { apiEndpoints } from '$lib/api';
  import { fmtRp } from '$lib/utils';
  import { goto } from '$app/navigation';
  import { onMount } from 'svelte';

  let { data } = $props();

  const COURIERS = [
    { name: 'JNE Reguler',     eta: '2-4 hari', cost: 12000 },
    { name: 'J&T Express',     eta: '2-3 hari', cost: 14000 },
    { name: 'SiCepat REG',     eta: '2-4 hari', cost: 11000 },
    { name: 'AnterAja',        eta: '1-3 hari', cost: 13000 },
    { name: 'GoSend Sameday',  eta: 'Hari Ini', cost: 25000 },
    { name: 'Pos Indonesia',   eta: '3-5 hari', cost:  9000 }
  ];
  const CITIES = [
    'DKI Jakarta - Jakarta Pusat','DKI Jakarta - Jakarta Selatan','DKI Jakarta - Jakarta Barat',
    'Jawa Barat - Bandung','Jawa Barat - Bekasi','Jawa Barat - Depok','Jawa Tengah - Semarang',
    'DI Yogyakarta - Yogyakarta','Jawa Timur - Surabaya','Banten - Tangerang','Sumatera Utara - Medan','Bali - Denpasar'
  ];

  let recipient = $state(''), phone = $state(''), city = $state(CITIES[0]), full = $state(''), notes = $state('');
  let courier = $state(COURIERS[0]);
  let pay = $state<string | null>(null);
  let loading = $state(false);

  const items = $derived(cart.checkedItems);
  const subtotal = $derived(cart.subtotal);
  const insurance = $derived(subtotal > 500000 ? Math.round(subtotal * 0.002) : 0);
  const baseTotal = $derived(subtotal + courier.cost + insurance);
  const method = $derived(pay ? data.methods.find((m: any) => m.code === pay) : null);
  const fee = $derived(method ? Math.round(method.fee_flat + (baseTotal * method.fee_pct / 100)) : 0);
  const total = $derived(baseTotal + fee);

  onMount(() => {
    if (!auth.user) { goto('/login?next=/checkout'); return; }
    if (items.length === 0) { goto('/cart'); return; }
    recipient = auth.user.name || '';
    phone = auth.user.phone || '';
  });

  // Group payment methods
  const grouped = $derived.by(() => {
    const g: Record<string, any[]> = {};
    data.methods.forEach((m: any) => { (g[m.group] = g[m.group] || []).push(m); });
    return g;
  });

  async function submit() {
    if (!recipient || !phone || !full) { toast.warn('Lengkapi alamat'); return; }
    if (!pay) { toast.warn('Pilih metode pembayaran'); return; }
    loading = true;
    try {
      const res: any = await apiEndpoints.checkout({
        items: items.map(i => ({ product_id: i.product_id, qty: i.qty, variant_selection: i.variant_selection ?? null })),
        recipient, phone, city, full_address: full, notes,
        courier_name: courier.name, courier_eta: courier.eta, courier_cost: courier.cost,
        payment_method: pay,
      });
      cart.clearChecked();
      toast.success('Pesanan dibuat');
      goto('/orders/' + res.order_id);
    } catch (e: any) {
      toast.error(e.message || 'Gagal');
      loading = false;
    }
  }
</script>

<svelte:head><title>Checkout — MPSI</title></svelte:head>

<div class="container-x py-8">
  <h1 class="section-title mb-8">Checkout</h1>

  <div class="grid lg:grid-cols-[1fr_400px] gap-8 items-start">
    <div class="space-y-6">
      <div class="card">
        <h3 class="font-semibold mb-4 flex items-center gap-2"><Icon name="truck" size={16} class="text-ink-500" /> Alamat Pengiriman</h3>
        <div class="grid sm:grid-cols-2 gap-4">
          <div><label class="label">Nama Penerima</label><input class="input" bind:value={recipient} required /></div>
          <div><label class="label">Nomor HP</label><input class="input" bind:value={phone} placeholder="0812xxxxxxxx" required /></div>
        </div>
        <div class="mt-4"><label class="label">Kota</label>
          <select class="input" bind:value={city}>{#each CITIES as c}<option>{c}</option>{/each}</select>
        </div>
        <div class="mt-4"><label class="label">Alamat Lengkap</label><textarea class="input" rows={3} bind:value={full} required /></div>
        <div class="mt-4"><label class="label">Catatan (opsional)</label><textarea class="input" rows={2} bind:value={notes} /></div>
      </div>

      <div class="card">
        <h3 class="font-semibold mb-4">Pengiriman</h3>
        <div class="grid sm:grid-cols-2 gap-3">
          {#each COURIERS as c}
            <button on:click={() => courier = c} class="text-left p-4 border rounded-2xl transition" class:border-ink-950={courier.name===c.name} class:bg-ink-50={courier.name===c.name} class:border-ink-200={courier.name!==c.name}>
              <div class="flex justify-between items-center"><b>{c.name}</b><span class="text-sm font-semibold">{fmtRp(c.cost)}</span></div>
              <div class="text-xs text-ink-500 mt-0.5">Estimasi {c.eta}</div>
            </button>
          {/each}
        </div>
      </div>

      <div class="card">
        <h3 class="font-semibold mb-4">Metode Pembayaran</h3>
        {#each Object.entries(grouped) as [g, ms]}
          <div class="mb-5">
            <h4 class="text-xs font-semibold uppercase tracking-widest text-ink-500 mb-2">{g}</h4>
            <div class="grid sm:grid-cols-2 gap-2">
              {#each ms as m}
                <button on:click={() => pay = m.code} class="text-left flex items-center gap-3 p-3 border rounded-2xl transition" class:border-ink-950={pay===m.code} class:bg-ink-50={pay===m.code} class:border-ink-200={pay!==m.code}>
                  <div class="w-10 h-10 rounded-lg grid place-items-center text-white text-xs font-bold" style:background={m.color}>{m.code.slice(0,3)}</div>
                  <div class="flex-1 min-w-0">
                    <b class="text-sm block truncate">{m.name}</b>
                    <small class="text-xs text-ink-500">Biaya: {m.fee_pct ? m.fee_pct + '%' : fmtRp(m.fee_flat)}</small>
                  </div>
                </button>
              {/each}
            </div>
          </div>
        {/each}
      </div>

      <div class="card">
        <h3 class="font-semibold mb-3">Item ({items.length})</h3>
        {#each items as it (it.product_id)}
          <div class="flex items-center gap-3 py-2 border-b border-ink-100 last:border-0">
            <img src={it.image} alt="" class="w-12 h-12 rounded-lg object-cover" />
            <div class="flex-1 min-w-0">
              <div class="text-sm font-medium line-clamp-1">{it.name}</div>
              <div class="text-xs text-ink-500">{it.qty} × {fmtRp(it.price)}</div>
            </div>
            <b class="text-sm">{fmtRp(it.price * it.qty)}</b>
          </div>
        {/each}
      </div>
    </div>

    <aside class="lg:sticky lg:top-24">
      <div class="card">
        <h3 class="font-semibold mb-4">Ringkasan Pembayaran</h3>
        <div class="space-y-2 text-sm">
          <div class="flex justify-between"><span class="text-ink-500">Subtotal</span><span>{fmtRp(subtotal)}</span></div>
          <div class="flex justify-between"><span class="text-ink-500">Ongkir</span><span>{fmtRp(courier.cost)}</span></div>
          {#if insurance > 0}<div class="flex justify-between"><span class="text-ink-500">Asuransi</span><span>{fmtRp(insurance)}</span></div>{/if}
          <div class="flex justify-between"><span class="text-ink-500">Biaya admin{#if method}<span class="text-ink-400"> ({method.name})</span>{/if}</span><span>{method ? fmtRp(fee) : '-'}</span></div>
          <div class="flex justify-between text-base font-bold pt-3 border-t border-ink-100 mt-3">
            <span>Total</span><span>{fmtRp(total)}</span>
          </div>
        </div>
        <button on:click={submit} disabled={loading || !pay} class="btn-primary btn-lg w-full mt-5">
          <Icon name="lock" size={14} /> {loading ? 'Memproses…' : pay ? `Bayar ${fmtRp(total)}` : 'Pilih metode pembayaran'}
        </button>
        <div class="flex items-start gap-2 mt-4 text-xs text-ink-500">
          <Icon name="shield-check" size={14} class="text-emerald-600 mt-0.5 shrink-0" />
          <span>Transaksi diproses dengan enkripsi end-to-end.</span>
        </div>
      </div>
    </aside>
  </div>
</div>
