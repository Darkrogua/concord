<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SectionResource;
use App\Models\Agreement;
use App\Models\InformationBlock;
use App\Models\Section;
use App\Models\UserGroup;
use App\Services\SectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SectionController extends Controller
{
    public function __construct(private SectionService $sections) {}

    public function index(Agreement $agreement): JsonResponse
    {
        $this->authorize('view', $agreement);
        $agreement->load(['sections.participants.user', 'sections.blocks.files', 'sections.votes.user']);

        return response()->json(['data' => SectionResource::collection($agreement->sections)]);
    }

    public function store(Request $request, Agreement $agreement): JsonResponse
    {
        $this->authorize('update', $agreement);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'order' => ['nullable', 'integer'],
            'show_results_before_vote' => ['boolean'],
            'participants_see_each_other' => ['boolean'],
        ]);

        $section = $this->sections->create($agreement, $data);

        return response()->json(['data' => new SectionResource($section->load(['participants', 'blocks', 'votes']))], 201);
    }

    public function show(Section $section): JsonResponse
    {
        $this->authorize('view', $section);
        $section->load(['agreement', 'participants.user', 'blocks.files', 'votes.user']);

        return response()->json(['data' => new SectionResource($section)]);
    }

    public function update(Request $request, Section $section): JsonResponse
    {
        $this->authorize('update', $section);
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'order' => ['sometimes', 'integer'],
            'show_results_before_vote' => ['boolean'],
            'participants_see_each_other' => ['boolean'],
            'reset_votes' => ['boolean'],
        ]);

        $reset = $data['reset_votes'] ?? true;
        unset($data['reset_votes']);

        return response()->json([
            'data' => new SectionResource($this->sections->update($section, $data, $request->user(), $reset)),
        ]);
    }

    public function destroy(Section $section): JsonResponse
    {
        $this->authorize('update', $section);
        $this->sections->delete($section);

        return response()->json(['ok' => true]);
    }

    public function duplicate(Section $section): JsonResponse
    {
        $this->authorize('update', $section);

        return response()->json(['data' => new SectionResource($this->sections->duplicate($section))], 201);
    }

    public function addParticipants(Request $request, Section $section): JsonResponse
    {
        $this->authorize('update', $section);
        $data = $request->validate([
            'participants' => ['required', 'array', 'min:1'],
            'participants.*.user_id' => ['required', 'exists:users,id'],
            'participants.*.signature_id' => ['required', 'exists:signatures,id'],
        ]);

        $this->sections->addParticipants($section, $data['participants']);

        return response()->json(['data' => new SectionResource($section->fresh(['participants.user', 'blocks', 'votes', 'agreement']))]);
    }

    public function addFromGroup(Request $request, Section $section): JsonResponse
    {
        $this->authorize('update', $section);
        $data = $request->validate([
            'user_group_id' => ['required', 'exists:user_groups,id'],
        ]);

        $group = UserGroup::query()->with('users.signatures')->findOrFail($data['user_group_id']);
        abort_unless($group->user_id === $request->user()->id, 403);

        $items = $group->users->map(fn ($user) => [
            'user_id' => $user->id,
            'signature_id' => $user->activeSignature()?->id ?? $user->signatures->first()?->id,
        ])->filter(fn ($item) => $item['signature_id'])->all();

        $this->sections->addParticipants($section, $items);

        return response()->json(['data' => new SectionResource($section->fresh(['participants.user', 'blocks', 'votes', 'agreement']))]);
    }

    public function removeParticipant(Section $section, int $userId): JsonResponse
    {
        $this->authorize('update', $section);
        $this->sections->removeParticipant($section, $userId);

        return response()->json(['ok' => true]);
    }

    public function storeBlock(Request $request, Section $section): JsonResponse
    {
        $this->authorize('update', $section);
        $data = $request->validate([
            'type' => ['required', 'in:text,gallery,files,links,code'],
            'title' => ['nullable', 'string', 'max:255'],
            'content' => ['nullable', 'array'],
            'order' => ['nullable', 'integer'],
        ]);

        return response()->json(['data' => $this->sections->createBlock($section, $data)], 201);
    }

    public function updateBlock(Request $request, InformationBlock $informationBlock): JsonResponse
    {
        $this->authorize('update', $informationBlock->section);
        $data = $request->validate([
            'type' => ['sometimes', 'in:text,gallery,files,links,code'],
            'title' => ['nullable', 'string', 'max:255'],
            'content' => ['nullable', 'array'],
            'order' => ['sometimes', 'integer'],
            'reset_votes' => ['boolean'],
        ]);
        $reset = $data['reset_votes'] ?? true;
        unset($data['reset_votes']);

        return response()->json(['data' => $this->sections->updateBlock($informationBlock, $data, $request->user(), $reset)]);
    }

    public function destroyBlock(InformationBlock $informationBlock): JsonResponse
    {
        $this->authorize('update', $informationBlock->section);
        $informationBlock->delete();

        return response()->json(['ok' => true]);
    }
}
