<?php

declare(strict_types=1);


namespace App\Service;


use App\Exception\InvalidVersionException;
use Symfony\Component\Intl\Languages;

class DataProvider
{
    private string $dataPath;

    public function __construct(string $dataPath)
    {
        $this->dataPath = $dataPath;
    }


    public function getData(string $version): array
    {
        if (!in_array($version, $this->getAvailableVersions())) {
            throw new InvalidVersionException();
        }

        $file = $this->dataPath.sprintf('/%s.json', $version);
        $data = json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);

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

        $translated = $this->translate($missing);
        ksort($translated);
        return $translated;
    }

    private function translate(array $data): array
    {
        $output = [];
        foreach ($data as $locale => $rows) {
            $str = Languages::getName($locale, 'en');
            $output[sprintf('%s (%s)', $str, $locale)] = $rows;
        }

        return $output;
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