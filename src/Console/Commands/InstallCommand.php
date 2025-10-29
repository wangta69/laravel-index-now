<?php

namespace Pondol\IndexNow\Console\Commands;

use Illuminate\Console\Command;

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
        return $this->installLaravelIndexNow($type);
    }


    private function installLaravelIndexNow($type)
    {

        \Artisan::call('vendor:publish', [
          '--force' => true,
          '--provider' => 'Pondol\IndexNow\IndexNowServiceProvider'
        ]);

        $this->info("The pondol's laravel index-now installed successfully.");

    }


}
