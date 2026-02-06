<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Transaction;
use App\Services\CurrencyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FundController extends Controller
{
    public function add(Request $request, CurrencyService $currencyService)
    {
        // "requires account id, fund"
        // "convert all amount and fund to USD if not in USD format. Use an api"

        $request->validate([
            'account_id' => 'required|exists:accounts,id',
            'fund' => 'required|numeric|decimal:0,3',
            'currency' => 'nullable|exists:currencies,code', // Optional input currency
        ]);

        $user = $request->user();
        $account = $user->accounts()->find($request->account_id);

        if (!$account) {
            return response()->json(['message' => 'Account not found or not owned by user'], 404);
        }

        $inputAmount = (float) $request->fund;
        $inputCurrency = $request->currency ?? 'USD'; // Default to USD if not provided? Or default to User preference? 
        // Prompt says "Use an api for this [conversion]... we will not use this [user currency] during transfer as we are getting the currency parameter".
        // But for "add funds", prompt was brief. Assuming input currency is passed or default USD.

        // Convert to USD
        try {
            $amountUSD = $currencyService->convert($inputAmount, $inputCurrency, 'USD');
        } catch (\Exception $e) {
            return response()->json(['message' => 'Currency conversion failed', 'error' => $e->getMessage()], 400);
        }

        // Check for negative balance (in USD)
        if ($amountUSD < 0 && ($account->balance + $amountUSD) < 0) {
            return response()->json(['message' => 'Insufficient funds. Balance cannot be negative.'], 400);
        }

        DB::beginTransaction();

        try {
            // Create transaction record
            Transaction::create([
                'recipient_account_id' => $account->id,
                'sender_account_id' => null, // System add/deduct
                'amount' => $amountUSD, // Stored in USD
                'type' => 'add_funds',
                'status' => 'completed'
            ]);

            // Update balance (USD)
            $account->balance += $amountUSD;
            $account->save();

            DB::commit();

            return response()->json([
                'message' => 'Funds added successfully',
                'account' => $account,
                'conversion' => [
                    'input_amount' => $inputAmount,
                    'input_currency' => $inputCurrency,
                    'converted_amount_usd' => $amountUSD
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Transaction failed', 'error' => $e->getMessage()], 500);
        }
    }
}
