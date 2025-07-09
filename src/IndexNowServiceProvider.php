<?php
namespace Pondol\IndexNow;

use Illuminate\Support\ServiceProvider;
use Pondol\IndexNow\Console\Commands\InstallCommand;

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

  }

	/**
   * Bootstrap any application services.
   *
   * @return void
   */
	public function boot()
  {
    if (!config()->has('pondol-indexnow')) {
      $this->publishes([
        __DIR__ . '/config/pondol-indexnow.php' => config_path('pondol-indexnow.php'),
      ], 'config');  
    } 
      
    $this->mergeConfigFrom(
      __DIR__ . '/config/pondol-indexnow.php',
      'pondol-indexnow'
    );

    $this->commands([
      InstallCommand::class
    ]);
  }
}
