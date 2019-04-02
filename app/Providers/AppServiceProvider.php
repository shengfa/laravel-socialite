<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Providers\Socialite\FacebookProvider;
use App\Providers\Socialite\LineProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->bootFacebookSocialite();
        $this->bootLineSocialite();
    }

    /**
     * Overwrite Socialite FacebookProvider
     *
     * @return void
     */
    private function bootFacebookSocialite()
    {
        $socialite = $this->app->make('Laravel\Socialite\Contracts\Factory');
        $socialite->extend(
            'facebook',
            function ($app) use ($socialite) {
                $config = $app['config']['services.facebook'];
                return $socialite->buildProvider(FacebookProvider::class, $config);
            }
        );
    }

    /**
     * Create Socialite LineProvider
     *
     * @return void
     */
    private function bootLineSocialite()
    {
        $socialite = $this->app->make('Laravel\Socialite\Contracts\Factory');
        $socialite->extend(
            'line',
            function ($app) use ($socialite) {
                $config = $app['config']['services.line'];
                return $socialite->buildProvider(LineProvider::class, $config);
            }
        );
    }
}
