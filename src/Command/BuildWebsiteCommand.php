<?php

declare(strict_types=1);

namespace App\Command;

use Sensiolabs\MinifyBundle\Minifier\MinifierInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;

#[AsCommand(name: 'app:build-website')]
final class BuildWebsiteCommand extends Command
{
    public function __construct(
        private MinifierInterface $minifier,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $outputDir = 'build';

        (new Filesystem())->remove('var/cache/prod');
        $kernel = new \App\Kernel('prod', false);

        $domain = 'https://symfony-translations.nyholm.tech';

        $pages = [
            'index.html' => '',
        ];

        foreach ($pages as $file => $url) {
            $request = \Symfony\Component\HttpFoundation\Request::create($domain.$url);
            $response = $kernel->handle($request);
            if (200 !== $response->getStatusCode()) {
                $output->writeln('Response is not 200');

                return Command::FAILURE;
            }

            $compressedHtml = $this->minifier->minify($response->getContent(), MinifierInterface::TYPE_HTML);
            file_put_contents($outputDir.'/'.$file, $compressedHtml);
        }

        return Command::SUCCESS;
    }
}
