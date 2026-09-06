<?php

namespace App\Http\Controllers\Api;

use App\Enums\VoteChoice;
use App\Http\Controllers\Controller;
use App\Models\Section;
use App\Services\VoteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VoteController extends Controller
{
    public function __construct(private VoteService $votes) {}

    public function store(Request $request, Section $section): JsonResponse
    {
        $this->authorize('vote', $section);
        $data = $request->validate([
            'vote' => ['required', 'in:yes,no'],
            'comment' => ['nullable', 'string', 'max:500'],
        ]);

        $vote = $this->votes->submit(
            $section,
            $request->user(),
            VoteChoice::from($data['vote']),
            $data['comment'] ?? null,
        );

        return response()->json(['data' => $vote], 201);
    }

    public function index(Request $request, Section $section): JsonResponse
    {
        $this->authorize('view', $section);
        $section->load(['votes.user', 'agreement', 'participants']);

        return response()->json(['data' => new \App\Http\Resources\SectionResource($section)]);
    }
}
