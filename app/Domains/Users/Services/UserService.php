<?php

namespace App\Domains\Users\Services;

use App\Domains\Users\Contracts\UserRepositoryContract;
use App\Domains\Users\Exceptions\UserNotDeletableException;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Administration des comptes.
 *
 * Deux garde-fous vivent ici plutot que dans le controleur, parce qu'ils
 * tiennent au metier et non au transport : on ne supprime pas le compte avec
 * lequel on est connecte, et on ne supprime pas le dernier administrateur. Les
 * deux produiraient un systeme dont personne ne peut plus sortir.
 */
final class UserService
{
    public function __construct(private readonly UserRepositoryContract $users) {}

    /** @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, User>
     */
    public function list(array $filters, int $perPage = 10): LengthAwarePaginator
    {
        return $this->users->paginate($filters, $perPage);
    }

    public function find(string $id): User
    {
        return $this->users->findOrFail($id);
    }

    /** @param  array<string, mixed>  $data */
    public function create(array $data): User
    {
        return $this->users->create($data);
    }

    /** @param  array<string, mixed>  $data */
    public function update(User $user, array $data): User
    {
        return $this->users->update($user, $data);
    }

    /**
     * Mise a jour par l'utilisateur de ses propres informations. Les roles ne
     * sont volontairement pas acceptes : personne ne s'auto-promeut.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateOwnProfile(User $user, array $data): User
    {
        unset($data['roles'], $data['password']);

        return $this->users->update($user, $data);
    }

    /**
     * Changement de mot de passe. Les autres jetons sont revoques : un mot de
     * passe change parce qu'on le croit compromis ne protege de rien si les
     * sessions ouvertes ailleurs survivent.
     */
    public function updateOwnPassword(User $user, string $password, ?string $currentTokenId = null): User
    {
        $user->update(['password' => $password]);

        $user->tokens()
            ->when($currentTokenId !== null, fn ($query) => $query->where('id', '!=', $currentTokenId))
            ->delete();

        return $user->refresh();
    }

    /** @throws UserNotDeletableException */
    public function delete(User $user, User $actor): void
    {
        if ($user->is($actor)) {
            throw UserNotDeletableException::self($user);
        }

        if ($this->isLastAdministrator($user)) {
            throw UserNotDeletableException::lastAdmin($user);
        }

        $this->users->delete($user);
    }

    private function isLastAdministrator(User $user): bool
    {
        if (! $user->hasRole('admin')) {
            return false;
        }

        return User::role('admin')->count() <= 1;
    }
}
