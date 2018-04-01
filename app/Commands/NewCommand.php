<?php

namespace App\Commands;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use LaravelZero\Framework\Commands\Command;
use RuntimeException;
use Symfony\Component\Process\Process;
use ZipArchive;
use Zttp\Zttp;

class NewCommand extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'new
        {name : Please specify a Project Name }
        {--a|app=laravel : PHP Application to create (Laravel|Lumen|Laravel-Zero)}
        {--d|directory=php/application : PHP Application folder to use }
        {--f|force : Forces install even if the directory already exists }
    ';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Create a new Laravel Lambda Project';

    /**
     * Main Application Folder
     *
     * @var string
     */
    protected $mainDirectory;

    /**
     * PHP Application Type
     *
     * @var string
     */
    protected $applicationType;

    /**
     * PHP Application Directory
     *
     * @var string
     */
    protected $applicationDirectory;

    /**
     * Class Constructor
     *
     */
    public function __construct()
    {
        if (!class_exists('ZipArchive')) {
            throw new RuntimeException('The Zip PHP extension is not installed. Please install it and try again.');
        }

        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle(): void
    {
        $mainDirectory = ($this->argument('name')) ? (starts_with($this->argument('name'), "/") ? $this->argument('name') : getcwd() . '/' . $this->argument('name')) : getcwd();
        $applicationType = strtolower(str_ireplace("=", "", $this->option('app')));
        $applicationDirectory = str_ireplace("=", "", $this->option('directory'));

        $this
            ->verifyCanInstallApplication($mainDirectory)

            ->download($zipFile = $this->makeFilename())

            ->extract($zipFile, $tempDirectory = md5(time() . uniqid()), $mainDirectory)

            ->moveFromTempDirectory($tempDirectory, $mainDirectory)

            ->cleanUp($zipFile, $tempDirectory)

            ->preparePhpExecutable($mainDirectory)

            ->installApplication($mainDirectory, $applicationDirectory, $applicationType)

            ->prepareApplication($mainDirectory, $applicationDirectory, $applicationType);
    }

    /**
     * Verify that the application does not already exist.
     *
     * @param  string  $mainDirectory
     * @return void
     */
    protected function verifyCanInstallApplication($mainDirectory): self
    {
        if (!$this->option('force')) {
            if ((is_dir($mainDirectory) || is_file($mainDirectory)) && $mainDirectory != getcwd()) {
                throw new RuntimeException('Application already exists!');
            }
        }

        return $this;
    }

    /**
     * Generate a random temporary filename.
     *
     * @return string
     */
    protected function makeFilename(): string
    {
        return getcwd() . '/php-lambda_' . md5(time() . uniqid()) . '.zip';
    }

    /**
     * Download the temporary Zip to the given file.
     *
     * @param  string  $zipFile
     * @param  string  $version
     * @return $this
     */
    protected function download($zipFile): self
    {
        ini_set('user_agent', 'Mozilla/5.0 (Windows; U; Windows NT 6.0; en-GB; rv:1.9.0.3) Gecko/2008092417 Firefox/3.0.3');

        File::copy(collect(Zttp::get("https://api.github.com/repos/nsouto/php-lambda/releases/latest")->json())->get('zipball_url'), $zipFile);

        return $this;
    }

    /**
     * Extract the Zip file into the given directory.
     *
     * @param  string  $zipFile
     * @param  string  $directory
     * @return $this
     */
    protected function extract($zipFile, $tempDirectory, $mainDirectory): self
    {
        $archive = new ZipArchive;

        $archive->open($zipFile);

        $archive->extractTo($tempDirectory);

        $archive->close();

        return $this;
    }

    /**
     * Move Extracted Files to Project Directory
     *
     * @return self
     */
    protected function moveFromTempDirectory($tempDirectory, $mainDirectory): self
    {
        File::moveDirectory(collect(File::directories($tempDirectory))->first(), $mainDirectory);

        return $this;
    }

    /**
     * Extract the Zip file into the given directory.
     *
     * @param  string  $zipFile
     * @param  string  $directory
     * @return $this
     */
    protected function preparePhpExecutable($mainDirectory): self
    {
        File::chmod($mainDirectory . "/php/bin/php-cgi", 0755);

        return $this;
    }

    /**
     * Clean-up the Zip file.
     *
     * @param  string  $zipFile
     * @return $this
     */
    protected function cleanUp($zipFile, $tempDirectory): self
    {
        File::delete($zipFile);

        File::deleteDirectory($tempDirectory);

        return $this;
    }

    /**
     * @return mixed
     */
    protected function installApplication($mainDirectory, $applicationDirectory, $applicationType): self
    {
        $process = new Process($this->getCommands($mainDirectory, $applicationDirectory, $applicationType)->get('shell')->implode(' && '), $mainDirectory, null, null, null);

        $process->run(function ($type, $line) {
            $this->info($line);
        });

        return $this;
    }

    /**
     * @return mixed
     */
    protected function prepareApplication($mainDirectory, $applicationDirectory, $applicationType): self
    {
        $this->getCommands($mainDirectory, $applicationDirectory, $applicationType)->get('files')->each(function ($fileUpdate) {
            call_user_func_array([$this, 'updateFileContent'], $fileUpdate);
        });

        return $this;
    }

    /**
     * Get commands needed to prepare application.
     *
     * @return Collection
     */
    protected function getCommands($mainDirectory, $applicationDirectory, $applicationType): Collection
    {
        switch ($applicationType) {

            case "laravel":
                return collect([
                    'shell' => collect([
                        'npm install',
                        base_path() . "/vendor/laravel/installer/laravel new " . $applicationDirectory . ($this->option('force') ? " --force " : ""),
                    ]),
                    'files' => collect([
                        [
                            "file" => "{$mainDirectory}/{$applicationDirectory}/.env",
                            "search" => "LOG_CHANNEL=stack",
                            "replace" => "LOG_CHANNEL=syslog",
                        ],
                        [
                            "file" => "{$mainDirectory}/{$applicationDirectory}/.env.example",
                            "search" => "LOG_CHANNEL=stack",
                            "replace" => "LOG_CHANNEL=syslog",
                        ],
                        [
                            "file" => "{$mainDirectory}/{$applicationDirectory}/config/cache.php",
                            "search" => "storage_path('framework/cache/data')",
                            "replace" => "'/tmp/php/storage/framework/cache'",
                        ],
                        [
                            "file" => "{$mainDirectory}/{$applicationDirectory}/config/session.php",
                            "search" => "storage_path('framework/sessions')",
                            "replace" => "'/tmp/php/storage/framework/sessions'",
                        ],
                        [
                            "file" => "{$mainDirectory}/{$applicationDirectory}/config/view.php",
                            "search" => "storage_path('framework/views')",
                            "replace" => "'/tmp/php/storage/framework/views'",
                        ],
                        [
                            "file" => "{$mainDirectory}/{$applicationDirectory}/config/filesystems.php",
                            "search" => "storage_path('app')",
                            "replace" => "'/tmp/php/storage/filesystem'",
                        ],
                        [
                            "file" => "{$mainDirectory}/index.js",
                            "search" => "php/app/public/index.php",
                            "replace" => "{$applicationDirectory}/public/index.php",
                        ],
                    ]),
                ]);
                break;

            case "lumen":
                return collect([
                    'shell' => collect([
                        'npm install',
                        base_path() . "/vendor/laravel/lumen-installer/lumen new " . $applicationDirectory . ($this->option('force') ? " --force " : ""),
                    ]),
                    'files' => collect([
                        [
                            "file" => "{$mainDirectory}/{$applicationDirectory}/.env.example",
                            "search" => "LOG_CHANNEL=stack",
                            "replace" => "LOG_CHANNEL=syslog",
                        ],
                        [
                            "file" => "{$mainDirectory}/index.js",
                            "search" => "php/app/public/index.php",
                            "replace" => "{$applicationDirectory}/public/index.php",
                        ],
                    ]),
                ]);
                break;

            case "laravel-zero":
                return collect([
                    'shell' => collect([
                        'npm install',
                        base_path() . "/vendor/laravel-zero/installer/builds/laravel-zero new " . $applicationDirectory,
                    ]),
                    'files' => collect([
                        [
                            "file" => "{$mainDirectory}/index.js",
                            "search" => "php/app/public/index.php",
                            "replace" => $applicationDirectory . "/" . collect(explode("/", strtolower($applicationDirectory)))->last(),
                        ],
                    ]),
                ]);
                break;
        }
    }

    /**
     * Update Application Files
     *
     * @param $file
     * @param $search
     * @param $replace
     */
    protected function updateFileContent($file, $search, $replace): void
    {
        File::put($file, str_replace($search, $replace, File::get($file)));
    }
}
