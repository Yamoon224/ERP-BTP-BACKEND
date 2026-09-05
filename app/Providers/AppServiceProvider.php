<?php

namespace App\Providers;

use App\Models\PersonalAccessToken;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Sanctum instancie son propre modele de jeton : sans cette bascule, il
        // continuerait d'ecrire un identifiant auto-incremente dans une colonne
        // desormais typee UUID.
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);
    }
}
