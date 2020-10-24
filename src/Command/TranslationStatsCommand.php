<?php

declare(strict_types=1);


namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Translation\Loader\XliffFileLoader;

class TranslationStatsCommand  extends Command
{
    protected static $defaultName = 'app:trans-stats';

    protected function configure()
    {
        $this->addArgument('path', InputArgument::REQUIRED, 'Path to Symfony src');
    }


    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $paths = [
            'Form Component'=>'src/Symfony/Component/Form/Resources/translations',
            'Security Core'=>'src/Symfony/Component/Security/Core/Resources/translations',
            'Validator Component'=>'src/Symfony/Component/Validator/Resources/translations',
        ];

        $sourceNames = [
            'Form Component'=>'validators.en.xlf',
            'Security Core'=>'security.en.xlf',
            'Validator Component'=>'validators.en.xlf',
        ];

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
                $x = 2;
            }
        }

        $output->writeln(json_encode(['available'=>$validIds, 'defined'=>$definedIds]));
        return 0;
    }
}
