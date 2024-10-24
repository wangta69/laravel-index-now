<?php
namespace Pondol\IndexNow;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Blade;

class IndexNowServiceProvider extends ServiceProvider {


  /**
   * Where the route file lives, both inside the package and in the app (if overwritten).
   *
   * @var string
   */

	/**
   * Register any application services.
   *
   * @return void
   */
  public function register()
  {

    if ($this->app->runningInConsole()) {
      $this->commands([
        Console\InstallCommand::class,
      ]);
    }

   

    // $this->app->singleton('mailer', function($app) {
    //   return new Mailer;
    // });
  }

	/**
   * Bootstrap any application services.
   *
   * @return void
   */
	public function boot()
  {

 
    // $this->loadRoutesFrom(__DIR__.'/routes/web.php');
 


    // $this->loadViewsFrom(__DIR__.'/resources/views/mailer', 'mailer');

    // // // set assets
		// $this->publishes([
    //   __DIR__.'/resources/pondol/mailer/' => public_path('pondol/mailer'),
    // ], 'public');

    // $this->publishes([
    //   __DIR__.'/resources/views/mailer/templates' => resource_path('views/mailer/templates'),
    //   __DIR__.'/database/migrations/' => database_path('migrations')
    // ]);

  }


}
