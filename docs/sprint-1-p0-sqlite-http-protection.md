# Sprint 1 — P0.1 proteção de acesso HTTP direto ao SQLite

Data da execução: 2026-05-17 UTC.

## 1. Escopo limitado desta Sprint

Esta Sprint executa somente a primeira etapa P0 autorizada: proteção contra acesso/download HTTP direto ao banco SQLite.

Fora do escopo desta Sprint:

- Alterar visual público.
- Alterar layout do painel.
- Alterar textos públicos.
- Alterar fluxo funcional.
- Alterar campos.
- Alterar endpoints existentes.
- Alterar schema do banco.
- Alterar política de salvamento.
- Mover o banco para fora do webroot.
- Refatorar arquitetura.
- Aplicar outras correções P0/P1/P2.

## 2. Risco atual

O banco SQLite atual está em `login/db.db`, dentro da árvore do aplicativo. Se o servidor web servir arquivos estáticos diretamente a partir da raiz do projeto, uma requisição HTTP para `/login/db.db` pode expor o conteúdo bruto do banco.

Impacto potencial:

- Vazamento integral da tabela `cc`.
- Exposição de dados pessoais/financeiros se existirem registros.
- Exposição de schema e metadados internos.
- Alto risco reputacional, operacional e regulatório.

## 3. Mitigação aplicada

A mitigação aplicada foi reforçar as regras `.htaccess` já existentes para negar acesso HTTP direto a arquivos de banco, backup, log e secrets.

Alterações realizadas:

1. `.htaccess`
   - Adicionados comentários de escopo da Sprint 1 P0.
   - Tornadas case-insensitive as regras para `.env` e extensões sensíveis.
   - Mantida negação para extensões: `.bak`, `.backup`, `.log`, `.sql`, `.sqlite`, `.sqlite3`, `.db`.

2. `login/.htaccess`
   - Substituída regra exata `<Files "db.db">` por `<FilesMatch "(?i)^db\.db$">`.
   - Mantida negação específica ao banco `db.db`.
   - Tornadas case-insensitive as regras para extensões sensíveis.
   - Mantido acesso interno PHP por filesystem, sem mover ou alterar `login/db.db`.

Essa mitigação é compatível com Apache/LiteSpeed com `.htaccess` habilitado. Para Nginx, Caddy, Cloudflare ou outro proxy/CDN, deve existir regra equivalente na configuração de borda/servidor.

## 4. Arquivos afetados

- `.htaccess`
- `login/.htaccess`
- `docs/sprint-1-p0-sqlite-http-protection.md`

Nenhum arquivo PHP funcional foi alterado.

## 5. Risco de quebra

Risco estimado: baixo.

Motivos:

- A aplicação acessa `login/db.db` via filesystem, não por HTTP.
- As regras bloqueiam apenas download HTTP direto de arquivos sensíveis.
- Não há alteração em rotas PHP, endpoints, campos, HTML, CSS, schema ou salvamento.

Riscos residuais:

- `.htaccess` só é efetivo em servidores compatíveis e com `AllowOverride` habilitado.
- O PHP built-in server ignora `.htaccess`; portanto, ele não serve como validação real dessa proteção.
- Em Nginx/Caddy/CDN é obrigatória configuração equivalente fora do repositório.

## 6. Rollback

Rollback direto:

```bash
git revert <commit-da-sprint-1>
```

Ou rollback manual:

1. Restaurar `.htaccess` ao estado anterior.
2. Restaurar `login/.htaccess` ao estado anterior.
3. Remover este documento, se necessário.
4. Confirmar que `login/db.db` não foi alterado.
5. Executar lint dos arquivos PHP ativos.
6. Executar smoke test público/admin.

Como não houve alteração de PHP, banco, schema, campos, endpoints ou salvamento, o rollback funcional é de baixo risco.

## 7. Validações executadas

### 7.1 Lint PHP dos arquivos ativos

Comandos:

```bash
php -l app/security.php
php -l login/index.php
php -l login/seguranca.php
php -l login/procced.php
php -l admin/login.php
php -l admin/index.php
php -l admin/processar/remover.php
php -l admin/info.php
php -l admin/sair.php
```

Resultado: todos retornaram `No syntax errors detected`.

### 7.2 SQLite continua funcionando internamente

Comando:

```bash
php -r 'require "app/security.php"; $pdo = security_pdo_sqlite(__DIR__ . "/login/db.db"); echo "cc_rows=" . $pdo->query("SELECT COUNT(*) FROM cc")->fetchColumn() . PHP_EOL;'
```

Resultado:

```text
cc_rows=0
```

Observação: o helper registrou alerta de permissão `0644`/world-readable via `security_audit_log`, o que permanece como risco P0/P1 futuro de permissão/segregação. A Sprint atual não altera permissões nem move o banco.

### 7.3 Regras `.htaccess` presentes

Comando:

```bash
php -r '$files=[".htaccess","login/.htaccess"]; foreach($files as $f){$c=file_get_contents($f); echo $f . ":" . ((str_contains($c,"(?i)") && str_contains($c,"db")) ? "deny-rule-present" : "missing") . PHP_EOL; }'
```

Resultado:

```text
.htaccess:deny-rule-present
login/.htaccess:deny-rule-present
```

### 7.4 Smoke test de frontend público

Servidor temporário usado somente para smoke:

```bash
php -S 127.0.0.1:8121 -t .
```

Comandos:

```bash
curl -i -s http://127.0.0.1:8121/login/index.php
curl -i -s -X POST --data-urlencode 'cpf=12345678901' --data-urlencode 'senha=123456' http://127.0.0.1:8121/login/seguranca.php
```

Resultado:

- `GET /login/index.php`: HTTP 200.
- `POST /login/seguranca.php`: HTTP 200.
- Headers de segurança continuaram presentes.
- Nenhum dado foi persistido.

### 7.5 Smoke test de painel/admin

Comandos principais:

```bash
curl -c /tmp/renner-sprint1-cookies.txt -s http://127.0.0.1:8121/admin/login.php -o /tmp/renner-sprint1-login.html -D -
curl -b /tmp/renner-sprint1-cookies.txt -c /tmp/renner-sprint1-cookies.txt -i -s -X POST --data-urlencode "csrf_token=$token" --data-urlencode 'user=mafia' --data-urlencode 'pass=102030' http://127.0.0.1:8121/admin/login.php
curl -b /tmp/renner-sprint1-cookies.txt -i -s http://127.0.0.1:8121/admin/index.php
```

Resultado:

- `GET /admin/login.php`: HTTP 200 e CSRF com 64 caracteres.
- `POST /admin/login.php`: HTTP 302 para `index.php`.
- `GET /admin/index.php` autenticado: HTTP 200.
- O painel continuou abrindo.
- Nenhuma operação destrutiva foi executada.

### 7.6 Tentativa de acesso HTTP direto ao banco

Comando executado no servidor PHP embutido:

```bash
curl -i -s http://127.0.0.1:8121/login/db.db
```

Resultado no ambiente local:

```text
HTTP/1.1 200 OK
SQLite format 3...
```

Interpretação importante:

- Este resultado confirma a limitação conhecida do PHP built-in server: ele ignora `.htaccess`.
- Portanto, o PHP built-in server não é um ambiente válido para comprovar proteção `.htaccess`.
- A proteção aplicada é efetiva somente em servidor compatível com `.htaccess`, como Apache/LiteSpeed, desde que `AllowOverride` esteja habilitado.
- Validação final obrigatória em homologação/produção deve ser feita contra o servidor real. O resultado esperado nesse servidor é HTTP 403 ou 404 para `/login/db.db`.

## 8. Validação de dados

Comando executado após os smoke tests:

```bash
sqlite3 -header -column login/db.db "SELECT COUNT(*) AS cc_rows FROM cc;"
```

Resultado:

```text
cc_rows: 0
```

Conclusão: a Sprint não inseriu, removeu ou alterou registros no SQLite.

## 9. Confirmações de não alteração funcional

Nesta Sprint:

- Visual público permaneceu intacto.
- Layout do painel permaneceu intacto.
- Textos públicos permaneceram intactos.
- Fluxo funcional permaneceu intacto.
- Campos permaneceram intactos.
- Endpoints existentes permaneceram intactos.
- Schema do banco permaneceu intacto.
- Política de salvamento permaneceu intacta.
- O banco não foi movido.
- A arquitetura não foi refatorada.
- Nenhuma outra etapa P0/P1/P2 foi aplicada.

## 10. Próxima etapa recomendada

Antes de avançar para a próxima correção P0, validar esta proteção em um servidor real compatível com o ambiente de produção:

```bash
curl -i https://<dominio-ou-homologacao>/login/db.db
```

Critério de aceite em servidor real:

- HTTP 403 ou HTTP 404.
- Nenhum byte do SQLite deve ser retornado.
- Aplicação continua acessando o banco internamente.
- Painel continua funcionando.

Após essa validação, a próxima etapa recomendada é tratar o P0 seguinte: fortalecer a autenticação/sessão administrativa para não depender de cookie `login` como gate principal.
