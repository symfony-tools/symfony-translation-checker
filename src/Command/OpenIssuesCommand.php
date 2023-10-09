<?php

declare(strict_types=1);

namespace App\Command;

use App\Model\ComponentCollection;
use App\Model\MissingTranslation;
use App\Service\DataProvider;
use Github\Client;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:open-issues')]
final class OpenIssuesCommand extends Command
{
    public const REPO_ORG = 'symfony';
    public const REPO_NAME = 'symfony';

    private DataProvider $dataProvider;
    private Client $github;
    private string $prTargetBranch;

    public function __construct(DataProvider $dataProvider, Client $github, string $prTargetBranch)
    {
        $this->dataProvider = $dataProvider;
        $this->github = $github;
        parent::__construct();
        $this->prTargetBranch = $prTargetBranch;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        foreach ($this->dataProvider->getData($this->prTargetBranch) as $language => $componentsCollection) {
            if ($componentsCollection->hasMissingTranslationStrings()) {
                $this->createIssue($language, $componentsCollection);
            } else {
                $this->closeIssue($componentsCollection);
            }
        }

        return 0;
    }

    private function closeIssue(ComponentCollection $componentCollection)
    {
        $issue = $componentCollection->getIssue();
        if (null === $issue) {
            // No issue exists
            return;
        } elseif (in_array($issue->getUser(), ['Nyholm', 'carsonbot'])) {
            // Issue exists, lets close it
            $this->github->issues()->update(self::REPO_ORG, self::REPO_NAME, $issue->getNumber(), ['state' => 'closed']);
        }
    }

    private function createIssue(string $language, ComponentCollection $componentCollection): void
    {
        $files = '';
        /** @var MissingTranslation $missingTranslation */
        foreach ($componentCollection as $missingTranslation) {
            if ($missingTranslation->getMissingCount() > 0) {
                $files .= sprintf('- [%s](https://github.com/symfony/symfony/blob/%s/%s)', $missingTranslation->getFile(), $this->prTargetBranch, $missingTranslation->getFile()).\PHP_EOL;
            }
        }

        $targetBranch = $this->prTargetBranch;
        $body = <<<TXT
Hello,

There are some missing translation for $language. These should be added in branch $targetBranch. 
See https://github.com/symfony/symfony/issues/38710 for details and [this page](https://symfony-translations.nyholm.tech/#pr) for an example.

These are the files that should be updated: 
$files

NOTE: If you want to work on this issue, add a comment to assign it to yourself and let others know that this is already taken.

TXT;

        $params = [
            'title' => $this->getIssueTitle($language),
            'labels' => ['Missing translations', 'Help wanted', 'Good first issue'],
            'body' => $body,
        ];

        $issue = $componentCollection->getIssue();
        if (null === $issue) {
            $this->github->issues()->create(self::REPO_ORG, self::REPO_NAME, $params);
        } elseif (in_array($issue->getUser(), ['Nyholm', 'carsonbot'])) {
            if ($body !== $issue->getBody()) {
                // Issue exists, lets update it
                $this->github->issues()->update(self::REPO_ORG, self::REPO_NAME, $issue->getNumber(), $params);
            } elseif ($issue->getUpdatedAt() < new \DateTimeImmutable('-5days')) {
                // TODO ping people
            }
        }
    }

    public static function getIssueTitle(string $language = null): string
    {
        if (null === $language) {
            return 'Missing translations for';
        }

        return sprintf('Missing translations for %s', $language);
    }
}
