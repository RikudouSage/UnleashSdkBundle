<?php

namespace Rikudou\Unleash\Bundle\Event;

use Symfony\Contracts\EventDispatcher\Event;

final class ContextValueNotFoundEvent extends Event
{
    private string $contextName;
    private ?string $value = null;
    public function __construct(string $contextName, ?string $value = null)
    {
        $this->contextName = $contextName;
        $this->value = $value;
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
