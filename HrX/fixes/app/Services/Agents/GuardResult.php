<?php

namespace App\Services\Agents;

/**
 * Value object returned by PayrollGuardAgent (and reusable by other guard-style agents).
 * Accumulates WARN / BLOCK findings; overall result is BLOCK if any block was recorded,
 * WARN if any warn but no blocks, PASS otherwise.
 */
class GuardResult
{
    /** @var array<int, array{level:string, code:string, meta:array}> */
    private array $findings = [];

    public function pass(): self { return $this; }

    public function warn(string $code, array $meta = []): self
    {
        $this->findings[] = ['level' => 'WARN', 'code' => $code, 'meta' => $meta];
        return $this;
    }

    public function block(string $code, array $meta = []): self
    {
        $this->findings[] = ['level' => 'BLOCK', 'code' => $code, 'meta' => $meta];
        return $this;
    }

    public function isBlock(): bool
    {
        foreach ($this->findings as $f) if ($f['level'] === 'BLOCK') return true;
        return false;
    }

    public function isWarn(): bool
    {
        return !$this->isBlock() && collect($this->findings)->contains(fn($f) => $f['level'] === 'WARN');
    }

    public function isPass(): bool { return empty($this->findings); }

    /** @return array<int, array{level:string,code:string,meta:array}> */
    public function findings(): array { return $this->findings; }

    public function messages(): array
    {
        return array_map(fn ($f) => "[{$f['level']}] {$f['code']}", $this->findings);
    }

    public function summary(): array
    {
        return [
            'overall'  => $this->isBlock() ? 'BLOCK' : ($this->isWarn() ? 'WARN' : 'PASS'),
            'findings' => $this->findings,
        ];
    }
}
