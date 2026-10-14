<?php

namespace App\Console\Commands;

use App\Models\GateTerminal;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

#[Signature('gate:terminal:create {name} {--gate=}')]
#[Description('Create an API credential for an offline gate terminal')]
class CreateGateTerminal extends Command
{
    public function handle(): int
    {
        $token = bin2hex(random_bytes(32));
        $terminal = GateTerminal::create([
            'terminal_id' => (string) Str::uuid(),
            'name' => (string) $this->argument('name'),
            'gate' => $this->option('gate'),
            'api_token_hash' => hash('sha256', $token),
            'api_token' => $token,
            'is_active' => true,
        ]);

        $this->components->info('Gate terminal created. Copy this token into the terminal secret store; it will not be shown again.');
        $this->line('Terminal ID: '.$terminal->terminal_id);
        $this->line('API token: '.$token);

        return self::SUCCESS;
    }
}
