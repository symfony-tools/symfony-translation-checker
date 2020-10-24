<?php

declare(strict_types=1);


namespace App\Command;

use App\Service\DataProvider;
use App\Service\PathProvider;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Translation\Loader\XliffFileLoader;

class BuildWebsiteCommand extends Command
{
    protected static $defaultName = 'app:build-website';

    private DataProvider $dataProvider;
    private string $defaultVersion;

    public function __construct(DataProvider $dataProvider, string $defaultVersion)
    {
        $this->dataProvider = $dataProvider;
        $this->defaultVersion = $defaultVersion;
        parent::__construct();
    }


    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $versions = $this->dataProvider->getAvailableVersions();
        $outputDir = 'build';
        $parser = \WyriHaximus\HtmlCompress\Factory::construct();
        $kernel = new \App\Kernel('prod', false);

        $domain = 'https://symfony-translations.nyholm.tech';

        $pages = [
            'index.html' => '',
        ];
        foreach ($versions as $version) {
            $pages[$version.'.html'] = '/'.$version;
        }

        foreach ($pages as $file => $url) {
            $request = \Symfony\Component\HttpFoundation\Request::create($domain.$url);
            $response = $kernel->handle($request);
            $compressedHtml = $parser->compress($response->getContent());
            file_put_contents($outputDir.'/'.$file, $compressedHtml);
        }

        return 0;
    }
}
