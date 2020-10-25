<?php

declare(strict_types=1);

namespace App\Model;

class ComponentCollection implements \Countable, \Iterator
{
    private array $data;
    private ?string $issue;
    private int $cursor;
    private string $language;

    public function __construct(array $data, string $language)
    {
        $this->issue = null;
        $this->cursor = 0;
        $this->data = array_values($data);
        $this->language = $language;
    }

    public function getIssue(): ?string
    {
        return $this->issue;
    }

    public function setIssue(?string $issue): void
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
