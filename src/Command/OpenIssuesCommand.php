<?php

declare(strict_types=1);

namespace App\Command;

use App\Model\ComponentCollection;
use App\Model\MissingTranslation;
use App\Service\DataProvider;
use App\Service\VersionProvider;
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

    public function __construct(
        private DataProvider $dataProvider,
        private Client $github,
        private VersionProvider $versionProvider
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $targetBranch = $this->versionProvider->getLowestSupportedVersion();
        foreach ($this->dataProvider->getData($targetBranch) as $language => $componentsCollection) {
            if ($componentsCollection->hasMissingTranslationStrings()) {
                $this->createIssue($language, $componentsCollection, $targetBranch);
            } else {
                $this->closeIssue($componentsCollection);
            }
        }

        return Command::SUCCESS;
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

    private function createIssue(string $language, ComponentCollection $componentCollection, string $targetBranch): void
    {
        $files = '';
        $details = '';
        /** @var MissingTranslation $missingTranslation */
        foreach ($componentCollection as $missingTranslation) {
            if ($missingTranslation->getMissingCount() > 0) {
                $link = sprintf('- [%s](https://github.com/symfony/symfony/blob/%s/%s)', $missingTranslation->getFile(), $targetBranch, $missingTranslation->getFile());
                $files .= $link.\PHP_EOL;
                $details .= $link.\PHP_EOL;
                foreach ($missingTranslation->getMissingTranslations() as $missing) {
                    $details .= sprintf('  - %d: %s', $missing['id'], $missing['source']).\PHP_EOL;
                }
            }
        }

        $body = <<<TXT
Hello,

There are some missing $language translations and we are looking for a **native** speaker to help us out. 

Here is a [short example](https://symfony-translations.nyholm.tech/#pr) of what you need to do. There are 4 rules: 

1. You must be a $language native speaker
2. You must look at the existing translations and follow the same "style" or "tone"
3. You must make your PR to branch $targetBranch
4. You must use the correct indentation (number of spaces)

These are the files that should be updated: 
$files

<details>
<summary>Show strings not translated</summary>
$details
</details>

> [!NOTE]
> If you want to work on this issue, add a comment to assign it to yourself and let others know that this is already taken.

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
