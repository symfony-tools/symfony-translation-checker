<?php

declare(strict_types=1);


namespace App\Service;


use App\Exception\InvalidVersionException;
use App\Model\MissingTranslation;
use Symfony\Component\Intl\Languages;

class DataProvider
{
    private string $dataPath;
    private PathProvider $pathProvider;

    public function __construct(string $dataPath, PathProvider $pathProvider)
    {
        $this->dataPath = $dataPath;
        $this->pathProvider = $pathProvider;
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

        $output = [];
        foreach ($missing as $locale => $components) {
            foreach ($components as $componentCode => $count) {
                $language = $this->getLanguageName($locale);
                $output[$language][$componentCode] = new MissingTranslation(
                    $this->pathProvider->getPath($componentCode, $locale),
                    $count,
                    $this->getComponentName($componentCode),
                    $locale,
                    $language,
                    $available[$name]
                );
            }
        }

        ksort($output);

        return $output;
    }

    private function getLanguageName(string $locale): string
    {
        $str = Languages::getName($locale, 'en');

        return sprintf('%s (%s)', $str, $locale);
    }

    private function getComponentName(string $code): string
    {
        return [
            'SecurityCore' => 'Security Core',
            'Validator' => 'Validator Component',
            'Form' => 'Form Component',
        ][$code];
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