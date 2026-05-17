# Sprint 1 — P0.3 remoção do fallback legado de credenciais administrativas

Data da execução: 2026-05-17 UTC.

## 1. Escopo limitado desta etapa

Esta etapa executa somente o P0 autorizado: remover credenciais administrativas hardcoded e exigir autenticação via variáveis de ambiente/secret.

Fora do escopo desta etapa:

- Alterar frontend público.
- Alterar fluxo público.
- Alterar campos públicos.
- Alterar layout ou visual.
- Alterar textos públicos.
- Alterar endpoints públicos.
- Alterar banco ou schema.
- Alterar política de persistência/salvamento.
- Alterar outras áreas fora da autenticação administrativa.
- Aplicar outras correções P0/P1/P2.

## 2. Risco atual tratado

Antes desta etapa, `admin/login.php` aceitava fallback local quando as variáveis de ambiente não estavam configuradas:

- usuário legado: `mafia`;
- senha legada: `102030`.

Riscos associados:

- Credencial previsível versionada junto ao código.
- Possibilidade de deploy sem secrets e ainda assim com login funcional usando credencial fraca.
- Dificuldade de governança, rotação e responsabilização operacional.
- Redução da efetividade do hardening de sessão já aplicado.

## 3. Mitigação aplicada

A mitigação aplicada foi remover o fallback hardcoded e exigir configuração explícita por ambiente/secret.

Mudanças realizadas em `admin/login.php`:

1. `ADMIN_USER` passa a ser obrigatório.
2. `ADMIN_PASSWORD_HASH` passa a ser obrigatório.
3. A senha só é validada por `password_verify()` contra `ADMIN_PASSWORD_HASH`.
4. Se as variáveis não estiverem configuradas, o login falha de forma segura.
5. Tentativas com configuração ausente registram evento `admin_credentials_missing` em auditoria.
6. O formulário, campos, layout, texto, CSRF, rate limit e sessão server-side foram preservados.

## 4. Arquivos afetados

- `admin/login.php`
- `docs/sprint-1-p0-admin-credentials.md`

Nenhum arquivo do frontend público foi alterado.
Nenhum arquivo de banco/schema foi alterado.

## 5. Risco de quebra

Risco estimado: médio e restrito ao login administrativo.

Mudança comportamental intencional:

- Sem `ADMIN_USER` e `ADMIN_PASSWORD_HASH`, nenhum login administrativo é aceito.
- A credencial legada `mafia`/`102030` não funciona mais por fallback.

Compatibilidades preservadas:

- Mesma URL `admin/login.php`.
- Mesmo formulário visual.
- Mesmos campos `user` e `pass`.
- Mesmo CSRF no login.
- Mesmo rate limit.
- Mesma sessão server-side introduzida na etapa anterior.
- Mesmo painel após login válido.

## 6. Configuração segura exigida

Gerar hash seguro da senha fora do repositório:

```bash
php -r 'echo password_hash("SENHA_FORTE_AQUI", PASSWORD_DEFAULT) . PHP_EOL;'
```

Configurar no ambiente/secret manager:

```bash
ADMIN_USER='usuario_admin'
ADMIN_PASSWORD_HASH='$2y$...hash_gerado...'
```

Observações:

- Nunca versionar a senha real.
- Nunca versionar o hash em arquivo público do projeto.
- Rotacionar credenciais em processo controlado.
- Usar secret manager em produção.

## 7. Rollback

Rollback direto:

```bash
git revert <commit-da-etapa-admin-credentials>
```

Rollback manual:

1. Restaurar `admin/login.php` ao estado anterior.
2. Remover este documento, se necessário.
3. Executar lint PHP.
4. Validar login admin.
5. Confirmar que `login/db.db` não foi alterado.

Como não houve alteração de banco, schema, frontend público, campos ou salvamento, o rollback não exige migração de dados.

## 8. Validações executadas

### 8.1 Lint PHP

Comandos:

```bash
php -l admin/login.php
php -l app/security.php
php -l admin/index.php
php -l admin/sair.php
php -l login/index.php
php -l login/seguranca.php
```

Resultado: todos retornaram `No syntax errors detected`.

### 8.2 Banco não foi alterado

Comando:

```bash
sqlite3 -header -column login/db.db "SELECT COUNT(*) AS cc_rows FROM cc;"
```

Resultado:

```text
cc_rows: 0
```

### 8.3 Ausência de variáveis bloqueia login

Servidor temporário sem variáveis administrativas:

```bash
env -u ADMIN_USER -u ADMIN_PASSWORD_HASH php -S 127.0.0.1:8123 -t .
```

Fluxo validado:

1. `GET /admin/login.php` retornou HTTP 200 e CSRF com 64 caracteres.
2. `POST /admin/login.php` com a credencial legada `mafia`/`102030` retornou HTTP 200, sem redirecionar para o painel.
3. `GET /admin/index.php` com os cookies resultantes retornou HTTP 302 para `login.php`.

Conclusão: sem variáveis/secret, o login falha de forma segura.

### 8.4 Login inválido com variáveis configuradas

Servidor temporário com variáveis configuradas:

```bash
ADMIN_USER=admin_operator \
ADMIN_PASSWORD_HASH="<hash password_hash de BankSecret123!>" \
ADMIN_SESSION_TIMEOUT=2 \
php -S 127.0.0.1:8124 -t .
```

Fluxo validado:

1. `GET /admin/login.php` retornou HTTP 200 e CSRF com 64 caracteres.
2. `POST /admin/login.php` com senha incorreta retornou HTTP 200, sem redirecionar para o painel.

### 8.5 Login correto com variáveis configuradas

Fluxo validado:

1. `POST /admin/login.php` com `user=admin_operator`, senha correta e CSRF válido retornou HTTP 302 para `index.php`.
2. `GET /admin/index.php` com cookies autenticados retornou HTTP 200.

Conclusão: o painel continua compatível quando `ADMIN_USER` e `ADMIN_PASSWORD_HASH` estão configurados corretamente.

### 8.6 Logout e sessão

Fluxo validado:

1. `GET /admin/sair.php` após login válido retornou HTTP 302 para `login.php`.
2. Cookies `login` e `legacy_admin_session` foram expirados.
3. Novo acesso a `admin/index.php` com cookies anteriores retornou HTTP 302 para `login.php`.

### 8.7 Frontend público continua abrindo

Comando:

```bash
curl -i -s http://127.0.0.1:8124/login/index.php
```

Resultado: HTTP 200 com headers de segurança. Nenhum fluxo público foi alterado.

## 9. Confirmações de não alteração fora do escopo

Nesta etapa:

- Frontend público permaneceu intacto.
- Fluxo público permaneceu intacto.
- Campos públicos permaneceram intactos.
- Layout/visual permaneceu intacto.
- Textos públicos permaneceram intactos.
- Endpoints públicos permaneceram intactos.
- Schema do banco permaneceu intacto.
- Política de salvamento permaneceu intacta.
- Banco não foi movido.
- Nenhuma outra etapa P0/P1/P2 foi aplicada.

## 10. Próxima etapa recomendada

Após validação/aprovação desta etapa, a próxima etapa P0 recomendada é revisar permissões/segregação física do SQLite (`login/db.db`) em ambiente real, sem alterar schema ou política de salvamento, começando por permissões de arquivo e plano controlado para caminho fora do webroot.
