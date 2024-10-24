<?php

namespace Pondol\IndexNow\Console;

use Illuminate\Console\Command;
// use Illuminate\Filesystem\Filesystem;
// use Illuminate\Support\Str;
// use Symfony\Component\Process\PhpExecutableFinder;
// use Symfony\Component\Process\Process;

class InstallCommand extends Command
{
  // use InstallsBladeStack;

  /**
   * The name and signature of the console command.
   *
   * @var string
   */
  protected $signature = 'pondol:install-index-now {type=full}'; // full | only

  /**
   * The console command description.
   *
   * @var string
   */
  protected $description = "Install Pondol's Index Now";


  public function __construct()
  {
    parent::__construct();
  }

  public function handle()
  {
    $type = $this->argument('type');
    return $this->installLaravelMailer($type);
  }


  private function installLaravelMailer($type)
  {

    \Artisan::call('vendor:publish',  [
      '--force'=> true,
      '--provider' => 'Pondol\IndexNow\IndexNowServiceProvider'
    ]);
    
    $this->info("The pondol's laravel index-now installed successfully.");
    
  }


}
