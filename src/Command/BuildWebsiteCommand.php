<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\DataProvider;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;

#[AsCommand(name: 'app:build-website')]
class BuildWebsiteCommand extends Command
{
    private DataProvider $dataProvider;

    public function __construct(DataProvider $dataProvider)
    {
        $this->dataProvider = $dataProvider;
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $versions = $this->dataProvider->getAvailableVersions();
        $outputDir = 'build';
        $parser = \WyriHaximus\HtmlCompress\Factory::construct();
        (new Filesystem())->remove('var/cache/prod');
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
            if (200 !== $response->getStatusCode()) {
                $output->writeln('Response is not 200');

                return Command::FAILURE;
            }

            $compressedHtml = $parser->compress($response->getContent());
            file_put_contents($outputDir.'/'.$file, $compressedHtml);
        }

        return Command::SUCCESS;
    }
}
