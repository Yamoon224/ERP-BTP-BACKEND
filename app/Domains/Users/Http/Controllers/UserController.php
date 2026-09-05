<?php

namespace App\Domains\Users\Http\Controllers;

use App\Domains\Users\Http\Requests\StoreUserRequest;
use App\Domains\Users\Http\Requests\UpdateUserRequest;
use App\Domains\Users\Http\Resources\UserResource;
use App\Domains\Users\Services\UserService;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Administration des comptes et de leurs roles.
 *
 * La separation des taches ne vaut que si quelqu'un peut la maintenir : c'est
 * cet ecran. Il est donc lui-meme protege par une permission distincte
 * (`users.manage`), et non par le seul role d'administrateur.
 */
class UserController extends Controller
{
    public function __construct(private readonly UserService $users) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        return UserResource::collection($this->users->list(
            $request->only('search', 'role', 'sort', 'direction'),
            $request->integer('per_page', 10),
        ));
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $user = $this->users->create($request->validated());

        return (new UserResource($user))->response()->setStatusCode(201);
    }

    public function show(User $user): UserResource
    {
        return new UserResource($this->users->find($user->id));
    }

    public function update(UpdateUserRequest $request, User $user): UserResource
    {
        return new UserResource($this->users->update($user, $request->validated()));
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        $this->users->delete($user, $request->user());

        return response()->json(null, 204);
    }
}
