<?php

use Illuminate\Support\Facades\DB;

DB::statement("ALTER TABLE activities MODIFY COLUMN statut ENUM('planifiee', 'validee', 'en_cours', 'realisee', 'manquee', 'annulee', 'rejetee', 'brouillon', 'en_attente') NOT NULL DEFAULT 'planifiee'");

echo "DB Enum updated\n";
