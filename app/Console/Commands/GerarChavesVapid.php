<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Minishlink\WebPush\VAPID;

class GerarChavesVapid extends Command
{
    protected $signature = 'webpush:vapid';

    protected $description = 'Gera um par de chaves VAPID para Web Push (copie para o .env)';

    public function handle(): int
    {
        try {
            $chaves = VAPID::createVapidKeys();
        } catch (\Throwable $e) {
            $this->error('Não foi possível gerar as chaves: '.$e->getMessage());
            $this->line('No Windows/WAMP, defina OPENSSL_CONF apontando para o openssl.cnf do PHP.');

            return self::FAILURE;
        }

        $this->info('Chaves VAPID geradas. Copie para o .env (dev) ou .env.docker (produção):');
        $this->newLine();
        $this->line('VAPID_PUBLIC_KEY='.$chaves['publicKey']);
        $this->line('VAPID_PRIVATE_KEY='.$chaves['privateKey']);
        $this->newLine();
        $this->warn('Guarde a chave privada com segurança e não a versione.');

        return self::SUCCESS;
    }
}
