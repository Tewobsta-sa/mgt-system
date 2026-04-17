<?php

use Spatie\Permission\Models\Role;
use App\Models\User;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$roles = ['teacher'];

foreach ($roles as $roleName) {
    if (!Role::where('name', $roleName)->exists()) {
        Role::create(['name' => $roleName, 'guard_name' => 'web']);
        echo "Created role: $roleName\n";
    }
}

// Check for users who might have been intended as teachers (registered recently without roles)
$usersWithoutRoles = User::doesntHave('roles')->get();
foreach ($usersWithoutRoles as $user) {
    $user->assignRole('teacher');
    echo "Assigned 'teacher' role to: {$user->username}\n";
}
