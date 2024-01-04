<?php

declare(strict_types=1);

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use voku\helper\HtmlMin;

#[AsCommand(name: 'app:build-website')]
final class BuildWebsiteCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $outputDir = 'build';
        $parser = \WyriHaximus\HtmlCompress\Factory::constructSmallest();
        $htmlMin = new HtmlMin();
        $htmlMin->doSortHtmlAttributes(false);
        $parser = $parser->withHtmlMin($htmlMin);

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

            $compressedHtml = $parser->compress($response->getContent());
            file_put_contents($outputDir.'/'.$file, $compressedHtml);
        }

        return Command::SUCCESS;
    }
}
