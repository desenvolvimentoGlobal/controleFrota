# Controle de Frota

Sistema de controle da frota de veículos da GLOBAL: cadastro de veículos,
alocação a motoristas, checagem por fotos na saída e no retorno (pelo celular,
instalável como app), ocorrências com responsável presumido, manutenções com
planos preventivos e relatórios de custo em PDF e Excel.

Irmão de `gestaoPessoas`, `gestaoEmpresarial` e `emissaoOS`: mesma stack
(Laravel 13 / PHP 8.3 / MySQL 8), mesmo design system `gc-` e mesma infra
(Docker + Traefik).

- Guia técnico e convenções: [CLAUDE.md](CLAUDE.md)
- Planejamento, modelo de dados, fluxos e decisões: [docs/PLANEJAMENTO.md](docs/PLANEJAMENTO.md)
- API de integração: [docs/API.md](docs/API.md)
- Deploy em produção: [docs/DEPLOY.md](docs/DEPLOY.md)

## Rodar em desenvolvimento (WAMP)

```powershell
composer install
copy .env.example .env      # ajuste DB_* se necessário
php artisan key:generate
php artisan migrate --seed
php artisan storage:link
php artisan serve
```

Acesse http://localhost:8000. Usuários de exemplo (senha `senha123`):
`admin`, `gestor`, `motorista`, `financeiro`.

As rotinas agendadas (atrasos, reservas do dia, planos preventivos, vencimentos,
retenção de fotos) rodam com `php artisan schedule:work`.

```powershell
php artisan test        # suíte completa (sqlite em memória)
vendor\bin\pint         # estilo
```
