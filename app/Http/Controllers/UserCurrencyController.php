<?php

namespace App\Http\Controllers;

use App\Models\UserCurrency;
use Illuminate\Http\Request;

class UserCurrencyController extends Controller
{
    public function show(Request $request)
    {
        $userCurrency = UserCurrency::firstOrCreate(
            ['user_id' => $request->user()->id],
            ['currency_code' => 'MYR']
        );

        return response()->json($userCurrency);
    }

    public function update(Request $request)
    {
        $request->validate([
            'currency_code' => 'required|exists:currencies,code',
        ]);

        $userCurrency = UserCurrency::updateOrCreate(
            ['user_id' => $request->user()->id],
            ['currency_code' => $request->currency_code]
        );

        return response()->json(['message' => 'Preferred currency updated', 'data' => $userCurrency]);
    }
}
