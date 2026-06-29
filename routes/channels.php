<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Authorization callbacks for private/presence channels. Each callback
| receives the authenticated user and must return true (or an array)
| to authorize the connection.
|
*/

// Admin-only channel for driver tracking events
Broadcast::channel('admin.tracking', function (User $user) {
    return $user->role === 'admin';
});
