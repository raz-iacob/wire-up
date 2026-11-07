<?php

declare(strict_types=1);

namespace App\Integrations;

abstract class CardIntegration extends Integration
{
    /**
     * @var list<string>|null
     */
    protected ?array $disconnects = null;

    protected bool $listed = true;

    abstract public function key(): string;

    /**
     * @param  array<string, mixed>  $saved
     */
    abstract public function connected(array $saved): bool;

    final public function showsOnIntegrationsPage(): bool
    {
        return $this->listed;
    }

    /**
     * @return list<string>
     */
    final public function disconnects(): array
    {
        return $this->disconnects ?? array_keys($this->settings());
    }
}
