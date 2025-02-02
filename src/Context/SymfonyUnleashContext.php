<?php

namespace Rikudou\Unleash\Bundle\Context;

use Error;
use InvalidArgumentException;
use JetBrains\PhpStorm\Pure;
use ReflectionObject;
use Rikudou\Unleash\Bundle\Event\ContextValueNotFoundEvent;
use Rikudou\Unleash\Bundle\Event\UnleashEvents;
use Rikudou\Unleash\Configuration\Context;
use Rikudou\Unleash\Enum\ContextField;
use Rikudou\Unleash\Enum\Stickiness;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserInterface;

final class SymfonyUnleashContext implements Context
{
    private ?string $currentUserId = null;

    private ?string $ipAddress = null;

    private ?string $sessionId = null;

    /**
     * @param array<string,string> $customProperties
     */
    public function __construct(
        private ?TokenStorageInterface $userTokenStorage,
        private ?string $userIdField,
        private array $customProperties,
        private ?RequestStack $requestStack,
        private ?ExpressionLanguage $expressionLanguage,
        private ?EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function getCurrentUserId(): ?string
    {
        if ($this->currentUserId !== null) {
            return $this->currentUserId;
        }
        $user = $this->getCurrentUser();
        if ($user === null) {
            return null;
        }
        if ($this->userIdField !== null) {
            if (property_exists($user, $this->userIdField)) {
                try {
                    return (string) $user->{$this->userIdField};
                } catch (Error) {
                    // ignore
                }
            }
            $reflection = new ReflectionObject($user);
            $idProperty = $reflection->getProperty($this->userIdField);
            $idProperty->setAccessible(true);

            return (string) $idProperty->getValue($user);
        }

        try {
            return $user->getUserIdentifier();
        } catch (Error) {
            return $user->getUsername();
        }
    }

    public function getIpAddress(): ?string
    {
        if ($this->ipAddress !== null) {
            return $this->ipAddress;
        }
        if ($this->requestStack === null) {
            return null;
        }
        $request = $this->requestStack->getCurrentRequest();
        if ($request === null) {
            return null;
        }

        return $request->getClientIp();
    }

    public function getSessionId(): ?string
    {
        if ($this->sessionId !== null) {
            return $this->sessionId;
        }
        if ($this->requestStack === null) {
            return null;
        }
        $request = $this->requestStack->getCurrentRequest();
        if ($request === null) {
            return null;
        }
        $session = $request->getSession();

        return $session->getId();
    }

    public function getCustomProperty(string $name): string
    {
        if (!$this->hasCustomProperty($name)) {
            if ($this->eventDispatcher !== null) {
                $event = new ContextValueNotFoundEvent($name);
                $this->eventDispatcher->dispatch($event, UnleashEvents::CONTEXT_VALUE_NOT_FOUND);

                $value = $event->getValue();
                if ($value !== null) {
                    return $value;
                }
            }

            throw new InvalidArgumentException("The context doesn't contain property named '{$name}'");
        }

        $value = $this->customProperties[$name];
        if (
            $this->expressionLanguage !== null
            && str_starts_with($value, '>')
        ) {
            $value = substr($value, 1);
            $value = (string) $this->expressionLanguage->evaluate($value, [
                'user' => $this->getCurrentUser(),
            ]);
        } elseif (str_starts_with($value, '\>')) {
            $value = substr($value, 1);
        }

        return $value;
    }

    public function setCustomProperty(string $name, string $value): self
    {
        $this->customProperties[$name] = $value;

        return $this;
    }

    #[Pure]
    public function hasCustomProperty(string $name): bool
    {
        return array_key_exists($name, $this->customProperties);
    }

    public function removeCustomProperty(string $name, bool $silent = true): self
    {
        if (!$this->hasCustomProperty($name) && !$silent) {
            throw new InvalidArgumentException("The context doesn't contain property with name '{$name}'");
        }
        unset($this->customProperties[$name]);

        return $this;
    }

    public function setCurrentUserId(?string $currentUserId): self
    {
        $this->currentUserId = $currentUserId;

        return $this;
    }

    public function setIpAddress(?string $ipAddress): self
    {
        $this->ipAddress = $ipAddress;

        return $this;
    }

    public function setSessionId(?string $sessionId): self
    {
        $this->sessionId = $sessionId;

        return $this;
    }

    public function hasMatchingFieldValue(string $fieldName, array $values): bool
    {
        $fieldValue = $this->findContextValue($fieldName);
        if ($fieldValue === null) {
            return false;
        }

        return in_array($fieldValue, $values, true);
    }

    public function findContextValue(string $fieldName): ?string
    {
        return match ($fieldName) {
            ContextField::USER_ID, Stickiness::USER_ID => $this->getCurrentUserId(),
            ContextField::SESSION_ID, Stickiness::SESSION_ID => $this->getSessionId(),
            ContextField::IP_ADDRESS => $this->getIpAddress(),
            default => $this->hasCustomProperty($fieldName) ? $this->getCustomProperty($fieldName) : null,
        };
    }

    private function getCurrentUser(): ?UserInterface
    {
        if ($this->userTokenStorage === null) {
            return null;
        }
        $token = $this->userTokenStorage->getToken();
        if ($token === null) {
            return null;
        }
        $user = $token->getUser();
        if (!$user instanceof UserInterface) {
            return null;
        }

        return $user;
    }
}
