<?php

declare(strict_types=1);

namespace App\Model;

class GithubIssue
{
    private int $number;
    private string $htmlUrl;
    private string $user;
    private \DateTimeImmutable $updatedAt;

    public static function create(array $data): self
    {
        $model = new self();
        $model->number = (int) $data['number'];
        $model->htmlUrl = (string) $data['html_url'];
        $model->user = (string) $data['user']['login'];
        $model->updatedAt = new \DateTimeImmutable($data['updated_at']);

        return $model;
    }

    public function getNumber(): int
    {
        return $this->number;
    }

    public function getHtmlUrl(): string
    {
        return $this->htmlUrl;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getUser(): string
    {
        return $this->user;
    }
}
