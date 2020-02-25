<?php

namespace App\Providers;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

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
        /* @markdown($expression), allows markdown, but not html */
        Blade::directive('markdown', function ($expression) {
            return "<?php echo (new \Parsedown())->text(e($expression));  ?>";
        });
    }
}
