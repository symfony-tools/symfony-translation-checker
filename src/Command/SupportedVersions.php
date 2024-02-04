<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\VersionProvider;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:supported-versions')]
final class SupportedVersions extends Command
{
    public function __construct(
        private VersionProvider $versionProvider
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        foreach ($this->versionProvider->getSupportedVersions() as $version) {
            $output->writeln($version);
        }

        return Command::SUCCESS;
    }
}
