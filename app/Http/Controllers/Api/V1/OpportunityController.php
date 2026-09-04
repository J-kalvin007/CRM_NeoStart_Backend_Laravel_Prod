<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Opportunity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OpportunityController extends Controller
{
    public function index()
    {
        $opportunities = Opportunity::with(['account', 'product', 'user'])->paginate(15);
        return response()->json($opportunities);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'account_id' => 'required|uuid|exists:accounts,id',
            'product_id' => 'nullable|uuid|exists:products,id',
            'title' => 'required|string|max:255',
            'expected_revenue' => 'required|numeric',
            'probability' => 'required|integer|min:0|max:100',
            'status' => 'required|in:new,qualification,proposal,negotiation,won,lost',
            'expected_close_date' => 'nullable|date',
        ]);

        $data['user_id'] = Auth::id();
        $data['subsidiary_id'] = Auth::user()->subsidiary_id;

        $opportunity = Opportunity::create($data);

        return response()->json($opportunity->load(['account', 'product']), 201);
    }

    public function show(Opportunity $opportunity)
    {
        return response()->json($opportunity->load(['account', 'product', 'user']));
    }

    public function update(Request $request, Opportunity $opportunity)
    {
        $data = $request->validate([
            'status' => 'sometimes|required|in:new,qualification,proposal,negotiation,won,lost',
            'expected_revenue' => 'sometimes|numeric',
            'probability' => 'sometimes|integer|min:0|max:100',
        ]);

        $opportunity->update($data);
        return response()->json($opportunity->load(['account', 'product']));
    }

    public function destroy(Opportunity $opportunity)
    {
        $opportunity->delete();
        return response()->json(null, 204);
    }
}
