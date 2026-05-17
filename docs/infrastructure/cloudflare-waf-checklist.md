# Checklist Cloudflare/WAF e proteção de borda

## Modo de implantação

Todas as regras abaixo devem iniciar em **Log/Simulate/Monitor**. Promover para challenge/bloqueio apenas após revisão de eventos, falsos positivos e aprovação operacional.

## Separação de superfícies

### Público

- Rotas públicas: `/login/`, `/login/index.php`, `/login/seguranca.php`, `/login/procced.php`, assets em `/js/`, `/fonts/`, `/images/` e `/login/index_files/`.
- Objetivo inicial: reduzir scanners e abuso volumétrico sem impedir usuários legítimos.

### Administrativo

- Rotas admin: `/admin/`, `/admin/login.php`, `/admin/index.php`, `/admin/info.php`, `/admin/processar/remover.php`, `/admin/sair.php`.
- Objetivo inicial: observabilidade, rate limit e alerta; restrição por IP/VPN deve ser avaliada, mas não ativada sem confirmação de operação.

## Regras WAF em modo log/simulação

| Prioridade | Escopo | Condição | Ação inicial | Promoção futura |
| --- | --- | --- | --- | --- |
| 1 | Admin | Acesso a `/admin/*` | Log | Allowlist por VPN/IP ou Access/Zero Trust após validação. |
| 2 | Admin login | Muitos POSTs em `/admin/login.php` por IP | Log + alertar | Rate limit/challenge gradual. |
| 3 | Admin ações | Acesso a `/admin/processar/*` | Log | Exigir sessão e regras adicionais na aplicação/borda. |
| 4 | Arquivos sensíveis | Solicitações para `*.db`, `*.sqlite`, `*.sql`, `.env`, `*.bak`, `*.log` | Log | Bloquear após confirmar que não há uso legítimo. |
| 5 | Métodos HTTP | Métodos diferentes de `GET`, `POST`, `HEAD` | Log | Bloquear `PUT`, `PATCH`, `DELETE`, `TRACE`, `OPTIONS` se não houver integração legítima. |
| 6 | Scanners comuns | Paths como `/wp-admin`, `/phpmyadmin`, `/.git`, `/vendor`, `/composer.*` | Log | Bloquear por regra gerenciada. |
| 7 | Payloads óbvios | SQLi/XSS/RCE conhecidos por managed rules | Log | Managed challenge/block após 7–14 dias sem falso positivo. |
| 8 | Bots abusivos | Alto volume em formulário público | Log | Bot score/challenge leve, nunca bloqueio cego no início. |

## Rate limits iniciais sugeridos em modo monitoramento

> Ajustar números com base em tráfego real. Começar monitorando antes de bloquear.

| Endpoint | Janela inicial | Limite inicial | Ação inicial |
| --- | --- | --- | --- |
| `/admin/login.php` | 5 minutos | 10 POST/IP | Log + alerta |
| `/admin/processar/remover.php` | 5 minutos | 20 requisições/IP | Log + alerta |
| `/login/procced.php` | 1 minuto | 30 POST/IP | Log |
| `/login/seguranca.php` | 1 minuto | 60 POST/IP | Log |

## Métodos HTTP

1. Inventariar métodos usados em logs por 7 dias.
2. Se apenas `GET`, `POST` e `HEAD` forem usados, criar regra para monitorar outros métodos.
3. Promover bloqueio de métodos desnecessários somente após janela de observação.

## Proteção contra scanners

Monitorar e depois bloquear tentativas para:

- `/.git`, `/.env`, `/composer.json`, `/composer.lock`.
- `/vendor/`, `/node_modules/`.
- `/phpmyadmin`, `/wp-admin`, `/xmlrpc.php`.
- `*.sql`, `*.db`, `*.sqlite`, `*.bak`, `*.backup`, `*.log`.

## Admin separado do público

Recomendações futuras, dependentes de aprovação:

- Subdomínio administrativo separado ou Cloudflare Access/Zero Trust.
- Allowlist por IP/VPN para `/admin/*`.
- MFA na camada de borda.
- Alertas em tempo real para login admin fora de país/ASN esperado.

## Critérios para sair de log/simulação

- Pelo menos 7 dias de tráfego representativo.
- Zero falso positivo crítico em rotas públicas e admin.
- Rollback documentado por regra.
- Responsável de plantão definido.
- Alertas de 403/429 ativos.
