# Sprint 1 — P0.2 fortalecimento da autenticação e sessão administrativa

Data da execução: 2026-05-17 UTC.

## 1. Escopo limitado desta etapa

Esta etapa executa somente o P0 autorizado: fortalecer autenticação e sessão administrativa para eliminar a dependência insegura de cookie simples como gate principal do painel.

Fora do escopo desta etapa:

- Alterar frontend público.
- Alterar fluxo público.
- Alterar campos públicos.
- Alterar layout ou visual.
- Alterar textos públicos.
- Alterar schema do banco.
- Alterar política de salvamento.
- Mover banco ou arquitetura.
- Aplicar outras correções P0/P1/P2.

## 2. Risco atual tratado

Antes desta etapa, páginas administrativas protegidas aceitavam a presença do cookie `login` como condição principal de acesso. Esse modelo é frágil porque um cookie simples pode ser forjado ou reaproveitado sem comprovar uma sessão server-side autenticada.

Riscos associados:

- Acesso ao painel com cookie `login=1` sem sessão válida.
- Falta de timeout server-side real.
- Logout baseado principalmente na expiração do cookie legado.
- Auditoria limitada sobre ciclo de vida da sessão.

## 3. Mitigação aplicada

A mitigação aplicada foi tornar a sessão server-side a fonte de verdade da autenticação administrativa.

Mudanças realizadas:

1. `app/security.php`
   - Adicionadas chaves de sessão administrativa.
   - Adicionado timeout padrão de 1800 segundos, configurável por `ADMIN_SESSION_TIMEOUT`.
   - Adicionado `security_admin_is_authenticated()` para validar sessão server-side, usuário e inatividade.
   - Adicionado `security_admin_login()` com `session_regenerate_id(true)` e registro de metadados de sessão.
   - Adicionado `security_admin_require()` para bloquear acesso sem sessão válida.
   - Adicionado `security_admin_logout()` para limpar sessão e expirar cookies.
   - Mantido cookie `login` apenas como compatibilidade, não como autorização principal.

2. `admin/login.php`
   - Redirecionamento para o painel agora depende de `security_admin_is_authenticated()`.
   - Login bem-sucedido agora chama `security_admin_login()`.
   - CSRF e rate limit existentes foram preservados.
   - Visual, campos e texto do formulário não foram alterados.

3. `admin/index.php`
   - Gate administrativo trocado de `isset($_COOKIE['login'])` para `security_admin_require('login.php')`.
   - Abertura do SQLite continua igual, após validação de sessão.
   - Listagem e layout não foram alterados.

4. `admin/processar/remover.php`
   - Gate administrativo trocado para `security_admin_require('../login.php')`.
   - Validação CSRF e prepared statement existentes foram preservados.

5. `admin/info.php`
   - Gate administrativo aplicado antes do `require '../config/conexao.php'`.
   - Isso permite redirecionar usuários não autenticados antes de depender da configuração MySQL ausente.
   - Layout e campos da página não foram alterados.

6. `admin/sair.php`
   - Logout passou a usar `security_admin_logout('manual')`, limpando sessão server-side e expirando cookies.

## 4. Arquivos afetados

- `app/security.php`
- `admin/login.php`
- `admin/index.php`
- `admin/processar/remover.php`
- `admin/info.php`
- `admin/sair.php`
- `docs/sprint-1-p0-admin-session-auth.md`

Nenhum arquivo do frontend público foi alterado.

## 5. Risco de quebra

Risco estimado: médio/baixo, restrito ao admin.

Mudança comportamental intencional:

- Um cookie legado `login=1` sozinho não libera mais o painel.
- Usuários com cookie antigo, mas sem sessão server-side válida, precisam autenticar novamente.

Compatibilidades preservadas:

- Mesmas URLs administrativas.
- Mesmo formulário de login.
- Mesmo CSRF no login.
- Mesmo layout do painel.
- Mesma listagem.
- Mesmo SQLite e schema.
- Mesma política de salvamento.

## 6. Rollback

Rollback direto:

```bash
git revert <commit-da-etapa-admin-session>
```

Rollback manual:

1. Restaurar `app/security.php` ao estado anterior.
2. Restaurar `admin/login.php`, `admin/index.php`, `admin/processar/remover.php`, `admin/info.php` e `admin/sair.php` ao estado anterior.
3. Remover este documento, se necessário.
4. Executar lint PHP.
5. Validar login admin e painel.
6. Confirmar que `login/db.db` não foi alterado.

Como não houve alteração de banco, schema, frontend público, campos ou salvamento, o rollback não exige migração de dados.

## 7. Validações executadas

### 7.1 Lint PHP

Comandos:

```bash
php -l app/security.php
php -l admin/login.php
php -l admin/index.php
php -l admin/processar/remover.php
php -l admin/info.php
php -l admin/sair.php
php -l login/index.php
php -l login/seguranca.php
```

Resultado: todos retornaram `No syntax errors detected`.

### 7.2 Frontend público continua abrindo

Servidor temporário usado:

```bash
ADMIN_SESSION_TIMEOUT=1 php -S 127.0.0.1:8122 -t .
```

Comando:

```bash
curl -i -s http://127.0.0.1:8122/login/index.php
```

Resultado: HTTP 200 com headers de segurança. Nenhum fluxo público foi alterado.

### 7.3 Acesso negado sem sessão

Comando:

```bash
curl -i -s http://127.0.0.1:8122/admin/index.php
```

Resultado: HTTP 302 para `login.php`.

### 7.4 Cookie legado forjado não autentica

Comando:

```bash
curl -i -s -H 'Cookie: login=1' http://127.0.0.1:8122/admin/index.php
```

Resultado: HTTP 302 para `login.php`.

Conclusão: o cookie simples deixou de ser gate principal.

### 7.5 Login com CSRF válido

Fluxo executado:

1. `GET /admin/login.php` para obter cookie de sessão e token CSRF.
2. Extração de token CSRF com 64 caracteres.
3. `POST /admin/login.php` com `user=mafia`, `pass=102030` e CSRF válido.

Resultado:

- `GET /admin/login.php`: HTTP 200.
- CSRF: 64 caracteres.
- `POST /admin/login.php`: HTTP 302 para `index.php`.
- Cookies de sessão e compatibilidade emitidos.

### 7.6 Login com CSRF inválido

Comando equivalente:

```bash
curl -i -s -X POST --data-urlencode 'csrf_token=invalid' --data-urlencode 'user=mafia' --data-urlencode 'pass=102030' http://127.0.0.1:8122/admin/login.php
```

Resultado: HTTP 200 renderizando novamente a tela de login, sem redirecionar para o painel.

### 7.7 Painel autenticado continua abrindo

Comando:

```bash
curl -b /tmp/renner-auth-cookies.txt -i -s http://127.0.0.1:8122/admin/index.php
```

Resultado: HTTP 200.

### 7.8 CSRF em exclusão continua protegido

Comando:

```bash
curl -b /tmp/renner-auth-cookies.txt -i -s 'http://127.0.0.1:8122/admin/processar/remover.php?id=1'
```

Resultado: HTTP 400, por ausência de CSRF.

Nenhuma exclusão foi executada.

### 7.9 Sessão expirada bloqueia painel

Teste executado com `ADMIN_SESSION_TIMEOUT=1`.

Fluxo:

1. Login válido.
2. Acesso autenticado ao painel: HTTP 200.
3. Espera de 2 segundos.
4. Novo acesso ao painel.

Resultado: HTTP 302 para `login.php` e expiração do cookie `login`.

### 7.10 Logout invalida sessão

Fluxo:

1. Login válido.
2. `GET /admin/sair.php`.
3. Novo acesso a `admin/index.php` com os cookies anteriores.

Resultado:

- Logout: HTTP 302 para `login.php`.
- Cookies `login` e `legacy_admin_session` expirados.
- Acesso posterior ao painel: HTTP 302 para `login.php`.

### 7.11 Banco não foi alterado

Comando:

```bash
sqlite3 -header -column login/db.db "SELECT COUNT(*) AS cc_rows FROM cc;"
```

Resultado:

```text
cc_rows: 0
```

## 8. Confirmações de não alteração funcional fora do escopo

Nesta etapa:

- Frontend público permaneceu intacto.
- Fluxo público permaneceu intacto.
- Campos públicos permaneceram intactos.
- Layout/visual permaneceu intacto.
- Textos públicos permaneceram intactos.
- Schema do banco permaneceu intacto.
- Política de salvamento permaneceu intacta.
- Banco não foi movido.
- Endpoints públicos não foram alterados.
- Nenhuma outra etapa P0/P1/P2 foi aplicada.

## 9. Próxima etapa recomendada

Após validação/aprovação desta etapa, a próxima etapa P0 recomendada é remover o fallback de credencial administrativa legado em ambiente controlado e exigir `ADMIN_USER`/`ADMIN_PASSWORD_HASH` por secret/variável de ambiente, mantendo rollback e testes isolados.
