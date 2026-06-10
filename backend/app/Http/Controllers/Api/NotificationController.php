<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserNotification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $q = UserNotification::where('user_id', $request->user()->id)->orderByDesc('id');
        if ($request->boolean('unread')) $q->whereNull('read_at');
        return response()->json($q->paginate(30));
    }

    public function unreadCount(Request $request)
    {
        $n = UserNotification::where('user_id', $request->user()->id)->whereNull('read_at')->count();
        return response()->json(['count' => $n]);
    }

    public function markRead(Request $request, $id)
    {
        $n = UserNotification::where('user_id', $request->user()->id)->findOrFail($id);
        if (!$n->read_at) $n->update(['read_at' => now()]);
        return response()->json(['ok' => true]);
    }

    public function markAllRead(Request $request)
    {
        UserNotification::where('user_id', $request->user()->id)->whereNull('read_at')->update(['read_at' => now()]);
        return response()->json(['ok' => true]);
    }

    public function destroy(Request $request, $id)
    {
        UserNotification::where('user_id', $request->user()->id)->findOrFail($id)->delete();
        return response()->json(['ok' => true]);
    }
}
