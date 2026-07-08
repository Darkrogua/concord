<?php namespace Zen\Chub\Console;

use Illuminate\Console\Command;

use Zen\Chub\Classes\Support\Strings;

/**
 * Patch Command
 *
 * @link https://docs.octobercms.com/4.x/extend/console-commands.html
 */
class PatchCommand extends Command
{
    /**
     * @var string signature for the console command.
     */
    protected $signature = 'chub:patch {dotpath}';

    /**
     * @var string description is the console command description
     */
    protected $description = 'Выполнение скрипта';

    /**
     * handle executes the console command.
     */
    public function handle()
    {
        $dotpath = $this->argument('dotpath');
        $dotpath = "Zen.Chub.Classes.Patches.$dotpath.handle";
        $handler = Strings::make()->dotpathToHandler($dotpath);
        app($handler['class'])->{$handler['method']}();
    }
}
