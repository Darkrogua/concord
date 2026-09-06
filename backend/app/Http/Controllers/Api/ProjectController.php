<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(['data' => $request->user()->projects()->orderBy('name')->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:255']]);
        $project = Project::query()->create([
            'name' => $data['name'],
            'user_id' => $request->user()->id,
        ]);

        return response()->json(['data' => $project], 201);
    }

    public function update(Request $request, Project $project): JsonResponse
    {
        abort_unless($project->user_id === $request->user()->id, 403);
        $data = $request->validate(['name' => ['required', 'string', 'max:255']]);
        $project->update($data);

        return response()->json(['data' => $project]);
    }

    public function destroy(Request $request, Project $project): JsonResponse
    {
        abort_unless($project->user_id === $request->user()->id, 403);
        $project->delete();

        return response()->json(['ok' => true]);
    }
}
