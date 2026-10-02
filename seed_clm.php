<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\ClmPresentationAnalytic;
use App\Models\Document;
use App\Models\User;
use App\Models\Contact;
use App\Models\Activity;

// Just grab any document for the seed to ensure it works
$documents = Document::take(5)->get();
if ($documents->count() == 0) {
    // create a fake document if none exists
    $doc = Document::create([
        'title' => 'E-Detailing Cardio Pro - Tablette',
        'type' => 'E-DETAILING',
        'url' => 'https://example.com/edetailing'
    ]);
    $documents = collect([$doc]);
}

$users = User::take(5)->get();
if ($users->count() == 0) {
    echo "No users found, cannot seed.\n";
    exit;
}

$contacts = Contact::take(10)->get();

// Grab an activity
$activity = Activity::first();
if (!$activity) {
    $activity = Activity::create([
        'user_id' => $users->first()->id,
        'activity_type' => 'visite',
        'statut' => 'realisee',
        'date_activite' => now(),
    ]);
}

for ($i = 0; $i < 50; $i++) {
    $doc = $documents->random();
    $user = $users->random();
    $contact = $contacts->count() > 0 ? $contacts->random() : null;
    
    $duration = rand(60, 600); // 1 min to 10 mins
    $slidesData = [];
    $numSlides = rand(3, 10);
    for ($s = 1; $s <= $numSlides; $s++) {
        $slidesData[] = [
            'slide_index' => $s,
            'duration_seconds' => rand(5, 60)
        ];
    }
    
    ClmPresentationAnalytic::create([
        'activity_id' => $activity->id, 
        'document_id' => $doc->id,
        'user_id' => $user->id,
        'contact_id' => $contact ? $contact->id : null,
        'total_duration_seconds' => $duration,
        'slides_data' => $slidesData,
        'completed' => rand(0, 1) == 1
    ]);
}
echo "Seeded CLM Analytics!\n";
