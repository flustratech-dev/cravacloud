<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\LinkedAccounts\LinkedAccountSync;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Memberikan akun untuk satu email kepada CravaCloud WA Gateway, saat pemiliknya
 * mencoba masuk di sana dan akunnya belum sampai.
 *
 * Tanpa ini, pelanggan VPS yang sudah ada sebelum penautan menyala disuruh
 * mendaftar ulang di WA Gateway dengan email dan kata sandi yang sebenarnya
 * sudah benar. Yang diberikan hanya hash kata sandi; WA Gateway yang
 * mencocokkan kata sandi yang diketik, jadi kata sandi itu tidak pernah
 * dikirim ke mana pun. Tanda tangannya sudah diperiksa middleware.
 *
 * Akun admin tidak pernah diberikan: admin WA Gateway lahir dari env-nya
 * sendiri, dan hash admin VPS tidak punya urusan berada di aplikasi lain.
 */
class LinkedAccountLookupController extends Controller
{
    public function __invoke(Request $request, LinkedAccountSync $sync): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email', 'max:255'],
        ]);

        if ($validator->fails()) {
            return $this->jawab(422, $validator->errors()->first());
        }

        $user = User::where('email', mb_strtolower(trim($validator->validated()['email'])))->first();

        if (! $user) {
            return $this->jawab(404, 'Akun tidak ditemukan.');
        }

        if ($user->is_admin) {
            return $this->jawab(403, 'Akun admin tidak diberikan ke aplikasi lain.');
        }

        $akun = $sync->payload($user, LinkedAccountSync::MODE_LINK);
        $akun['previous_email'] = null;

        return response()->json(['success' => true, 'data' => $akun]);
    }

    private function jawab(int $status, string $pesan): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => ['message' => $pesan],
        ], $status);
    }
}
