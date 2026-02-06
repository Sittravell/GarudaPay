<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TransactionController extends Controller
{
    public function index(Request $request)
    {
        // "requires account id, pagination settings (user can set per page up to 50 (default 50) )"

        $request->validate([
            'account_id' => 'required|exists:accounts,id',
            'per_page' => 'nullable|integer|max:50|min:1',
            'page' => 'nullable|integer|min:1',
        ]);

        $user = $request->user();
        $account = $user->accounts()->find($request->account_id);

        if (!$account) {
            return response()->json(['message' => 'Account not found or unauthorized'], 404);
        }

        $perPage = $request->per_page ?? 50;

        // Transactions where account is sender OR recipient
        $transactions = Transaction::with(['sender.user', 'recipient.user'])
            ->where(function ($query) use ($account) {
                $query->where('sender_account_id', $account->id)
                    ->orWhere('recipient_account_id', $account->id);
            })
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        $transactions->through(function ($transaction) use ($account) {
            // Direction Logic
            if ($transaction->sender_account_id == $account->id) {
                $transaction->direction = 'out'; // Outflow
                $transaction->signed_amount = '-' . number_format($transaction->amount, 3, '.', '');
            } else {
                $transaction->direction = 'in'; // Inflow
                $transaction->signed_amount = '+' . number_format($transaction->amount, 3, '.', '');
            }

            // Enrich with Names and IDs
            // Use optional chaining or checks in case user/account is deleted (though soft deletes might be better, strict checks here are safe)
            $transaction->sender_name = $transaction->sender && $transaction->sender->user ? $transaction->sender->user->name : 'Unknown';
            $transaction->sender_id = $transaction->sender && $transaction->sender->user ? $transaction->sender->user->id : null;
            $transaction->sender_account_name = $transaction->sender ? $transaction->sender->name : 'Unknown';

            $transaction->recipient_name = $transaction->recipient && $transaction->recipient->user ? $transaction->recipient->user->name : 'Unknown';
            $transaction->recipient_id = $transaction->recipient && $transaction->recipient->user ? $transaction->recipient->user->id : null;
            $transaction->recipient_account_name = $transaction->recipient ? $transaction->recipient->name : 'Unknown';

            return $transaction;
        });

        return response()->json($transactions);
    }

    public function repeat(Request $request, $id)
    {
        $user = $request->user();
        $originalTransaction = Transaction::findOrFail($id);

        if ($originalTransaction->type === 'transfer') {
            // Check if user owns sender account
            $senderAccount = $user->accounts()->find($originalTransaction->sender_account_id);
            if (!$senderAccount) {
                return response()->json(['message' => 'Unauthorized or sender account not found'], 403);
            }

            // Check recipient account exists
            $recipientAccount = Account::find($originalTransaction->recipient_account_id);
            if (!$recipientAccount) {
                return response()->json(['message' => 'Recipient account no longer exists'], 400);
            }

            // Check balance
            if ($senderAccount->balance < $originalTransaction->amount) {
                return response()->json(['message' => 'Insufficient funds'], 400);
            }

            DB::beginTransaction();
            try {
                $senderAccount->balance -= $originalTransaction->amount;
                $senderAccount->save();

                $recipientAccount->balance += $originalTransaction->amount;
                $recipientAccount->save();

                $newTransaction = Transaction::create([
                    'sender_account_id' => $senderAccount->id,
                    'recipient_account_id' => $recipientAccount->id,
                    'amount' => $originalTransaction->amount,
                    'type' => 'transfer',
                    'status' => 'completed'
                ]);

                DB::commit();
                return response()->json(['message' => 'Transaction repeated', 'transaction' => $newTransaction]);
            } catch (\Exception $e) {
                DB::rollBack();
                return response()->json(['message' => 'Failed to repeat transaction', 'error' => $e->getMessage()], 500);
            }

        } elseif ($originalTransaction->type === 'add_funds') {
            // Check if user owns recipient account (since they added funds to themselves)
            $recipientAccount = $user->accounts()->find($originalTransaction->recipient_account_id);
            if (!$recipientAccount) {
                return response()->json(['message' => 'Unauthorized or recipient account not found'], 403);
            }

            DB::beginTransaction();
            try {
                $recipientAccount->balance += $originalTransaction->amount;
                $recipientAccount->save();

                $newTransaction = Transaction::create([
                    'sender_account_id' => null,
                    'recipient_account_id' => $recipientAccount->id,
                    'amount' => $originalTransaction->amount,
                    'type' => 'add_funds',
                    'status' => 'completed'
                ]);

                DB::commit();
                return response()->json(['message' => 'Transaction repeated', 'transaction' => $newTransaction]);
            } catch (\Exception $e) {
                DB::rollBack();
                return response()->json(['message' => 'Failed to repeat transaction', 'error' => $e->getMessage()], 500);
            }
        }

        return response()->json(['message' => 'Transaction type not supported for repeat'], 400);
    }
}
