<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChatMessage;
use App\Models\ChatThread;
use App\Models\Vendor;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    /** List threads untuk user yang login (sebagai pembeli ATAU sebagai pemilik vendor) */
    public function index(Request $request)
    {
        $user = $request->user();
        $vendor = $user->vendor;
        $threads = ChatThread::where('user_id', $user->id)
            ->orWhere(function ($q) use ($vendor) {
                if ($vendor) $q->where('vendor_id', $vendor->id);
            })
            ->with(['vendor:id,name,avatar', 'user:id,name', 'product:id,name,image', 'messages' => fn($q) => $q->latest()->limit(1)])
            ->orderByDesc('last_message_at')
            ->get();
        return response()->json($threads);
    }

    public function show(Request $request, $id)
    {
        $thread = ChatThread::with(['vendor:id,name,avatar,user_id', 'user:id,name', 'product:id,name,image,price', 'messages.sender:id,name'])
            ->findOrFail($id);
        $this->authorizeThread($request, $thread);
        // mark messages addressed to current user as read
        $isBuyer = $thread->user_id === $request->user()->id;
        ChatMessage::where('thread_id', $thread->id)
            ->where('sender_type', $isBuyer ? 'SELLER' : 'BUYER')
            ->update(['is_read' => true]);
        return response()->json($thread);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'vendor_id' => 'required|exists:vendors,id',
            'product_id' => 'nullable|exists:products,id',
        ]);
        $vendor = Vendor::findOrFail($data['vendor_id']);
        if ($vendor->user_id === $request->user()->id) {
            return response()->json(['message' => 'Tidak bisa chat ke toko sendiri'], 422);
        }
        $thread = ChatThread::firstOrCreate(
            ['user_id' => $request->user()->id, 'vendor_id' => $vendor->id],
            ['product_id' => $data['product_id'] ?? null, 'last_message_at' => now()]
        );
        if (!empty($data['product_id'])) {
            $thread->product_id = $data['product_id'];
            $thread->save();
        }
        return response()->json($thread);
    }

    public function sendMessage(Request $request, $threadId)
    {
        $data = $request->validate(['message' => 'required|string|max:2000']);
        $thread = ChatThread::findOrFail($threadId);
        $this->authorizeThread($request, $thread);

        $isBuyer = $thread->user_id === $request->user()->id;
        $msg = ChatMessage::create([
            'thread_id' => $thread->id,
            'sender_user_id' => $request->user()->id,
            'sender_type' => $isBuyer ? 'BUYER' : 'SELLER',
            'message' => $data['message'],
        ]);
        $thread->update(['last_message_at' => now()]);
        return response()->json($msg);
    }

    private function authorizeThread(Request $request, ChatThread $thread): void
    {
        $user = $request->user();
        $isBuyer  = $thread->user_id === $user->id;
        $isSeller = $user->vendor && $user->vendor->id === $thread->vendor_id;
        if (!$isBuyer && !$isSeller) abort(403, 'Bukan thread Anda');
    }
}
