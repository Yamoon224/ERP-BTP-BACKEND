<?php

namespace App\Domains\Users\Repositories;

use App\Domains\Shared\Support\Sort;
use App\Domains\Users\Contracts\UserRepositoryContract;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class EloquentUserRepository implements UserRepositoryContract
{
    /** @var array<string, string> */
    private const SORTABLE = [
        'id' => 'id',
        'name' => 'name',
        'email' => 'email',
        'created_at' => 'created_at',
    ];

    /** @return LengthAwarePaginator<int, User> */
    public function paginate(array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        return User::query()
            ->with('roles')
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where(
                fn ($subQuery) => $subQuery
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%"),
            ))
            ->when($filters['role'] ?? null, fn ($query, $role) => $query->whereHas(
                'roles',
                fn ($roleQuery) => $roleQuery->where('name', $role),
            ))
            ->tap(fn ($query) => Sort::apply($query, $filters, self::SORTABLE, 'id', 'desc'))
            ->paginate($perPage)
            ->withQueryString();
    }

    public function findOrFail(string $id): User
    {
        return User::with('roles')->findOrFail($id);
    }

    public function create(array $attributes): User
    {
        $roles = $attributes['roles'] ?? [];
        unset($attributes['roles']);

        $user = User::create($attributes);
        $user->syncRoles($roles);

        return $user->load('roles');
    }

    public function update(User $user, array $attributes): User
    {
        if (array_key_exists('roles', $attributes)) {
            $user->syncRoles($attributes['roles']);
            unset($attributes['roles']);
        }

        // Un mot de passe vide dans un formulaire d'edition veut dire « ne
        // change rien », jamais « efface le mot de passe ».
        if (($attributes['password'] ?? null) === null) {
            unset($attributes['password']);
        }

        $user->update($attributes);

        return $user->refresh()->load('roles');
    }

    public function delete(User $user): void
    {
        // Les jetons sont revoques avec le compte : sans cela, une session
        // ouverte survivrait a la suppression de son propriétaire.
        $user->tokens()->delete();
        $user->delete();
    }
}
