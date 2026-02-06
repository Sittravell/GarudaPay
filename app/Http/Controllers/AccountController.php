<?php

namespace App\Http\Controllers;

use App\Models\Account;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    public function index(Request $request)
    {
        return response()->json([
            'accounts' => Account::where('user_id', $request->user()->id)->get()
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
        ]);

        $user = $request->user();

        $isMain = false;

        if ($user->accounts()->count() === 0) {
            $isMain = true;
        }

        if ($isMain) {
            $user->accounts()->where('is_main', true)->update(['is_main' => false]);
        }

        $account = $user->accounts()->create([
            'name' => $request->name,
            'is_main' => $isMain,
            'balance' => 0,
            'currency' => 'USD',
        ]);

        return response()->json($account, 201);
    }

    public function show($id)
    {
        $account = Account::find($id);

        if (!$account) {
            return response()->json(['message' => 'Account not found'], 404);
        }

        // Check ownership
        if ($account->user_id !== request()->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json($account);
    }

    public function setMain(Request $request)
    {
        $request->validate([
            'account_id' => 'required|exists:accounts,id',
        ]);

        $user = $request->user();
        $account = $user->accounts()->find($request->account_id);

        if (!$account) {
            return response()->json(['message' => 'Account not found or not owned by user'], 404);
        }

        if ($account->is_main) {
            return response()->json(['message' => 'Account is already main'], 200);
        }

        $user->accounts()->where('is_main', true)->update(['is_main' => false]);

        $account->is_main = true;
        $account->save();

        return response()->json(['message' => 'Main account updated successfully', 'account' => $account]);
    }
    public function totalBalance(Request $request)
    {
        $totalBalance = $request->user()->accounts()->sum('balance');

        return response()->json([
            'total_balance' => $totalBalance
        ]);
    }
}
