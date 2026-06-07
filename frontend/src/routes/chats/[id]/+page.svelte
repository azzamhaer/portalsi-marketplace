<script lang="ts">
  import { onMount, onDestroy, tick } from 'svelte';
  import Icon from '$lib/components/Icon.svelte';
  import { apiEndpoints, getToken } from '$lib/api';
  import { auth, toast } from '$lib/stores.svelte';
  import { goto } from '$app/navigation';
  import { fmtRp } from '$lib/utils';
  import { page } from '$app/stores';

  let thread = $state<any>(null);
  let loading = $state(true);
  let msg = $state('');
  let sending = $state(false);
  let scroller: HTMLDivElement | null = $state(null);
  let poller: any;

  const id = $derived($page.params.id);

  async function load(scroll = false) {
    try {
      thread = await apiEndpoints.chatThread(+id);
      if (scroll) { await tick(); if (scroller) scroller.scrollTop = scroller.scrollHeight; }
    } catch (e: any) { toast.error(e.message); }
  }

  onMount(async () => {
    if (!getToken()) { goto('/login'); return; }
    await load(true);
    loading = false;
    poller = setInterval(() => load(false), 5000);
  });
  onDestroy(() => clearInterval(poller));

  async function send(e: Event) {
    e.preventDefault();
    if (!msg.trim()) return;
    sending = true;
    try {
      await apiEndpoints.sendMessage(+id, msg.trim());
      msg = '';
      await load(true);
    } catch (e: any) { toast.error(e.message); } finally { sending = false; }
  }
</script>

<svelte:head><title>Chat</title></svelte:head>

<div class="container-x py-4 sm:py-6">
  <a href="/chats" class="inline-flex items-center gap-1 text-sm text-ink-500 hover:text-ink-950 mb-4">
    <Icon name="arrow-left" size={14} /> Semua chat
  </a>

  {#if loading}<div class="card text-center text-ink-500 py-10">Memuat…</div>
  {:else if thread}
    {@const isMyThread = auth.user?.id === thread.user_id}
    {@const otherName = isMyThread ? thread.vendor?.name : thread.user?.name}
    <div class="card !p-0 overflow-hidden flex flex-col" style="height: calc(100vh - 200px); min-height: 500px;">
      <!-- header -->
      <div class="flex items-center gap-3 p-4 border-b border-ink-100">
        <img src={thread.vendor?.avatar} alt="" class="w-10 h-10 rounded-full object-cover" />
        <div class="flex-1 min-w-0">
          <div class="font-semibold text-sm truncate">{otherName}</div>
          {#if thread.product}<a href={`/product/${thread.product.id}`} class="text-xs text-ink-500 truncate hover:text-ink-950">📦 {thread.product.name}</a>{/if}
        </div>
      </div>

      {#if thread.product}
        <a href={`/product/${thread.product.id}`} class="flex items-center gap-3 p-3 mx-4 mt-3 bg-ink-50 rounded-xl hover:bg-ink-100 transition">
          <img src={thread.product.image} alt="" class="w-12 h-12 rounded-lg object-cover" />
          <div class="flex-1 min-w-0">
            <div class="text-xs font-medium line-clamp-1">{thread.product.name}</div>
            <div class="text-sm font-bold">{fmtRp(thread.product.price)}</div>
          </div>
          <Icon name="chevron-right" size={14} class="text-ink-400" />
        </a>
      {/if}

      <!-- messages -->
      <div bind:this={scroller} class="flex-1 overflow-y-auto p-4 space-y-2">
        {#each thread.messages ?? [] as m (m.id)}
          {@const mine = m.sender_user_id === auth.user?.id}
          <div class="flex" class:justify-end={mine}>
            <div class="max-w-[75%]">
              <div class="px-4 py-2 rounded-2xl text-sm" class:bg-app-primary={mine} class:text-app-pfg={mine} class:bg-ink-100={!mine}>
                {m.message}
              </div>
              <div class="text-[10px] text-ink-400 mt-1" class:text-right={mine}>
                {new Date(m.created_at).toLocaleTimeString('id-ID', { hour:'2-digit', minute:'2-digit' })}
              </div>
            </div>
          </div>
        {/each}
        {#if thread.messages?.length === 0}
          <div class="text-center text-ink-400 text-sm py-10">Belum ada pesan. Mulai percakapan!</div>
        {/if}
      </div>

      <!-- input -->
      <form on:submit={send} class="p-3 border-t border-ink-100 flex gap-2">
        <input bind:value={msg} placeholder="Ketik pesan…" class="input flex-1" />
        <button disabled={sending || !msg.trim()} class="btn-primary btn-md !px-4"><Icon name="send" size={16} /></button>
      </form>
    </div>
  {/if}
</div>
