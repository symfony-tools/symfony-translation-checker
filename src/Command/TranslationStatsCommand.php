<?php

declare(strict_types=1);

namespace App\Command;

use App\Model\Project;
use App\Service\PathProvider;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Translation\Loader\XliffFileLoader;

#[AsCommand(name: 'app:trans-stats')]
final class TranslationStatsCommand extends Command
{
    public function __construct(
        private PathProvider $pathProvider
    ) {
        parent::__construct();
    }

    protected function configure()
    {
        $this->addArgument('path', InputArgument::REQUIRED, 'Path to the symfony/symfony or symfony/ux checkout');
        $this->addOption('project', null, InputOption::VALUE_REQUIRED, 'Project to collect the data of: "symfony" or "ux"', 'symfony');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var string $rootPath */
        $rootPath = $input->getArgument('path');

        $files = null;
        if ('symfony' === Project::fromName($input->getOption('project'))->name) {
            $paths = $this->pathProvider->getComponentPaths();
            $sourceNames = $this->pathProvider->getSourceNames();
        } else {
            [$paths, $sourceNames, $files] = $this->findUxCatalogues($rootPath);
        }

        $validIds = [];
        $definedIds = [];

        $loader = new XliffFileLoader();
        foreach ($paths as $name => $path) {
            $validIds[$name] = [];
            $catalogue = $loader->load($rootPath.'/'.$path.'/'.$sourceNames[$name], 'en');
            $meta = $catalogue->getMetadata() ?? [];
            foreach ($meta as $source => $data) {
                $validIds[$name][$data['id']] = ['id' => $data['id'], 'source' => $source];
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
                $meta = $catalogue->getMetadata() ?? [];
                $messages = $catalogue->all()['messages'] ?? [];
                foreach ($meta as $source => $data) {
                    $definedIds[$name][$locale][$data['id']] = [
                        'id' => $data['id'],
                        'trans' => $messages[$source] ?? '',
                    ] + ($data['target-attributes'] ?? []);
                }
            }
            ksort($definedIds[$name]);
        }

        ksort($validIds);
        ksort($definedIds);

        $data = ['available' => $validIds, 'defined' => $definedIds];
        if (null !== $files) {
            $data['files'] = $files;
        }

        $output->writeln(json_encode($data, \JSON_PRETTY_PRINT));

        return Command::SUCCESS;
    }

    /**
     * Symfony UX packages keep their catalogues in src/<Package>/translations/<domain>.<locale>.xlf.
     *
     * @return array{array<string, string>, array<string, string>, array<string, string>} the paths, the English file names and the file patterns, by package
     */
    private function findUxCatalogues(string $rootPath): array
    {
        $englishFiles = glob($rootPath.'/src/*/translations/*.en.xlf');
        if (!$englishFiles) {
            throw new \RuntimeException(sprintf('No "src/*/translations/*.en.xlf" file found in "%s".', $rootPath));
        }

        $paths = $sourceNames = $files = [];
        foreach ($englishFiles as $englishFile) {
            $package = basename(\dirname($englishFile, 2));
            if (isset($paths[$package])) {
                throw new \LogicException(sprintf('The "%s" package has more than one English catalogue.', $package));
            }

            $paths[$package] = sprintf('src/%s/translations', $package);
            $sourceNames[$package] = basename($englishFile);
            $files[$package] = sprintf('%s/%s.%%s.xlf', $paths[$package], basename($englishFile, '.en.xlf'));
        }

        return [$paths, $sourceNames, $files];
    }
}
