<?php

namespace Stats4sd\FilamentOdkLink\Forms\Components;

use Filament\Forms\Components\Component;
use Filament\Forms\Components\Concerns\HasName;

class HtmlBlock  extends Component
{
    use HasName;

    protected string $view = 'forms.components.html-block';
    protected mixed $content = null;

    final public function __construct(string $name)
    {
        $this->name($name);
        $this->statePath($name);
    }

    public static function make(string $name): static
    {
        $static = app(static::class, ['name' => $name]);
        $static->configure();

        return $static;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->dehydrated(false);
    }

    public function content(mixed $content): static
    {
        $this->content = $content;

        return $this;
    }

    public function getId(): string
    {
        return parent::getId() ?? $this->getStatePath();
    }

    public function getContent(): mixed
    {
        return $this->evaluate($this->content);
    }
}
