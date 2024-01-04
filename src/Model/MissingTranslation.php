<?php

declare(strict_types=1);

namespace App\Model;

class MissingTranslation
{
    public function __construct(
        private string $file,
        private array $missingTranslations,
        private string $componentName,
        private string $locale,
        private string $language,
        private int $totalCount
    ) {
    }

    public function getFile(): string
    {
        return $this->file;
    }

    public function getMissingCount(): int
    {
        return count($this->missingTranslations);
    }

    /**
     * @return array<int, array{id: int, source: string}>
     */
    public function getMissingTranslations(): array
    {
        return $this->missingTranslations;
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
        if (0 === $this->totalCount) {
            return 0;
        }

        return (int) round(100 * ($this->totalCount - $this->getMissingCount()) / $this->totalCount);
    }
}
