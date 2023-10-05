<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\PathProvider;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Translation\Loader\XliffFileLoader;

#[AsCommand(name: 'app:trans-stats')]
class TranslationStatsCommand extends Command
{
    private PathProvider $pathProvider;

    public function __construct(PathProvider $dataProvider)
    {
        $this->pathProvider = $dataProvider;
        parent::__construct();
    }

    protected function configure()
    {
        $this->addArgument('path', InputArgument::REQUIRED, 'Path to Symfony src');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $paths = $this->pathProvider->getComponentPaths();
        $sourceNames = $this->pathProvider->getSourceNames();

        $validIds = [];
        $definedIds = [];

        /** @var string $rootPath */
        $rootPath = $input->getArgument('path');
        $loader = new XliffFileLoader();
        foreach ($paths as $name => $path) {
            $validIds[$name] = [];
            $catalogue = $loader->load($rootPath.'/'.$path.'/'.$sourceNames[$name], 'en');
            $meta = $catalogue->getMetadata();
            foreach ($meta as $source => $data) {
                $validIds[$name][] = $data['id'];
            }
        }

        // We have all the valid IDs. Lets see what is missing.
        foreach ($paths as $name => $path) {
            $finder = Finder::create()
                ->in($rootPath.'/'.$path)
                ->name('*.xlf');

            foreach ($finder as $file) {
                $basename = $file->getBasename('.xlf');
                $locale = substr($basename, strrpos($basename, '.') + 1);
                $definedIds[$name][$locale] = [];
                $catalogue = $loader->load($file->getPathname(), $locale);
                $meta = $catalogue->getMetadata();
                foreach ($meta as $source => $data) {
                    $definedIds[$name][$locale][] = $data['id'];
                }
            }
            ksort($definedIds[$name]);
        }

        ksort($validIds);
        ksort($definedIds);

        $output->writeln(json_encode(['available' => $validIds, 'defined' => $definedIds], \JSON_PRETTY_PRINT));

        return 0;
    }
}
