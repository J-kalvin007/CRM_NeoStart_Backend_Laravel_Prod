<?php
namespace App\Http\Controllers\Api\V1;
use App\Http\Controllers\Controller;

class ConversationController extends Controller {
    public function index() {
        return response()->json([
            "data" => [
                [
                    "id" => "1",
                    "sender_id" => "u123",
                    "sender_name" => "Manager Jean",
                    "content" => "Bonjour, avez-vous pu voir le Dr. Aka ?",
                    "created_at" => "2026-08-28T09:15:00Z",
                    "is_read" => false
                ]
            ]
        ]);
    }
    public function messages($id) {
        return response()->json([
            "data" => []
        ]);
    }
}
