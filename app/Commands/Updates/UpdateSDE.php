<?php

namespace EK\Commands\Updates;

use EK\Api\ConsoleCommand;
use Kcs\ClassFinder\Finder\ComposerFinder;
use League\Container\Container;

class UpdateSDE extends ConsoleCommand
{
    protected string $signature = 'update:sde { --skipDownload : Skips downloading the SDE }';
    protected string $description = 'Updates the SDE to the latest version available';

    protected string $sdeUrl = 'https://eve-static-data-export.s3-eu-west-1.amazonaws.com/tranquility/sde.zip';

    protected string $sdeMd5Url = 'https://eve-static-data-export.s3-eu-west-1.amazonaws.com/tranquility/checksum';

    protected string $sdeSqlite = 'https://www.fuzzwork.co.uk/dump/sqlite-latest.sqlite.bz2';

    public function __construct(
        protected Container $container,
        protected \Composer\Autoload\ClassLoader $autoloader
    ) {
        parent::__construct();
    }

    final public function handle(): void
    {
        // We don't need any memory limits where we're going
        ini_set('memory_limit', '-1');

        $cachePath = BASE_DIR . '/resources/cache';

        // Get the MD5
        $this->out('<info>Getting MD5</info>');
        $md5 = file_get_contents($this->sdeMd5Url);

        // Check if the MD5 is the same as the one we have already imported
        $importedMd5 = $this->config->findOne(['key' => 'sdemd5'])->get('value');
        if ($md5 === $importedMd5) {
           $this->out('<info>MD5 is the same, skipping</info>');
           return;
        }

        if ($this->skipDownload === false) {
            // Download and unzip SDE
            $this->out('<info>Downloading SDE</info>');
            if (file_exists("{$cachePath}/sde.zip")) {
                exec("rm {$cachePath}/sde.zip");
            }
            if (file_exists("{$cachePath}/sde")) {
                exec("rm -rf {$cachePath}/sde");
            }
            exec("curl --progress-bar -o {$cachePath}/sde.zip {$this->sdeUrl}");
            exec("unzip -oq {$cachePath}/sde.zip -d {$cachePath}");

            // Download and unpack SQL
            $this->out('<info>Downloading SQL</info>');
            exec("curl --progress-bar -o {$cachePath}/sqlite-latest.sqlite.bz2 {$this->sdeSqlite}");
            if (file_exists("{$cachePath}/sqlite-latest.sqlite")) {
                exec("rm {$cachePath}/sqlite-latest.sqlite");
            }
            exec("bzip2 -d {$cachePath}/sqlite-latest.sqlite.bz2");
        }

        // Load the commands using KCS composer finder
        $finder = new ComposerFinder($this->autoloader);
        $finder->inNamespace('EK\\EVE\\Seeds');

        // Add all the commands found to the container
        foreach ($finder as $className => $reflection) {
            $class = $this->container->get($className);
            $this->out('Importing ' . $class->collectionName . '..');
            $class->ensurePrimaryIndex();
            $itemCount = $class->getItemCount();

            $progressBar = $this->progressBar($itemCount);
            $progressBar->setFormat("%current%/%max% [%bar%] %percent:3s%%\n");
            $progressBar->start();
            $class->execute($progressBar);
            $progressBar->finish();
        }

        // Update the MD5
        $this->config->update([ 'key' => 'sdemd5' ], [ '$set' => [ 'value' => $md5 ] ], [ 'upsert' => true ]);
    }
}
