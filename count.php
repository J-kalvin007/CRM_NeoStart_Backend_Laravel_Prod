<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
echo 'DOC_COUNT=' . \App\Models\Document::withoutGlobalScopes()->count() . "\n";
echo 'DOC_SCOPED=' . \App\Models\Document::count() . "\n";
