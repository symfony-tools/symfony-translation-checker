<?php

declare(strict_types=1);

namespace App\Model;

/**
 * @implements \Iterator<int, MissingTranslation>
 */
class ComponentCollection implements \Countable, \Iterator
{
    private ?GithubIssue $issue;
    private int $cursor;

    /**
     * @param MissingTranslation[] $data
     */
    public function __construct(
        private array $data,
        private string $language
    ) {
        $this->issue = null;
        $this->cursor = 0;
        $this->data = array_values($data);
    }

    public function hasMissingTranslationStrings(): bool
    {
        foreach ($this->data as $item) {
            if ($item->getMissingCount() > 0) {
                return true;
            }
        }

        return false;
    }

    public function getIssue(): ?GithubIssue
    {
        return $this->issue;
    }

    public function setIssue(GithubIssue $issue): void
    {
        $this->issue = $issue;
    }

    public function getLanguage(): string
    {
        return $this->language;
    }

    public function current(): MissingTranslation
    {
        return $this->data[$this->cursor];
    }

    public function next(): void
    {
        ++$this->cursor;
    }

    public function key(): int
    {
        return $this->cursor;
    }

    public function valid(): bool
    {
        return isset($this->data[$this->cursor]);
    }

    public function rewind(): void
    {
        $this->cursor = 0;
    }

    public function count(): int
    {
        return count($this->data);
    }
}
