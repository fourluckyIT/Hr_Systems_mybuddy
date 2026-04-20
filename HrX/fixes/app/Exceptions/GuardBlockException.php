<?php

namespace App\Exceptions;

class GuardBlockException extends \RuntimeException
{
    public function __construct(
        public readonly array $findings,
        public readonly array $summary,
        string $message = 'Payroll guard blocked finalization',
    ) {
        parent::__construct($message);
    }

    public function render($request)
    {
        return response()->json([
            'error'    => 'guard_blocked',
            'message'  => $this->getMessage(),
            'findings' => $this->findings,
            'summary'  => $this->summary,
        ], 422);
    }
}
