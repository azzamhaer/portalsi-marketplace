<script lang="ts">
  import { settings } from '$lib/stores.svelte';
  const sections = [
    { id:'order', title:'Pesanan', items:[
      { q:'Bagaimana cara melacak pesanan saya?', a:'Buka halaman Pesanan Saya, pilih order, klik Detail. Status real-time + nomor resi tersedia.' },
      { q:'Berapa lama pesanan diproses?', a:'Setelah pembayaran berhasil, seller wajib memproses dalam 1×24 jam.' },
      { q:'Bisa membatalkan pesanan?', a:'Ya, selama status masih "Menunggu Pembayaran" — tidak melakukan pembayaran dalam 24 jam akan otomatis dibatalkan.' }
    ]},
    { id:'bayar', title:'Pembayaran', items:[
      { q:'Metode pembayaran apa saja?', a:'16+ metode: VA semua bank besar, OVO, DANA, ShopeePay, LinkAja, QRIS, Alfamart, Indomaret, & Kartu Kredit.' },
      { q:'Berapa biaya pembayaran?', a:'VA Bank Rp 4.000-5.500, e-wallet 1.5-2%, retail Rp 5.000, kartu kredit 2.95%.' },
      { q:'Sudah bayar tapi status pending?', a:'Status update otomatis dalam 1-5 menit. Jika lebih lama, hubungi CS dengan bukti transfer + Order ID.' }
    ]},
    { id:'kirim', title:'Pengiriman', items:[
      { q:'Kurir apa saja?', a:'JNE, J&T Express, SiCepat, AnterAja, GoSend Sameday, Pos Indonesia.' },
      { q:'Estimasi sampai?', a:'Same city: 1-2 hari · Pulau Jawa: 2-4 hari · Luar Jawa: 3-7 hari.' },
      { q:'Gratis Ongkir?', a:'Min. belanja Rp 90.000.' }
    ]},
    { id:'akun', title:'Akun & Toko', items:[
      { q:'Bagaimana jadi seller?', a:'Klik "Buka Toko" di profil → isi form gratis → langsung dapat akses Seller Center.' },
      { q:'Bisa jadi pembeli & penjual sekaligus?', a:'Ya, satu akun bisa dual role.' }
    ]}
  ];
</script>

<svelte:head><title>Pusat Bantuan — MPSI</title></svelte:head>

<div class="container-x py-12 max-w-4xl">
  <div class="text-center mb-12">
    <div class="section-eyebrow mb-2">Bantuan</div>
    <h1 class="font-display text-4xl font-bold tracking-tightest">Bagaimana kami bisa membantu?</h1>
    {#if settings.helpIntro}
      <div class="mt-6 max-w-2xl mx-auto text-left text-sm text-ink-700 bg-ink-50 rounded-2xl p-5 whitespace-pre-line">{settings.helpIntro}</div>
    {/if}
  </div>

  <div class="space-y-10">
    {#each sections as s}
      <section id={s.id}>
        <h2 class="text-xl font-bold tracking-tightest mb-4 pb-3 border-b border-ink-200">{s.title}</h2>
        <div class="space-y-2">
          {#each s.items as it}
            <details class="group border border-ink-100 rounded-2xl p-5 hover:border-ink-200 transition">
              <summary class="cursor-pointer font-medium flex items-center justify-between gap-4 list-none">
                <span>{it.q}</span>
                <span class="w-6 h-6 rounded-full bg-ink-100 grid place-items-center text-xs font-bold group-open:bg-ink-950 group-open:text-white transition">+</span>
              </summary>
              <p class="text-sm text-ink-600 mt-3 leading-relaxed">{it.a}</p>
            </details>
          {/each}
        </div>
      </section>
    {/each}
  </div>
</div>
