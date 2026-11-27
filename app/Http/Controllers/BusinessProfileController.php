<?php

namespace App\Http\Controllers;

use App\Models\BillingSetting;
use Illuminate\Http\Request;

class BusinessProfileController extends Controller
{
    public function edit()
    {
        return response()->view('business-profile.edit', [
            'settings' => BillingSetting::current(),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function update(Request $request)
    {
        $request->merge([
            'business_name' => is_string($request->input('business_name'))
                ? trim($request->input('business_name'))
                : $request->input('business_name'),
        ]);

        $data = $request->validate([
            'business_name' => ['required', 'string', 'max:100'],
        ], [
            'business_name.required' => 'Nama usaha wajib diisi.',
            'business_name.string' => 'Nama usaha harus berupa teks.',
            'business_name.max' => 'Nama usaha maksimal 100 karakter.',
        ]);

        $settings = BillingSetting::current();
        $settings->business_name = $data['business_name'];
        $settings->save();

        return redirect()->route('business-profile.edit')
            ->with('status', 'Nama usaha berhasil disimpan.');
    }
}
