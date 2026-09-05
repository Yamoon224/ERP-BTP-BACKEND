<?php

namespace App\Domains\Procurement\Http\Controllers;

use App\Domains\Procurement\Http\Requests\StoreProjectRequest;
use App\Domains\Procurement\Http\Requests\UpdateProjectRequest;
use App\Domains\Procurement\Http\Resources\ProjectResource;
use App\Domains\Procurement\Services\ProjectService;
use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProjectController extends Controller
{
    public function __construct(private readonly ProjectService $projectService) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        return ProjectResource::collection($this->projectService->list(
            $request->only('search', 'is_active', 'sort', 'direction'),
            $request->integer('per_page', 10),
        ));
    }

    public function store(StoreProjectRequest $request): JsonResponse
    {
        $project = $this->projectService->create($request->validated());

        return (new ProjectResource($project))->response()->setStatusCode(201);
    }

    public function show(Project $project): ProjectResource
    {
        return new ProjectResource($project);
    }

    public function update(UpdateProjectRequest $request, Project $project): ProjectResource
    {
        return new ProjectResource($this->projectService->update($project, $request->validated()));
    }
}
