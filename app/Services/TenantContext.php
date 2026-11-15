<?php

namespace App\Services;

use RuntimeException;

class TenantContext
{
    private ?int $companyId = null;
    private ?int $userId = null;
    private bool $bypassScope = false;

    public function setCompany(int $companyId, ?int $userId = null): void
    {
        if ($companyId <= 0) {
            throw new RuntimeException("INVALID_COMPANY_ID: {$companyId}");
        }
        $this->companyId = $companyId;
        if ($userId !== null) {
            $this->userId = $userId;
        }
    }

    public function clear(): void
    {
        $this->companyId = null;
        $this->userId = null;
    }

    public function id(): ?int
    {
        return $this->companyId;
    }

    public function userId(): ?int
    {
        return $this->userId;
    }

    public function requireCompanyId(): int
    {
        if ($this->companyId === null) {
            throw new RuntimeException('TENANT_CONTEXT_MISSING: Active company_id is not set.');
        }

        return $this->companyId;
    }

    public function isBypassed(): bool
    {
        return $this->bypassScope;
    }

    public function withoutScope(callable $callback): mixed
    {
        $previous = $this->bypassScope;
        $this->bypassScope = true;

        try {
            return $callback();
        } finally {
            $this->bypassScope = $previous;
        }
    }
}
