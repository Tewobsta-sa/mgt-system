<?php

use App\Models\User;
use App\Models\ProgramType;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Ensure the Young track exists
$youngTrack = ProgramType::find(2);
if (!$youngTrack) {
    echo "Error: Young track (ID 2) not found.\n";
    exit(1);
}

// Get all teachers
$teachers = User::role('teacher')->get();

foreach ($teachers as $teacher) {
    if (!$teacher->programTypes()->where('program_types.id', 2)->exists()) {
        $teacher->programTypes()->attach(2);
        echo "Successfully linked teacher '{$teacher->username}' to Young track.\n";
    } else {
        echo "Teacher '{$teacher->username}' is already linked to Young track.\n";
    }
}
