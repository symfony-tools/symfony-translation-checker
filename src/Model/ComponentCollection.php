<?php

declare(strict_types=1);

namespace App\Model;

class ComponentCollection implements \Countable, \Iterator
{
    private ?GithubIssue $issue;
    private int $cursor;

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

    public function current()
    {
        return $this->data[$this->cursor];
    }

    public function next()
    {
        ++$this->cursor;
        if ($this->valid()) {
            return $this->data[$this->cursor];
        }

        return false;
    }

    public function key()
    {
        return $this->cursor;
    }

    public function valid()
    {
        return isset($this->data[$this->cursor]);
    }

    public function rewind()
    {
        $this->cursor = 0;
    }

    public function count()
    {
        return count($this->data);
    }
}
