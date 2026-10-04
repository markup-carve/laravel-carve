<?php

declare(strict_types=1);

namespace MarkupCarve\LaravelCarve\Service;

final class WikiLinkUrlTemplate
{
    public function __construct(private string $template)
    {
    }

    public function __invoke(string $page): string
    {
        $slug = strtolower(trim($page));
        $slug = (string)preg_replace('/\\s+/', '-', $slug);
        $slug = (string)preg_replace('/[^a-z0-9\\-_\\/]/', '', $slug);
        $slug = (string)preg_replace('/-+/', '-', $slug);

        return str_replace('{page}', $slug, $this->template);
    }
}
