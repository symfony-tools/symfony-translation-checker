<?php

declare(strict_types=1);

namespace App\Model;

class MissingTranslation
{
    private string $file;
    private int $missingCount;
    private string $componentName;
    private string $locale;
    private string $language;
    private int $totalCount;

    public function __construct(string $file, int $missingCount, string $componentName, string $locale, string $language, int $totalCount)
    {
        $this->file = $file;
        $this->missingCount = $missingCount;
        $this->componentName = $componentName;
        $this->locale = $locale;
        $this->language = $language;
        $this->totalCount = $totalCount;
    }

    public function getFile(): string
    {
        return $this->file;
    }

    public function getMissingCount(): int
    {
        return $this->missingCount;
    }

    public function getComponentName(): string
    {
        return $this->componentName;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function getLanguage(): string
    {
        return $this->language;
    }

    public function getTotalCount(): int
    {
        return $this->totalCount;
    }

    public function getPercentDone(): int
    {
        if ($this->totalCount === 0) {
            return 0;
        }

        return (int) round(100*($this->totalCount-$this->missingCount) / $this->totalCount);
    }
}
