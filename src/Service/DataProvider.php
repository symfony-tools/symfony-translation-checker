<?php

declare(strict_types=1);

namespace App\Service;

use App\Command\OpenIssuesCommand;
use App\Exception\InvalidVersionException;
use App\Model\ComponentCollection;
use App\Model\GithubIssue;
use App\Model\MissingTranslation;
use Github\Client;
use Symfony\Component\Intl\Languages;

class DataProvider
{
    private string $dataPath;
    private PathProvider $pathProvider;
    private Client $github;

    public function __construct(string $dataPath, PathProvider $pathProvider, Client $github)
    {
        $this->dataPath = $dataPath;
        $this->pathProvider = $pathProvider;
        $this->github = $github;
    }

    public function getData(string $version): array
    {
        if (!in_array($version, $this->getAvailableVersions())) {
            throw new InvalidVersionException();
        }

        $data = $this->prepareData($version);
        $paginator = new \Github\ResultPager($this->github);
        $issues = $paginator->fetchAll($this->github->search(), 'issues', [sprintf('repo:%s/%s "%s" is:open', OpenIssuesCommand::REPO_ORG, OpenIssuesCommand::REPO_NAME, OpenIssuesCommand::getIssueTitle())]);
        foreach ($issues as $issue) {
            foreach ($data as $language => $componentCollection) {
                if ($issue['title'] === sprintf('Missing translations for %s', $componentCollection->getLanguage())) {
                    $componentCollection->setIssue(GithubIssue::create($issue));
                }
            }
        }

        return $data;
    }

    private function prepareData(string $version): array
    {
        $file = $this->dataPath.sprintf('/%s.json', $version);
        $data = json_decode(file_get_contents($file), true, 512, \JSON_THROW_ON_ERROR);

        $missing = [];
        $available = [];
        foreach ($data['available'] as $name => $rows) {
            $available[$name] = count($rows);
        }

        // Init all locales
        $locales = [];
        foreach ($data['defined'] as $localeData) {
            foreach (array_keys($localeData) as $locale) {
                $locales[$locale] = true;
            }
        }

        // Init missing
        foreach ($data['available'] as $name => $rows) {
            foreach (array_keys($locales) as $locale) {
                $missing[$locale][$name] = $available[$name];
            }
        }

        foreach ($data['defined'] as $name => $localeData) {
            foreach ($localeData as $locale => $rows) {
                // Flip the data so it makes sense to print
                $missing[$locale][$name] = $available[$name] - count($rows);
            }
        }

        $output = [];
        foreach ($missing as $locale => $components) {
            $language = $this->getLanguageName($locale);
            $collectionData = [];
            foreach ($components as $componentCode => $count) {
                $collectionData[] = new MissingTranslation(
                    $this->pathProvider->getPath($componentCode, $locale),
                    $count,
                    $this->pathProvider->getComponentName($componentCode),
                    $locale,
                    $language,
                    $available[$name]
                );
            }
            $output[$language] = new ComponentCollection($collectionData, $language);
        }

        ksort($output);

        return $output;
    }

    private function getLanguageName(string $locale): string
    {
        $str = Languages::getName($locale, 'en');

        return sprintf('%s (%s)', $str, $locale);
    }

    public function getAvailableVersions(): array
    {
        $versions = [];
        foreach (glob($this->dataPath.'/*.json') as $path) {
            $versions[] = substr(basename($path), 0, -5);
        }

        return array_reverse($versions);
    }
}
