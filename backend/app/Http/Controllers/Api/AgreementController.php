<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AgreementResource;
use App\Models\Agreement;
use App\Services\AgreementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgreementController extends Controller
{
    public function __construct(private AgreementService $agreements) {}

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->agreements->queryForUser($request->user(), $request->all());

        return response()->json([
            'data' => AgreementResource::collection($paginator->getCollection())->resolve(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function show(Request $request, Agreement $agreement): JsonResponse
    {
        $this->authorize('view', $agreement);
        $agreement->load([
            'author',
            'signature',
            'project',
            'sections.participants.user',
            'sections.blocks.files',
            'sections.votes.user',
        ]);

        return response()->json(['data' => new AgreementResource($agreement)]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'deadline' => ['required', 'date', 'after:now'],
            'publish_date' => ['nullable', 'date'],
            'project_id' => ['nullable', 'exists:projects,id'],
        ]);

        $agreement = $this->agreements->create($request->user(), $data);

        return response()->json(['data' => new AgreementResource($agreement)], 201);
    }

    public function update(Request $request, Agreement $agreement): JsonResponse
    {
        $this->authorize('update', $agreement);
        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'deadline' => ['sometimes', 'date'],
            'publish_date' => ['nullable', 'date'],
            'project_id' => ['nullable', 'exists:projects,id'],
        ]);

        return response()->json(['data' => new AgreementResource($this->agreements->update($agreement, $data, $request->user()))]);
    }

    public function destroy(Request $request, Agreement $agreement): JsonResponse
    {
        $this->authorize('delete', $agreement);
        $this->agreements->softDelete($agreement, $request->user());

        return response()->json(['ok' => true]);
    }

    public function publish(Request $request, Agreement $agreement): JsonResponse
    {
        $this->authorize('publish', $agreement);

        return response()->json(['data' => new AgreementResource($this->agreements->publish($agreement, $request->user()))]);
    }

    public function duplicate(Request $request, Agreement $agreement): JsonResponse
    {
        $this->authorize('view', $agreement);

        return response()->json(['data' => new AgreementResource($this->agreements->duplicate($agreement, $request->user()))], 201);
    }

    public function archive(Request $request, Agreement $agreement): JsonResponse
    {
        $this->authorize('update', $agreement);

        return response()->json(['data' => new AgreementResource($this->agreements->archive($agreement, $request->user()))]);
    }

    public function restore(Request $request, Agreement $agreement): JsonResponse
    {
        $this->authorize('delete', $agreement);

        return response()->json(['data' => new AgreementResource($this->agreements->restore($agreement, $request->user()))]);
    }

    public function restart(Request $request, Agreement $agreement): JsonResponse
    {
        $this->authorize('update', $agreement);

        return response()->json(['data' => new AgreementResource($this->agreements->restart($agreement, $request->user()))]);
    }

    public function favorite(Request $request, Agreement $agreement): JsonResponse
    {
        $this->authorize('view', $agreement);
        $favorited = $this->agreements->toggleFavorite($agreement, $request->user());

        return response()->json(['favorited' => $favorited]);
    }

    public function publicShow(string $token): JsonResponse
    {
        $agreement = Agreement::query()
            ->where('public_token', $token)
            ->with(['sections.blocks.files'])
            ->firstOrFail();

        return response()->json(['data' => new AgreementResource($agreement->setRelation('votes', collect()))]);
    }
}
