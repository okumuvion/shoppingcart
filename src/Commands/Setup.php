<?php

namespace Eddieodira\Shoppingcart\Commands;

use Config\Services;
use CodeIgniter\CLI\CLI;
use CodeIgniter\Commands\Database\Migrate;
use CodeIgniter\Test\Filters\CITestStreamFilter;
use Eddieodira\Shoppingcart\Commands\Setup\ContentReplacer;

class Setup extends BaseCommand
{
    protected $name        = 'cart:publish';
    protected $description = 'Publish Cart config file to app/Config';

    /**
     * The path to `Eddieodira\Shoppingcart\` src directory.
     *
     * @var string
     */
    protected $sourcePath;

    protected $distPath = APPPATH;
    private ContentReplacer $replacer;

    /**
     * Displays the help for the spark cli script itself.
     */
    public function run(array $params): void
    {
        $this->replacer = new ContentReplacer();

        $this->sourcePath = __DIR__ . '/../';

        $this->publishConfig();
    }

    private function publishConfig(): void
    {
        $this->publishCartConfig();
        $this->runMigrations();
    }

    /**
     * @param string $file     Relative file path like 'Config/Cart.php'.
     * @param array  $replaces [search => replace]
     */
    protected function copyAndReplace(string $file, array $replaces): void
    {
        $path = "{$this->sourcePath}/{$file}";

        $content = file_get_contents($path);

        $content = $this->replacer->replace($content, $replaces);

        $this->writeFile($file, $content);
    }

    private function publishCartConfig(): void
    {
        $file     = 'Config/Cart.php';
        $replaces = [
            'namespace Eddieodira\Shoppingcart\Config'  => 'namespace Config',
            'use CodeIgniter\\Config\\BaseConfig;' => 'use Eddieodira\\Shoppingcart\\Config\\Cart as ShoppingCart;',
            'extends BaseConfig'                   => 'extends ShoppingCart',
        ];

        $this->copyAndReplace($file, $replaces);
    }

    /**
     * Replace for setupHelper()
     *
     * @param string $file     Relative file path like 'Controllers/BaseController.php'.
     * @param array  $replaces [search => replace]
     */
    private function replace(string $file, array $replaces): bool
    {
        $path      = $this->distPath . $file;
        $cleanPath = clean_path($path);

        $content = file_get_contents($path);

        $output = $this->replacer->replace($content, $replaces);

        if ($output === $content) {
            return false;
        }

        if (write_file($path, $output)) {
            $this->write(CLI::color('  Updated: ', 'green') . $cleanPath);

            return true;
        }

        $this->error("  Error updating {$cleanPath}.");

        return false;
    }

     /**
     * Write a file, catching any exceptions and showing a
     * nicely formatted error.
     *
     * @param string $file Relative file path like 'Config/Cart.php'.
     */
    protected function writeFile(string $file, string $content): void
    {
        $path      = $this->distPath . $file;
        $cleanPath = clean_path($path);

        $directory = dirname($path);

        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        if (file_exists($path)) {
            $overwrite = (bool) CLI::getOption('f');

            if (
                ! $overwrite
                && $this->prompt("  File '{$cleanPath}' already exists in destination. Overwrite?", ['n', 'y']) === 'n'
            ) {
                $this->error("  Skipped {$cleanPath}. If you wish to overwrite, please use the '-f' option or reply 'y' to the prompt.");

                return;
            }
        }

        if (write_file($path, $content)) {
            $this->write(CLI::color("  The file is Created: ", 'green') . $cleanPath);
        } else {
            $this->error("  Error creating {$cleanPath}.");
        }
    }

    private function runMigrations(): void
    {
        if (
            $this->prompt('  Run `spark migrate --all` now?', ['y', 'n']) === 'n'
        ) {
            return;
        }

        $command = new Migrate(Services::logger(), Services::commands());

        // This is a hack for testing.
        // @TODO Remove CITestStreamFilter and refactor when CI 4.5.0 or later is supported.
        CITestStreamFilter::registration();
        CITestStreamFilter::addOutputFilter();
        CITestStreamFilter::addErrorFilter();

        $command->run(['all' => null]);

        CITestStreamFilter::removeOutputFilter();
        CITestStreamFilter::removeErrorFilter();

        // Capture the output, and write for testing.
        // @TODO Remove CITestStreamFilter and refactor when CI 4.5.0 or later is supported.
        $output = CITestStreamFilter::$buffer;
        $this->write($output);

        CITestStreamFilter::$buffer = '';
    }
}


