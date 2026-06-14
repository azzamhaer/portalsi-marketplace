<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Address;
use Illuminate\Http\Request;

class AddressController extends Controller
{
    public function index(Request $request)
    {
        return response()->json($request->user()->addresses()->orderByDesc('is_default')->get());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'recipient'    => 'required|string|max:255',
            'phone'        => 'required|string|max:20',
            'country'      => 'nullable|string|max:80',
            'province'     => 'required|string|max:255',
            'city'         => 'required|string|max:255',
            'district'     => 'required|string|max:255',
            'village'      => 'required|string|max:255',
            'full_address' => 'required|string',
            'postal_code'  => 'required|string|max:10',
            'address_note' => 'nullable|string|max:1000',
            'latitude'     => 'nullable|numeric',
            'longitude'    => 'nullable|numeric',
            'is_default'   => 'sometimes|boolean',
        ]);
        $data['country'] = $data['country'] ?? 'Indonesia';
        if (!empty($data['is_default'])) {
            $request->user()->addresses()->update(['is_default' => false]);
        }
        $addr = $request->user()->addresses()->create($data);
        return response()->json($addr, 201);
    }

    public function update(Request $request, $id)
    {
        $addr = $request->user()->addresses()->findOrFail($id);
        $data = $request->validate([
            'recipient'    => 'sometimes|string|max:255',
            'phone'        => 'sometimes|string|max:20',
            'country'      => 'nullable|string|max:80',
            'province'     => 'sometimes|string|max:255',
            'city'         => 'sometimes|string|max:255',
            'district'     => 'sometimes|string|max:255',
            'village'      => 'sometimes|string|max:255',
            'full_address' => 'sometimes|string',
            'postal_code'  => 'sometimes|string|max:10',
            'address_note' => 'nullable|string|max:1000',
            'latitude'     => 'nullable|numeric',
            'longitude'    => 'nullable|numeric',
            'is_default'   => 'sometimes|boolean',
        ]);
        if (array_key_exists('country', $data) && !$data['country']) $data['country'] = 'Indonesia';
        if (!empty($data['is_default'])) {
            $request->user()->addresses()->update(['is_default' => false]);
        }
        $addr->update($data);
        return response()->json($addr);
    }

    public function destroy(Request $request, $id)
    {
        $addr = $request->user()->addresses()->findOrFail($id);
        $addr->delete();
        return response()->json(['ok' => true]);
    }
}
