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

final class DataProvider
{
    public function __construct(
        private string $dataPath,
        private PathProvider $pathProvider,
        private Client $github
    ) {
    }

    /**
     * @return array<string, ComponentCollection>
     */
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

    /**
     * @return array<string, ComponentCollection>
     */
    private function prepareData(string $version): array
    {
        $file = $this->dataPath.sprintf('/%s.json', $version);
        $data = json_decode(file_get_contents($file), true, 512, \JSON_THROW_ON_ERROR);

        $missing = [];
        $available = [];
        foreach ($data['available'] as $name => $rows) {
            $available[$name] = array_keys($rows);
        }

        // Init all locales
        $locales = [];
        foreach ($data['defined'] as $localeData) {
            foreach (array_keys($localeData) as $locale) {
                $locales[$locale] = true;
            }
        }

        // Init $missing for each locale
        foreach (array_keys($locales) as $locale) {
            foreach ($data['available'] as $name => $rows) {
                $missing[$locale][$name] = [];
                foreach ($rows as $id => $translation) {
                    if (!isset($data['defined'][$name][$locale][$id])) {
                        $missing[$locale][$name][$id] = [
                            'id' => $id,
                            'source' => $translation['source'],
                        ];
                    }
                }
            }
        }

        $output = [];
        foreach ($missing as $locale => $components) {
            $language = $this->getLanguageName($locale);
            $collectionData = [];
            foreach ($components as $componentCode => $rows) {
                $collectionData[] = new MissingTranslation(
                    $this->pathProvider->getPath($componentCode, $locale),
                    $rows,
                    $this->pathProvider->getComponentName($componentCode),
                    $locale,
                    $language,
                    count($available[$componentCode]),
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
