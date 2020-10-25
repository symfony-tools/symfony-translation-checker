<?php

declare(strict_types=1);

namespace App\Command;

use App\Model\MissingTranslation;
use App\Service\DataProvider;
use Github\Client;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class OpenIssuesCommand extends Command
{
    protected static $defaultName = 'app:open-issues';
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
        foreach ($this->getMissingLanguages() as $language => $branches) {
            $this->createIssue($language, $branches);

            // Avoid rate limits
            sleep(3);
        }

        return 0;
    }

    /**
     * @param MissingTranslation[] $missingTranslations
     */
    private function createIssue(string $language, array $missingTranslations): void
    {
        $files = '';
        foreach ($missingTranslations as $missingTranslation) {
            $files .= sprintf('- [%s](https://github.com/symfony/symfony/blob/%s/%s)', $missingTranslation->getFile(), $this->prTargetBranch, $missingTranslation->getFile()).\PHP_EOL;
        }

        $body = <<<TXT
Hello,

There are some missing translation for $language. These should be added in branch 3.4. 
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

        $issues = $this->github->search()->issues(sprintf('repo:%s/%s "%s" is:open', self::REPO_ORG, self::REPO_NAME, $this->getIssueTitle($language)));
        if (0 === $issues['total_count']) {
            $this->github->issues()->create(self::REPO_ORG, self::REPO_NAME, $params);
        } elseif (1 === $issues['total_count'] && 'Nyholm' === $issues['items'][0]['user']['login']) {
            // Issue exists, lets update it
            $updatedAt = new \DateTime($issues['items'][0]['updated_at']);
            if ($updatedAt < new \DateTime('-10days')) {
                $this->github->issues()->update(self::REPO_ORG, self::REPO_NAME, $issues['items'][0]['number'], $params);
            }
        }
    }

    private function getIssueTitle(string $language): string
    {
        return sprintf('Missing translations for %s', $language);
    }

    private function getMissingLanguages(): array
    {
        $localesWithMissing = [];

        $data = $this->dataProvider->getData($this->prTargetBranch);
        foreach ($data as $language => $components) {
            foreach ($components as $component) {
                if ($component->getMissingCount() > 0) {
                    $localesWithMissing[$language][] = $component;
                }
            }
        }

        return $localesWithMissing;
    }
}
