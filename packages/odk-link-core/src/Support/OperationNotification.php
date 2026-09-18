<?php

namespace Stats4sd\FilamentOdkLink\Support;

use Illuminate\Support\HtmlString;

final readonly class OperationNotification
{
    public function __construct(
        public string $title,
        public string | HtmlString | null $body = null,
        public string $severity = 'info',
        public ?string $id = null,
        public bool $persistent = false,
        public bool $database = false,
        public bool $broadcast = true,
        public ?string $actionUrl = null,
    ) {}
}
