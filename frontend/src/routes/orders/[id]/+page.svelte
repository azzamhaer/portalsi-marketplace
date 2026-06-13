<script lang="ts">
  import Icon from '$lib/components/Icon.svelte';
  import { fmtRp, statusPill, ORDER_STATUS_LABEL } from '$lib/utils';
  import { toast, confirmDialog } from '$lib/stores.svelte';
  import { apiEndpoints } from '$lib/api';
  import { invalidateAll } from '$app/navigation';
  import { onMount, onDestroy } from 'svelte';

  let { data } = $props();
  const order = $derived(data.order);
  let now = $state(Date.now());
  let busy = $state(false);

  const p = $derived(order.payment);
  const isPaid = $derived(p?.status === 'PAID');
  const isPending = $derived(order.status === 'PENDING_PAYMENT' && p?.status === 'UNPAID');

  let timer: any, poller: any;
  onMount(() => {
    timer = setInterval(() => now = Date.now(), 1000);
    if (isPending) poller = setInterval(refreshSilent, 8000);
  });
  onDestroy(() => { clearInterval(timer); clearInterval(poller); });

  async function refreshSilent() {
    try {
      const r: any = await apiEndpoints.refreshOrder(order.id);
      if (r.changed) await invalidateAll();
    } catch {}
  }
  async function refresh() {
    busy = true;
    try {
      const r: any = await apiEndpoints.refreshOrder(order.id);
      if (r.changed) { toast.success('Status diperbarui'); await invalidateAll(); }
      else toast.info('Belum ada perubahan');
    } catch (e: any) { toast.error(e.message); } finally { busy = false; }
  }
  async function simulate() {
    busy = true;
    try { await apiEndpoints.simulateOrder(order.id); toast.success('Pembayaran disimulasikan'); await invalidateAll(); }
    catch (e: any) { toast.error(e.message); } finally { busy = false; }
  }
  async function markDone() {
    const ok = await confirmDialog.ask({ title: 'Pesanan sudah diterima?', message: 'Status pesanan akan berubah menjadi selesai.', confirmText: 'Sudah diterima' });
    if (!ok) return;
    busy = true;
    try { await apiEndpoints.markOrderDone(order.id); toast.success('Pesanan selesai'); await invalidateAll(); }
    catch (e: any) { toast.error(e.message); } finally { busy = false; }
  }
  let returnReason = $state('');
  let showReturn = $state(false);
  async function requestReturn() {
    if (!returnReason.trim()) { toast.warn('Tulis alasan'); return; }
    const ok = await confirmDialog.ask({ title: 'Ajukan pengembalian?', message: 'Permintaan return akan dikirim ke admin untuk ditinjau.', confirmText: 'Kirim return' });
    if (!ok) return;
    busy = true;
    try { await apiEndpoints.requestReturn(order.id, returnReason); toast.success('Permintaan return dikirim'); showReturn = false; }
    catch (e: any) { toast.error(e.message); } finally { busy = false; }
  }

  function copy(s: string) { navigator.clipboard.writeText(s); toast.success('Disalin'); }

  const remaining = $derived(p ? Math.max(0, new Date(p.expired_at).getTime() - now) : 0);
  const hh = $derived(Math.floor(remaining / 3600000));
  const mm = $derived(Math.floor((remaining % 3600000) / 60000));
  const ss = $derived(Math.floor((remaining % 60000) / 1000));
  const fmt = (n: number) => String(n).padStart(2, '0');
</script>

<svelte:head><title>{order.order_number}</title></svelte:head>

<div class="container-x py-6 sm:py-8">
  <div class="flex items-start sm:items-center justify-between mb-6 flex-wrap gap-3">
    <div>
      <div class="text-xs text-ink-500 mb-1">Pesanan</div>
      <h1 class="font-display text-xl sm:text-2xl font-bold tracking-tightest">{order.order_number}</h1>
    </div>
    <span class="pill {statusPill(order.status)} text-sm !px-3 !py-1">{ORDER_STATUS_LABEL[order.status]}</span>
  </div>

  <div class="grid lg:grid-cols-[1fr_360px] gap-6 lg:gap-8 items-start">
    <div class="space-y-4">
      {#if isPending && p}
        <div class="card flex items-center gap-3 bg-amber-50 border-amber-200">
          <Icon name="clock" size={18} class="text-amber-600" />
          <div class="flex-1">
            <div class="text-sm font-semibold text-amber-900">Selesaikan pembayaran</div>
            <div class="text-xs text-amber-700">Sisa waktu <b class="font-mono">{fmt(hh)}:{fmt(mm)}:{fmt(ss)}</b></div>
          </div>
          <button on:click={refresh} disabled={busy} class="btn-outline btn-sm"><Icon name="refresh-cw" size={12} /> Cek</button>
        </div>
      {/if}

      {#if isPaid}
        <div class="card flex items-center gap-3 bg-emerald-50 border-emerald-200">
          <Icon name="check-circle-2" size={18} class="text-emerald-600" />
          <div>
            <div class="text-sm font-semibold text-emerald-900">Pembayaran berhasil</div>
            <div class="text-xs text-emerald-700">Pesanan sedang diproses penjual.</div>
          </div>
        </div>
      {/if}

      {#if p && !isPaid}
        {#if p.method === 'QRIS'}
          <div class="card text-center">
            <h3 class="font-semibold mb-2">Scan QR untuk Bayar</h3>
            {#if p.qr_string}
              <img src={`https://api.qrserver.com/v1/create-qr-code/?size=400x400&margin=10&data=${encodeURIComponent(p.qr_string)}`} alt="QRIS" class="w-56 sm:w-64 h-56 sm:h-64 mx-auto border border-ink-100 rounded-2xl p-2 bg-white" />
            {:else if p.pay_url}
              <a href={p.pay_url} target="_blank" rel="noreferrer" class="btn-primary btn-md mt-2"><Icon name="external-link" size={14} /> Buka Halaman QRIS</a>
            {/if}
            <div class="mt-3 text-sm">Total: <b class="text-lg">{fmtRp(p.total)}</b></div>
          </div>
        {:else if p.pay_code}
          <div class="card border-2 border-dashed border-ink-300">
            <div class="text-xs text-ink-500 mb-1">{p.method.endsWith('VA') ? 'Nomor Virtual Account' : 'Kode Pembayaran'} ({p.method_name})</div>
            <div class="font-mono text-2xl sm:text-3xl font-bold tracking-wider mb-3 break-all">{p.pay_code}</div>
            <button on:click={() => copy(p.pay_code)} class="btn-outline btn-sm"><Icon name="copy" size={12} /> Salin</button>
            <div class="mt-4 text-sm">Total: <b>{fmtRp(p.total)}</b></div>
          </div>
        {:else if p.pay_url}
          <div class="card bg-blue-50">
            <p class="text-sm mb-3">Klik tombol di bawah untuk lanjut ke pembayaran <b>{p.method_name}</b>:</p>
            <a href={p.pay_url} target="_blank" rel="noreferrer" class="btn-primary btn-md"><Icon name="external-link" size={14} /> Buka {p.method_name}</a>
          </div>
        {/if}

        <button on:click={simulate} disabled={busy} class="btn-outline btn-md w-full">Simulasikan Pembayaran (mode dev)</button>
      {/if}

      {#if order.status === 'SHIPPED' && order.tracking_no}
        <div class="card">
          <h3 class="font-semibold mb-2 flex items-center gap-2"><Icon name="truck" size={16} /> Sedang Dikirim</h3>
          <p class="text-sm">Resi: <b class="font-mono">{order.tracking_no}</b> · {order.courier_name}</p>
          <button on:click={markDone} disabled={busy} class="btn-primary btn-md mt-3">Pesanan Diterima</button>
        </div>
      {/if}

      {#if order.status === 'DONE'}
        <div class="card">
          {#if !showReturn}
            <button on:click={() => showReturn = true} class="btn-outline btn-md"><Icon name="undo-2" size={14} /> Ajukan Pengembalian</button>
          {:else}
            <h3 class="font-semibold mb-3">Ajukan Pengembalian</h3>
            <textarea bind:value={returnReason} class="input mb-3" rows={3} placeholder="Tuliskan alasan pengembalian"></textarea>
            <div class="flex gap-2">
              <button on:click={requestReturn} disabled={busy} class="btn-primary btn-md">Kirim Permintaan</button>
              <button on:click={() => showReturn = false} class="btn-outline btn-md">Batal</button>
            </div>
          {/if}
        </div>
      {/if}

      <div class="card">
        <h3 class="font-semibold mb-4">Detail Pesanan</h3>
        {#each order.items as it (it.id)}
          <div class="flex items-center gap-3 py-2 border-b border-ink-100 last:border-0">
            <img src={it.product_image} alt="" class="w-14 h-14 rounded-xl object-cover" />
            <div class="flex-1 min-w-0">
              <a href={`/product/${it.product_id}`} class="text-sm font-medium hover:text-ink-950 line-clamp-2">{it.product_name}</a>
              <div class="text-xs text-ink-500">{it.quantity} × {fmtRp(it.price)}</div>
            </div>
            <b class="text-sm">{fmtRp(it.price * it.quantity)}</b>
          </div>
        {/each}
        <div class="mt-4 pt-4 border-t border-ink-100 text-sm space-y-2">
          <div class="flex items-start gap-2"><Icon name="map-pin" size={14} class="text-ink-500 mt-0.5 shrink-0" />
            <div>
              <b>{order.address.recipient}</b> ({order.address.phone})<br />
              <span class="text-ink-600">{order.address.full_address}, {order.address.city}</span>
            </div>
          </div>
          <div class="flex items-center gap-2"><Icon name="truck" size={14} class="text-ink-500 shrink-0" /><span>{order.courier_name} · {order.courier_eta}</span></div>
        </div>
      </div>
    </div>

    <aside class="lg:sticky lg:top-24">
      <div class="card">
        <h3 class="font-semibold mb-4">Rincian Tagihan</h3>
        <div class="space-y-2 text-sm">
          <div class="flex justify-between"><span class="text-ink-500">Subtotal</span><span>{fmtRp(order.subtotal)}</span></div>
          <div class="flex justify-between"><span class="text-ink-500">Ongkir</span><span>{fmtRp(order.shipping)}</span></div>
          {#if order.insurance > 0}<div class="flex justify-between"><span class="text-ink-500">Asuransi</span><span>{fmtRp(order.insurance)}</span></div>{/if}
          <div class="flex justify-between"><span class="text-ink-500">Biaya admin</span><span>{fmtRp(order.payment_fee)}</span></div>
          <div class="flex justify-between text-base font-bold pt-3 border-t border-ink-100 mt-3"><span>Total</span><span>{fmtRp(order.total)}</span></div>
        </div>
        <a href="/orders" class="btn-outline btn-md w-full mt-4">Semua Pesanan</a>
      </div>
    </aside>
  </div>
</div>
