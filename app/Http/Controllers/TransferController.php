<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use App\Services\CurrencyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TransferController extends Controller
{
    public function transfer(Request $request, CurrencyService $currencyService)
    {
        // "recipient_phone (required), account_id (optional), fund, currency"
        // "uses phone number instead of recipient id"

        $request->validate([
            'recipient_phone' => 'required',
            'recipient_name' => 'nullable|string',
            'account_id' => 'nullable|exists:accounts,id', // Sender account
            'fund' => 'required|numeric|decimal:0,3|gt:0', // "positive only"
            'currency' => 'required|exists:currencies,code',
        ]);

        $user = $request->user();
        $recipientPhone = $request->recipient_phone;
        $inputAmount = (float) $request->fund;
        $currency = $request->currency;

        // Find recipient by phone
        $recipientUser = User::where('phone_number', $recipientPhone)->first();
        if (!$recipientUser) {

            // Create new user
            $recipientUser = User::create([
                'name' => $request->recipient_name ?? "Garuda User",
                'email' => $recipientPhone . '@wrapper.com', // Placeholder
                'phone_number' => $recipientPhone,
                'password' => \Illuminate\Support\Facades\Hash::make(\Illuminate\Support\Str::random(16)),
            ]);

            // Create main account for new user
            $recipientUser->accounts()->create([
                'name' => 'Main Account',
                'is_main' => true,
                'balance' => 0,
                'currency' => 'USD',
            ]);
        }

        if ($user->id == $recipientUser->id) {
            return response()->json(['message' => 'Cannot transfer to self.'], 400);
        }

        // 1. Identify Sender Account
        if ($request->account_id) {
            $senderAccount = $user->accounts()->find($request->account_id);
            if (!$senderAccount) {
                return response()->json(['message' => 'Sender account not found or unauthorized'], 404);
            }
        } else {
            // Use main account
            $senderAccount = $user->accounts()->where('is_main', true)->first();
            if (!$senderAccount) {
                return response()->json(['message' => 'Sender has no main account'], 400);
            }
        }

        // Convert to USD
        try {
            $amountUSD = $currencyService->convert($inputAmount, $currency, 'USD');
        } catch (\Exception $e) {
            return response()->json(['message' => 'Currency conversion failed', 'error' => $e->getMessage()], 400);
        }

        // Check Balance (USD)
        if ($senderAccount->balance < round($amountUSD, 3)) {
            return response()->json(['message' => 'Insufficient funds'], 400);
        }

        // 2. Identify Recipient Account
        // Find main account of recipient
        $recipientAccount = $recipientUser->accounts()->where('is_main', true)->first();

        if (!$recipientAccount) {
            // Fallback: any account?
            $recipientAccount = $recipientUser->accounts()->first();
            if (!$recipientAccount) {
                return response()->json(['message' => 'Recipient has no accounts'], 400);
            }
        }

        // No currency mismatch check needed as all accounts are USD base.

        DB::beginTransaction();

        try {
            // Deduct USD
            $senderAccount->balance -= $amountUSD;
            $senderAccount->save();

            // Add USD
            $recipientAccount->balance += $amountUSD;
            $recipientAccount->save();

            // Record Transaction
            $transaction = Transaction::create([
                'sender_account_id' => $senderAccount->id,
                'recipient_account_id' => $recipientAccount->id,
                'amount' => $amountUSD, // Stored in USD
                'type' => 'transfer',
                'status' => 'completed'
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Transfer successful',
                'transaction' => $transaction,
                'conversion' => [
                    'input_amount' => $inputAmount,
                    'input_currency' => $currency,
                    'converted_amount_usd' => $amountUSD
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Transfer failed', 'error' => $e->getMessage()], 500);
        }
    }
}
