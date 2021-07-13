<?php

namespace Rikudou\Unleash\Bundle\Event;

use Symfony\Contracts\EventDispatcher\Event;

final class ContextValueNotFoundEvent extends Event
{
    private ?string $value = null;
    private string $contextName;

    public function __construct(string $contextName)
    {
        $this->contextName = $contextName;
    }

    public function getContextName(): string
    {
        return $this->contextName;
    }

    public function getValue(): ?string
    {
        return $this->value;
    }

    /**
     * @return $this
     */
    public function setValue(?string $value)
    {
        $this->value = $value;

        return $this;
    }
}
