<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Stancl\Tenancy\Database\Models\Domain;

class ListTenantCertDomainsCommand extends Command
{
    protected $signature = 'tenant:cert-domains';

    protected $description = 'Print every hostname the TLS certificate must cover (main domain + tenant subdomains), one per line. Read by /usr/local/sbin/bashar-tenant-certs.';

    public function handle(): int
    {
        $base = parse_url(config('app.url'), PHP_URL_HOST) ?: 'exchange.damatech.sy';

        $this->line($base);

        // Only full subdomains of the base domain; legacy short names ("issa") and
        // foreign domains are not something this certificate can or should cover.
        Domain::query()
            ->where('domain', 'like', '%.' . $base)
            ->orderBy('domain')
            ->pluck('domain')
            ->each(fn (string $domain) => $this->line(strtolower($domain)));

        return self::SUCCESS;
    }
}
