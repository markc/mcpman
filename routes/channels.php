<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// MCP Process monitoring channels
Broadcast::channel('mcp-processes', function () {
    return true; // Public channel - all users can monitor processes
});

Broadcast::channel('mcp-processes.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});
