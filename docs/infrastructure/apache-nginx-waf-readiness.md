# Readiness Apache/Nginx/WAF — hardening operacional

Este documento registra os controles técnicos internos adicionados sem alterar visual, layout, textos públicos, campos ou fluxo visual.

## Apache/LiteSpeed

Controles no repositório:

- `.htaccess` raiz bloqueia `.env`, bancos, backups, logs, manifests comuns e `.git`.
- `login/.htaccess` bloqueia `db.db` e artefatos de banco/backup/log dentro de `login/`.
- `login/system/.htaccess` bloqueia acesso HTTP direto às bibliotecas legadas server-side.

Validação obrigatória em homologação:

```bash
curl -i https://<host>/login/db.db
curl -i https://<host>/.env
curl -i https://<host>/.git/config
curl -i https://<host>/login/system/PHPMailer/class.phpmailer.php
```

Resultado esperado: HTTP 403 ou 404.

## Nginx equivalente

Quando Nginx estiver na frente da aplicação, `.htaccess` não é aplicado. Usar regra equivalente no server block:

```nginx
location ~ /\.git(?:/|$) { return 404; }
location ~ /\.env { deny all; return 403; }
location ~* \.(?:bak|backup|log|sql|sqlite|sqlite3|db)$ { deny all; return 403; }
location ^~ /login/system/ { deny all; return 403; }
location ~* ^/(?:composer\.(?:json|lock)|package(?:-lock)?\.json|README\.md)$ { deny all; return 403; }
```

## WAF/CDN

Regras recomendadas sem alterar aplicação:

- bloquear acesso a caminhos sensíveis (`/.git`, `/.env`, `/login/db.db`, `/login/system/`);
- rate limit em `/admin/login.php`;
- challenge/managed rules para painel admin;
- permitir apenas HTTPS;
- registrar request ID do proxy em `X-Request-Id` ou equivalente.

## CSP/HSTS

- CSP permanece em modo relatório por padrão para não quebrar scripts externos do frontend legado.
- `APP_CSP_ENFORCE=true` só deve ser habilitado após homologação visual completa.
- `APP_FORCE_HTTPS=true` deve estar ativo em produção para redirecionar HTTP para HTTPS.
