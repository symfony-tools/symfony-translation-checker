<?php

declare(strict_types=1);

namespace App\Command;

use App\Model\ComponentCollection;
use App\Model\GithubIssue;
use App\Model\MissingTranslation;
use App\Service\DataProvider;
use App\Service\VersionProvider;
use Github\Client;
use Github\ResultPager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:open-issues')]
final class OpenIssuesCommand extends Command
{
    public const REPO_ORG = 'symfony';
    public const REPO_NAME = 'symfony';

    private const PING_AFTER = '-30 days';
    private const PING_MARKER = '<!-- ping-contributors -->';
    private const PING_MAX_CONTRIBUTORS = 5;

    /**
     * @var array<string, list<string>>
     */
    private array $commitLanguages = [];

    /**
     * @var array<string, true>|null
     */
    private ?array $orgMembers = null;

    /**
     * @var array<string, true>
     */
    private array $pingedContributors = [];

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
                $files .= sprintf('- [%s](https://github.com/symfony/symfony/blob/%s/%s)', $missingTranslation->getFile(), $targetBranch, $missingTranslation->getFile()).\PHP_EOL;

                $details .= \PHP_EOL.'#### '.$missingTranslation->getFile().\PHP_EOL.\PHP_EOL;
                $details .= '| Id | English | Translation | Status |'.\PHP_EOL;
                $details .= '| -- | -- | -- | -- |'.\PHP_EOL;
                foreach ($missingTranslation->getMissingTranslations() as $missing) {
                    $details .= sprintf('| %d | %s | %s | %s |', $missing['id'], str_replace('|', '\|', $missing['source']), null === $missing['trans'] ? '' : str_replace('|', '\|', $missing['trans']), 'needs-review-translation' === $missing['state'] ? 'Needs review' : 'Missing').\PHP_EOL;
                }
                $details .= \PHP_EOL;
            }
        }

        $body = <<<TXT
Hello,

There are some translation work needed for $language and we are looking for a **native** speaker to help us out. 

Here is a [short example](https://symfony-translations.nyholm.tech/#pr) of what you need to do. There are 4 rules: 

1. You must be a $language native speaker
2. You must look at the existing translations and follow the same "style" or "tone"
3. You must make your PR to branch $targetBranch
4. You must use the correct indentation (number of spaces)

These are the files that should be updated: 
$files

<details>
<summary>Show related strings</summary>
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
            }

            if ($issue->getCreatedAt() < new \DateTimeImmutable(self::PING_AFTER)) {
                $this->pingContributors($language, $issue, $componentCollection, $targetBranch);
            }
        }
    }

    private function pingContributors(string $language, GithubIssue $issue, ComponentCollection $componentCollection, string $targetBranch): void
    {
        $comments = (new ResultPager($this->github))->fetchAll($this->github->issues()->comments(), 'all', [self::REPO_ORG, self::REPO_NAME, $issue->getNumber()]);
        foreach ($comments as $comment) {
            if (str_contains((string) $comment['body'], self::PING_MARKER) || new \DateTimeImmutable($comment['created_at']) > new \DateTimeImmutable(self::PING_AFTER)) {
                // Contributors were already pinged, or someone commented recently
                return;
            }
        }

        if (!$contributors = $this->getContributors($componentCollection, $targetBranch)) {
            return;
        }

        $marker = self::PING_MARKER;
        $mentions = implode(', ', array_map(fn (string $login) => '@'.$login, $contributors));
        $body = <<<TXT
$marker
Hello $mentions,

We're still looking for a native speaker to translate the strings listed above.
You contributed to the $language translations in the past. Could you help, or do you know someone who can?

If a pull request is already open for this issue, a review from a native speaker would help too.

Thank you!
TXT;

        $this->github->issues()->comments()->create(self::REPO_ORG, self::REPO_NAME, $issue->getNumber(), ['body' => $body]);
        $this->pingedContributors += array_fill_keys($contributors, true);
    }

    /**
     * Returns the most recent authors of commits that changed the translation files of this language only.
     *
     * @return list<string>
     */
    private function getContributors(ComponentCollection $componentCollection, string $targetBranch): array
    {
        $commits = [];
        $language = '';
        foreach ($componentCollection as $missingTranslation) {
            $language = explode('_', $missingTranslation->getLocale())[0];
            $params = ['sha' => $targetBranch, 'path' => $missingTranslation->getFile(), 'per_page' => 100];
            foreach ($this->github->repo()->commits()->all(self::REPO_ORG, self::REPO_NAME, $params) as $commit) {
                $commits[$commit['sha']] = $commit;
            }
        }
        uasort($commits, fn (array $a, array $b) => $b['commit']['author']['date'] <=> $a['commit']['author']['date']);

        $contributors = [];
        foreach ($commits as $sha => $commit) {
            $login = $commit['author']['login'] ?? null;
            if (null === $login || 'Bot' === $commit['author']['type'] || 1 < \count($commit['parents'])) {
                continue;
            }

            // Organization members already follow these issues, and nobody should be pinged on several issues at once
            if (isset($contributors[$login]) || isset($this->pingedContributors[$login]) || isset($this->getOrgMembers()[$login])) {
                continue;
            }

            if ([$language] !== $this->getCommitLanguages($sha)) {
                continue;
            }

            $contributors[$login] = $login;
            if (self::PING_MAX_CONTRIBUTORS === \count($contributors)) {
                break;
            }
        }

        return array_values($contributors);
    }

    /**
     * @return array<string, true>
     */
    private function getOrgMembers(): array
    {
        if (null === $this->orgMembers) {
            $members = (new ResultPager($this->github))->fetchAll($this->github->organization()->members(), 'all', [self::REPO_ORG]);
            $this->orgMembers = array_fill_keys(array_column($members, 'login'), true);
        }

        return $this->orgMembers;
    }

    /**
     * @return list<string>
     */
    private function getCommitLanguages(string $sha): array
    {
        if (!isset($this->commitLanguages[$sha])) {
            $languages = [];
            foreach ($this->github->repo()->commits()->show(self::REPO_ORG, self::REPO_NAME, $sha)['files'] ?? [] as $file) {
                if (preg_match('{/Resources/translations/[^/]+\.([a-z]++)(?:_[a-zA-Z]++)?\.xlf$}', $file['filename'], $m)) {
                    $languages[$m[1]] = $m[1];
                }
            }
            $this->commitLanguages[$sha] = array_values($languages);
        }

        return $this->commitLanguages[$sha];
    }

    public static function getIssueTitle(string $language = null): string
    {
        if (null === $language) {
            return 'Missing translations for';
        }

        return sprintf('Missing translations for %s', $language);
    }
}
