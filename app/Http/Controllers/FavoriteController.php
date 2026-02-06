<?php

namespace App\Http\Controllers;

use App\Models\Favorite;
use App\Models\User;
use Illuminate\Http\Request;

class FavoriteController extends Controller
{
    public function index(Request $request)
    {
        // List favorites with user details
        $user = $request->user();

        // Return list of favorite users
        // "add an api to add and remove user favourites"
        // Return their basic info (name, phone)
        $favorites = $user->favorites()->select('users.id', 'users.name', 'users.phone_number')->get();
        return response()->json($favorites);
    }

    public function store(Request $request)
    {
        $request->validate([
            'phone_number' => 'required|exists:users,phone_number',
        ]);

        $user = $request->user();
        $phone = $request->phone_number;

        if ($user->phone_number === $phone) {
            return response()->json(['message' => 'Cannot add self as favorite'], 400);
        }

        $favoriteUser = User::where('phone_number', $phone)->first();

        // Check if already exists
        if ($user->favorites()->where('favorite_user_id', $favoriteUser->id)->exists()) {
            return response()->json(['message' => 'User already in favorites'], 409);
        }

        $user->favorites()->attach($favoriteUser->id);

        return response()->json(['message' => 'Favorite added successfully', 'user' => $favoriteUser]);
    }

    public function destroy(Request $request)
    {
        // Remove favorite. Identify by phone? or ID? 
        // "api to add and remove"
        // If list returns ID, maybe remove by ID of user?
        // Let's support removing by phone via query or body? Or ID in path.
        // Usually DELETE /favorites/{user_id}.
        // But prompt implies phone based interaction?
        // Let's support JSON body with phone_number for consistency with add?
        // Or route param `favorites/{phone}`.
        // Let's stick to request body for consistency or simple destroy logic.
        // Assuming body: { "phone_number": "..." }

        $request->validate([
            'phone_number' => 'required|exists:users,phone_number',
        ]);

        $user = $request->user();
        $targetUser = User::where('phone_number', $request->phone_number)->first();

        if (!$user->favorites()->where('favorite_user_id', $targetUser->id)->exists()) {
            return response()->json(['message' => 'User not in favorites'], 404);
        }

        $user->favorites()->detach($targetUser->id);

        return response()->json(['message' => 'Favorite removed successfully']);
    }
}
