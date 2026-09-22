# Controle de Frota

Sistema de controle da frota de veículos da GLOBAL: cadastro de veículos,
alocação a motoristas, checagem por fotos na saída e no retorno, manutenções e
visão financeira de custos.

Irmão de `gestaoPessoas`, `gestaoEmpresarial` e `emissaoOS`: mesma stack
(Laravel 13 / PHP 8.3 / MySQL 8), mesmo design system `gc-` e mesma infra
(Docker + Traefik).

- Guia técnico e convenções: [CLAUDE.md](CLAUDE.md)
- Planejamento, modelo de dados, fluxos e decisões: [docs/PLANEJAMENTO.md](docs/PLANEJAMENTO.md)

## Rodar em desenvolvimento (WAMP)

```powershell
composer install
copy .env.example .env      # ajuste DB_* se necessário
php artisan key:generate
php artisan migrate --seed
php artisan storage:link
php artisan serve
```

Acesse http://localhost:8000 com `admin` / `senha123`.
