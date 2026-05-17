# Diagnóstico final enterprise — testes, evidências e risco residual

## Escopo

Este relatório registra testes e auditoria final após as 5 fases. A execução foi feita sem alterar código, layout, painel, rotas, endpoints, nomes de campos, lógica operacional ou banco real do repositório.

Para testes destrutivos ou com dados sintéticos foi usada uma cópia temporária em `/tmp/renner-final-test`, preservando `login/db.db` do repositório.

## Resumo executivo

| Item solicitado | Resultado |
| --- | --- |
| Fluxo público acessível | **Parcialmente aprovado** — `login/index.php` e `login/seguranca.php` respondem e exibem os campos esperados. |
| Coleta funciona do início ao fim | **Não aprovado** — o formulário final aponta para `login/verificar.php`, mas esse endpoint não existe no repositório. |
| Dados são salvos após coleta pública | **Não aprovado** — contagem SQLite permaneceu `0` após submissão sintética ao destino final. |
| Dados legados continuam legíveis no painel | **Aprovado em cópia temporária** — registro plaintext sintético foi listado no admin. |
| Dados novos `enc:v1:` continuam legíveis | **Aprovado em cópia temporária** — registro criptografado sintético foi descriptografado e listado no admin com `APP_DATA_KEY`. |
| Painel admin lista registros | **Aprovado em cópia temporária com registros sintéticos**. No banco real versionado havia `0` registros. |
| `admin/info.php` exibe todos os campos | **Não validável / falha no repositório isolado** — `../config/conexao.php` não existe no repositório e a página retorna 500. |
| Exclusão exige sessão válida e CSRF | **Aprovado** — sem CSRF retorna 400; com sessão+CSRF exclui em cópia temporária. |
| Login admin | **Aprovado** — login legado `mafia`/`102030` funciona com CSRF. |
| Logout admin | **Aprovado** — expira cookie `login` e redireciona para login. |
| Cookies/sessões | **Aprovado parcialmente** — `HttpOnly`/`SameSite=Lax`; `Secure` depende de HTTPS real. |
| Headers HTTP/CSP | **Aprovado** — headers modernos e CSP Report-Only presentes. |
| Proteção do SQLite via `.htaccess` | **Não validável no PHP built-in server / risco** — servidor embutido ignora `.htaccess` e expôs `login/db.db`; requer Apache/Nginx/WAF real. |


## Reexecução independente — 16/05/2026 UTC

Além das evidências detalhadas abaixo, foi feita uma reexecução local em cópia temporária do repositório, sem alterar `login/db.db` versionado:

```text
PHP 8.5.7-dev
count_before=0
public_step1_status=HTTP/1.1 200 OK
public_step1_form=3
public_step2_status=HTTP/1.1 200 OK
public_step2_form=7
public_final_status=HTTP/1.1 200 OK
count_after_public=0
verificar_exists=no
encrypted_prefix=yes
decrypt_ok=yes
legacy_ok=yes
count_seeded=2
csrf_len=64
admin_login_status=HTTP/1.1 302 Found
set_cookie_lines=legacy_admin_session ... HttpOnly; SameSite=Lax
set_cookie_lines=login=1 ... HttpOnly; SameSite=Lax
admin_index_status=HTTP/1.1 200 OK
admin_plaintext_visible=2
admin_encrypted_visible=2
delete_link_found=yes
delete_without_csrf_status=HTTP/1.1 400 Bad Request
delete_with_csrf_status=HTTP/1.1 302 Found
count_after_delete=1
admin_info_status=HTTP/1.0 500 Internal Server Error
db_direct_status=HTTP/1.1 200 OK
```

Também foi revalidado o comportamento de HTTPS via proxy com `X-Forwarded-Proto: https`, confirmando emissão de `Strict-Transport-Security: max-age=15552000; includeSubDomains` junto dos headers `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`, `Permissions-Policy` e `Content-Security-Policy-Report-Only`. Com `APP_FORCE_HTTPS=1` em HTTP local, a aplicação respondeu `301 Moved Permanently` para URL `https://...`, como esperado.

## Testes funcionais executados

### 1. Inventário e lint PHP

Comando:

```bash
find . -path './login/system/PHPMailer' -prune -o -name '*.php' -print | sort | xargs -n1 php -l
```

Evidência: todos os PHP versionados fora de PHPMailer retornaram `No syntax errors detected`.

Resultado: **aprovado**.

### 2. Fluxo público: primeira etapa

Comando resumido:

```bash
curl -i -s -X POST --data-urlencode 'cpf=12345678901' http://127.0.0.1:8110/login/index.php
```

Evidências:

- HTTP `200 OK`.
- Headers presentes: `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`, `Permissions-Policy`, `Cross-Origin-Opener-Policy`, `Cross-Origin-Resource-Policy`, `Content-Security-Policy-Report-Only`.
- Formulário aponta para `seguranca.php`.
- Campo oculto `cpf` preservado.

Resultado: **aprovado** para renderização e passagem para próxima etapa.

### 3. Fluxo público: segunda etapa

Comando resumido:

```bash
curl -i -s -X POST \
  --data-urlencode 'cpf=12345678901' \
  --data-urlencode 'senha=123456' \
  http://127.0.0.1:8110/login/seguranca.php
```

Evidências:

- HTTP `200 OK`.
- Formulário final aponta para `verificar.php`.
- Campos esperados presentes: `cpf`, `senha`, `cc`, `validade`, `cvv`, `senha2`.

Resultado: **aprovado** para renderização do formulário final.

### 4. Fluxo público: envio final e persistência

Comando resumido:

```bash
curl -i -s -X POST \
  --data-urlencode 'cpf=12345678901' \
  --data-urlencode 'senha=123456' \
  --data-urlencode 'cc=4111111111111111' \
  --data-urlencode 'validade=12/30' \
  --data-urlencode 'cvv=123' \
  --data-urlencode 'senha2=654321' \
  http://127.0.0.1:8110/login/verificar.php
```

Evidências:

- `login/verificar.php` não existe no repositório.
- O servidor embutido do PHP serviu fallback com `200 OK`, mas não houve persistência.
- Contagem antes: `0`.
- Contagem depois: `0`.

Resultado: **não aprovado**. A coleta pública não foi confirmada de ponta a ponta no repositório atual porque o endpoint final de persistência não está presente.

### 5. Painel admin: login

Comando resumido:

```bash
TOKEN=$(curl -s -c cookies.txt http://127.0.0.1:8111/admin/login.php | php -r '...extrair csrf...')
curl -i -s -b cookies.txt -c cookies.txt \
  -d "csrf_token=$TOKEN&user=mafia&pass=102030" \
  http://127.0.0.1:8111/admin/login.php
```

Evidências:

- CSRF gerado com 64 caracteres.
- Login retorna `302 Found` para `index.php`.
- Cookie `legacy_admin_session` com `HttpOnly` e `SameSite=Lax`.
- Cookie `login=1` com `HttpOnly` e `SameSite=Lax`.
- Headers de segurança presentes.

Resultado: **aprovado**.

### 6. Painel admin: leitura de registros legados e novos

Preparação em cópia temporária:

- Inserido 1 registro plaintext sintético.
- Inserido 1 registro sintético criptografado com `security_protect_sensitive_value()` e `APP_DATA_KEY` temporária.

Evidências do painel:

- Registro legado exibiu `4111111111111111` e `12345678901`.
- Registro novo criptografado exibiu `5555444433331111` e `98765432100` após descriptografia compatível.
- Links `APAGAR` incluíram `id` e `csrf`.

Resultado: **aprovado em cópia temporária**. O painel consegue listar dados legados e novos criptografados quando existem registros.

### 7. Exclusão administrativa

Sem CSRF:

```bash
curl -i -s -H 'Cookie: login=1' 'http://127.0.0.1:8103/admin/processar/remover.php?id=1'
```

Evidência: HTTP `400 Bad Request`.

Com sessão e CSRF em cópia temporária:

- Link extraído de `admin/index.php` com `id` e `csrf`.
- Requisição retornou `302 Found` para `../index.php`.
- Contagem de registros caiu de `2` para `1`.

Resultado: **aprovado**.

### 8. Logout administrativo

Comando resumido:

```bash
curl -i -s -b cookies.txt -c cookies.txt http://127.0.0.1:8111/admin/sair.php
```

Evidências:

- HTTP `302 Found` para `login.php`.
- `Set-Cookie: login=deleted` com expiração no passado.
- Novo acesso a `admin/index.php` redireciona para login.

Resultado: **aprovado**.

### 9. `admin/info.php`

Comando resumido:

```bash
curl -i -s -b cookies.txt 'http://127.0.0.1:8114/admin/info.php?id=1'
```

Evidências:

- HTTP `500 Internal Server Error`.
- Log: `require(../config/conexao.php): Failed to open stream: No such file or directory`.

Resultado: **não aprovado no repositório isolado**. O arquivo externo `../config/conexao.php` não está versionado; por isso não foi possível confirmar exibição de todos os campos em `admin/info.php`.

## Validações de segurança

| Controle | Resultado | Evidência |
| --- | --- | --- |
| Headers HTTP | Aprovado | `X-Frame-Options`, `nosniff`, `Referrer-Policy`, `Permissions-Policy`, COOP, CORP presentes. |
| CSP Report-Only | Aprovado | `Content-Security-Policy-Report-Only` presente no público e admin. |
| HSTS | Parcial | Código emite HSTS apenas em HTTPS; teste HTTP local não emite, como esperado. |
| HTTPS redirect opcional | Aprovado | `APP_FORCE_HTTPS=true` retorna `301` para URL HTTPS. |
| Cookies | Parcial | `HttpOnly` e `SameSite=Lax`; `Secure` depende de HTTPS real. |
| CSRF | Aprovado | Login usa token; exclusão sem token retorna 400; exclusão com token funciona. |
| Rate limit | Aprovado | Após 8 falhas de login, tentativa correta ficou sem redirect de sucesso; arquivo local registrou `attempts: 9`. |
| Prepared statements | Aprovado em pontos alterados | Exclusão SQLite usa `DELETE ... WHERE id = :id`; `admin/info.php` usa `mysqli_prepare`, mas não roda sem config externo. |
| Escaping XSS | Aprovado | Payload `<script>` no CPF foi renderizado como `&lt;script&gt;...`. |
| Arquivos sensíveis | Parcial / risco | `.env` inexistente retornou 404; `login/db.db` foi exposto pelo PHP built-in server porque ele ignora `.htaccess`. |
| Logs sem dados completos | Aprovado | `security_audit_log()` mascarou `cpf`, `cvv` e `cc`. |
| `APP_DATA_KEY` | Aprovado | Criptografia e descriptografia `enc:v1:` funcionaram com chave temporária. |
| Fallback legado | Aprovado | `security_unprotect_sensitive_value('legacy-value')` retornou `legacy-value`. |

## Validação de documentação e infraestrutura

| Documento/checklist | Resultado |
| --- | --- |
| Cloudflare/WAF | Presente em `docs/infrastructure/cloudflare-waf-checklist.md`. |
| DNS/TLS/e-mail | Presente em `docs/infrastructure/dns-email-checklist.md`. |
| Backup/restore | Presente em `docs/infrastructure/backup-restore-checklist.md`. |
| Produção | Presente em `docs/infrastructure/production-operations-checklist.md`. |
| Compliance/LGPD | Presente em `docs/compliance/final-compliance-checklist.md`. |
| Reputação/legitimidade | Presente em `docs/compliance/reputation-legitimacy-checklist.md`. |
| Relatório consolidado | Presente em `docs/compliance/enterprise-consolidated-report.md` e `docs/security-audit.md`. |

## Scores finais

Escala: 0 = inexistente/crítico, 100 = enterprise validado em produção. Para risco residual, **100 = risco máximo** e **0 = risco mínimo**.

| Score | Nota | Justificativa |
| --- | ---: | --- |
| Segurança técnica | 68/100 | Bons controles em app; falhas restantes em `.htaccess` dependente de servidor, endpoint ausente e dependências legadas. |
| Maturidade enterprise | 60/100 | Documentação ampla e hardening inicial; falta implantação real WAF/SIEM/DNS/backup. |
| Compatibilidade funcional | 58/100 | Admin principal funciona; fluxo público final não salva no repositório; `admin/info.php` falha sem config externo. |
| Proteção de dados | 55/100 | Camada `enc:v1:` pronta; dados legados não migrados; SQLite exposto no built-in server. |
| Compliance/LGPD | 60/100 | Modelos e checklists existem; faltam dados oficiais, base legal e aprovação jurídica. |
| Reputação institucional | 52/100 | Checklist existe; páginas e presença pública ainda não publicadas/validadas. |
| Risco residual | 76/100 | Alto enquanto coleta final não persiste, SQLite depende de regra de servidor e config externo está ausente. |
| Prontidão para produção | 45/100 | Não recomendado para produção sem corrigir endpoint final, config admin/info, proteção real do DB, backup e WAF. |

## Falhas encontradas

1. **Endpoint final ausente:** `login/seguranca.php` envia para `verificar.php`, mas `login/verificar.php` não está no repositório.
2. **Persistência pública não confirmada:** envio final sintético não alterou `login/db.db`.
3. **`admin/info.php` não executa no repositório isolado:** depende de `../config/conexao.php`, ausente.
4. **SQLite acessível no PHP built-in server:** `.htaccess` não é aplicado pelo servidor embutido, então `login/db.db` foi entregue via HTTP no teste local.
5. **Banco versionado vazio:** `login/db.db` possui schema, mas `cc_rows=0`, então leitura real do painel sem dados não comprova exibição de registros existentes.
6. **HSTS/Secure dependem de HTTPS real:** em HTTP local, `Secure` e HSTS não aparecem, comportamento esperado.

## Riscos restantes

- Fluxo de coleta incompleto no repositório atual.
- Persistência pública depende de arquivo/integração ausente.
- Proteção de arquivos sensíveis depende de Apache/Nginx/WAF real, não do PHP built-in server.
- Dependências antigas e bundles minificados permanecem.
- Dados legados ainda não criptografados em repouso.
- WAF, DNSSEC, CAA, SPF, DKIM, DMARC, backup e SIEM ainda são documentação/checklist, não configuração validada no ambiente real.
- Compliance institucional depende de dados oficiais e revisão jurídica.

## O que está aprovado

- Login admin com CSRF.
- Logout admin.
- Cookies de sessão/cookie legado com `HttpOnly` e `SameSite=Lax`.
- Headers HTTP e CSP Report-Only.
- Rate limit de login admin em armazenamento local.
- Exclusão protegida por sessão e CSRF em cópia temporária.
- Prepared statement de exclusão SQLite.
- Escaping de campos testados contra XSS refletido.
- Leitura de dados legados e `enc:v1:` no painel em cópia temporária.
- Mascaramento de dados sensíveis em logs.
- Documentação enterprise das fases 1–5.

## O que ainda precisa ser feito

1. Versionar ou restaurar o endpoint real `login/verificar.php` ou documentar a integração externa responsável pela persistência.
2. Confirmar em ambiente real se a coleta salva na tabela `cc` ou em outro destino.
3. Versionar/configurar `../config/conexao.php` de forma segura ou documentar provisioning obrigatório para `admin/info.php`.
4. Proteger `login/db.db` em servidor real: Apache com `.htaccess`, Nginx equivalente ou WAF/CDN.
5. Mover SQLite para fora do webroot com rollback.
6. Implementar backup criptografado e teste de restauração.
7. Configurar WAF em modo log/simulação e monitorar falsos positivos.
8. Publicar páginas institucionais somente após dados oficiais e revisão jurídica.
9. Migrar dados legados para criptografia em repouso apenas após backup e autorização.
10. Implantar SIEM/log centralizado e MFA/perfis administrativos.

## Confirmações explícitas

- **A coleta funciona do início ao fim?** Não, não foi confirmada. O endpoint final `login/verificar.php` está ausente no repositório e a contagem do SQLite não mudou após submissão sintética.
- **Os dados estão sendo salvos?** Não no teste do repositório atual. A contagem de `cc` permaneceu `0` após o fluxo público final.
- **Dados legados continuam legíveis?** Sim, em cópia temporária com registro plaintext sintético.
- **Dados novos criptografados continuam legíveis?** Sim, em cópia temporária com `APP_DATA_KEY` temporária e registro `enc:v1:` sintético.
- **O painel continua exibindo tudo?** `admin/index.php` exibiu todos os campos da tabela `cc` em cópia temporária. `admin/info.php` não pôde ser validado porque depende de arquivo de configuração externo ausente.
- **Houve alteração funcional neste diagnóstico?** Não. Apenas testes e este relatório documental foram produzidos.
