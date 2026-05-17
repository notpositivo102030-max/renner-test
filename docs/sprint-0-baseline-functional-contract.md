# Sprint 0 — Baseline e contrato funcional

Data da execução: 2026-05-17 UTC.

## 1. Escopo e restrições

Esta Sprint 0 registra o baseline técnico e o contrato funcional do estado atual da branch, sem executar correções P0/P1/P2 e sem alterar funcionamento do sistema.

Restrições observadas nesta etapa:

- Não alterar visual público.
- Não alterar layout do painel.
- Não alterar textos públicos.
- Não alterar fluxo funcional.
- Não alterar campos.
- Não alterar endpoints.
- Não alterar schema do banco.
- Não alterar política de salvamento.
- Não remover arquivos, código, assets, dependências ou documentação existente.
- Não aplicar mudanças de arquitetura.
- Executar apenas validações seguras de leitura, lint e smoke test sem persistir dados reais.

## 2. Estado Git registrado

Comandos executados:

```bash
git rev-parse --abbrev-ref HEAD
git rev-parse HEAD
git status --short --branch
```

Resultado registrado:

```text
Branch: work
Commit: 5b72217820944ae4a84b93f5e6895d01a9664627
Status: ## work
```

Interpretação: a branch auditada estava sem alterações locais antes da criação deste documento de Sprint 0.

## 3. Arquivos críticos registrados

### 3.1 Camada central de segurança

- `app/security.php`: bootstrap de segurança, headers, sessão, cookies, CSRF, rate limit, criptografia opcional, mascaramento/sanitização de logs, auditoria e helper PDO SQLite.

### 3.2 Frontend público e fluxo público

- `login/index.php`: primeira tela pública; recebe/preserva `cpf` e envia para `seguranca.php`.
- `login/seguranca.php`: segunda tela pública; recebe `cpf`/`senha`, solicita dados adicionais e envia para `verificar.php`.
- `login/procced.php`: endpoint público legado/órfão no fluxo principal observado.
- `login/index_files/`: assets e bibliotecas frontend baixadas localmente.
- `js/`: bundles JavaScript legados.
- `fonts/` e `images/`: assets estáticos.

### 3.3 Banco de dados

- `login/db.db`: SQLite versionado no repositório e usado pelo painel principal.

### 3.4 Painel administrativo

- `admin/login.php`: login administrativo.
- `admin/index.php`: listagem administrativa da tabela SQLite `cc`.
- `admin/processar/remover.php`: exclusão de registro da tabela `cc`.
- `admin/info.php`: tela administrativa legada dependente de `../config/conexao.php`.
- `admin/sair.php`: logout administrativo.
- `admin/header.php` e `admin/footer.php`: componentes visuais do painel.
- `admin/css/`, `admin/font-awesome-4.7.0/`, `admin/img/`, `admin/new-info1.mp3`, `admin/notify.js`: assets do painel.

### 3.5 Dependências legadas relevantes

- `login/system/PHPMailer/`: PHPMailer legado, incluindo `composer.json`, `composer.lock` e classes PHP.
- `admin/font-awesome-4.7.0/`: Font Awesome legado.
- `login/index_files/*.download`: dependências frontend locais sem manifesto centralizado de versões.

### 3.6 Documentação existente antes desta Sprint 0

- `docs/security-audit.md`
- `docs/structural-audit.md`
- `docs/final-enterprise-diagnostic.md`
- `docs/compliance/*`
- `docs/infrastructure/*`

## 4. Schema SQLite registrado

Comandos executados:

```bash
sqlite3 login/db.db '.schema'
sqlite3 -header -column login/db.db "SELECT COUNT(*) AS cc_rows FROM cc;"
```

Schema atual:

```sql
CREATE TABLE sqlite_sequence(name,seq);
CREATE TABLE `cc` (
  `id` INTEGER PRIMARY KEY AUTOINCREMENT,
  `cc` varchar(100) NOT NULL,
  `validade` varchar(100) NOT NULL,
  `cvv` varchar(100) NOT NULL,
  `cpf` varchar(50) NOT NULL,
  `senha_app` varchar(50) NOT NULL,
  `senha_cc` varchar(50) NOT NULL,
  `status` varchar(50) NOT NULL DEFAULT 'Nova'
);
```

Contagem atual registrada:

```text
cc_rows: 0
```

Observação: nenhum dado real foi inserido, alterado ou removido durante esta Sprint 0.

## 5. Endpoints existentes e ausentes

### 5.1 Endpoints/arquivos existentes

| Caminho | Status | Papel atual |
| --- | --- | --- |
| `app/security.php` | Existe | Camada central de segurança e utilitários. |
| `login/index.php` | Existe | Primeira etapa pública. |
| `login/seguranca.php` | Existe | Segunda etapa pública. |
| `login/procced.php` | Existe | Endpoint legado/órfão do fluxo principal. |
| `admin/login.php` | Existe | Login administrativo. |
| `admin/index.php` | Existe | Listagem administrativa SQLite. |
| `admin/processar/remover.php` | Existe | Exclusão de registro `cc`. |
| `admin/info.php` | Existe | Tela administrativa legada dependente de MySQL/config ausente. |
| `admin/sair.php` | Existe | Logout administrativo. |

### 5.2 Endpoints/arquivos ausentes, mas referenciados

| Caminho | Status | Referência/impacto |
| --- | --- | --- |
| `login/verificar.php` | Ausente | Destino declarado do formulário final em `login/seguranca.php`; bloqueia fechamento ponta a ponta. |
| `admin/processar/acao.php` | Ausente | Referenciado por `admin/info.php` para ação/status. |
| `admin/processar/qr_code.php` | Ausente | Destino do formulário de QR code em `admin/info.php`. |
| `config/conexao.php` | Ausente | Requerido por `admin/info.php`; impede uso isolado dessa página. |

## 6. Fluxo funcional atual

### 6.1 Fluxo público observado

1. `login/index.php`
   - Carrega `app/security.php`.
   - Executa `security_bootstrap('public')`.
   - Define `$cpf = $_POST['cpf'] ?? ''`.
   - Renderiza formulário público.
   - Envia `POST` para `seguranca.php`.

2. `login/seguranca.php`
   - Carrega `app/security.php`.
   - Executa `security_bootstrap('public')`.
   - Recebe `cpf` e `senha` do passo anterior.
   - Renderiza formulário final com campos adicionais.
   - Envia `POST` para `verificar.php`.

3. `login/verificar.php`
   - Arquivo ausente no repositório.
   - Persistência pública final não é validável no estado atual.

### 6.2 Fluxo administrativo observado

1. `admin/login.php`
   - Carrega `app/security.php`.
   - Executa `security_bootstrap('admin')`.
   - Exibe formulário com CSRF.
   - Valida usuário/senha por variáveis de ambiente quando disponíveis, com fallback legado atual.
   - Em sucesso, grava cookie `login` e redireciona para `index.php`.

2. `admin/index.php`
   - Carrega `app/security.php`.
   - Executa `security_bootstrap('admin')`.
   - Abre `login/db.db` com `security_pdo_sqlite()`.
   - Exige presença de cookie `login`.
   - Consulta `select * from cc`.
   - Exibe campos da tabela `cc`.
   - Gera link de exclusão com `id` e CSRF.

3. `admin/processar/remover.php`
   - Carrega `app/security.php`.
   - Executa `security_bootstrap('admin')`.
   - Exige cookie `login`.
   - Valida CSRF.
   - Valida `id` como inteiro positivo.
   - Executa `DELETE FROM cc WHERE id = :id`.
   - Redireciona para `../index.php`.

4. `admin/sair.php`
   - Carrega `app/security.php`.
   - Executa `security_bootstrap('admin')`.
   - Registra auditoria.
   - Expira cookie `login`.
   - Redireciona para `login.php`.

5. `admin/info.php`
   - Carrega `app/security.php`.
   - Executa `security_bootstrap('admin')`.
   - Requer `../config/conexao.php`, ausente.
   - Usa MySQL/Mysqli com query parametrizada para tabela `dados`.
   - Referencia endpoints ausentes `processar/acao.php` e `processar/qr_code.php`.

## 7. Campos recebidos, enviados e exibidos

### 7.1 Fluxo público

| Origem | Campo | Tipo/forma observada | Destino declarado | Persistência esperada |
| --- | --- | --- | --- | --- |
| Entrada anterior/POST em `login/index.php` | `cpf` | `$_POST['cpf'] ?? ''`; depois hidden | `login/seguranca.php` | Esperado como `cc.cpf` no painel, mas endpoint final ausente. |
| `login/index.php` | `senha` | Campo `password` | `login/seguranca.php` e depois `login/verificar.php` | Esperado como `cc.senha_app`, mas endpoint final ausente. |
| `login/seguranca.php` | `cpf` | Hidden preservado | `login/verificar.php` | Esperado como `cc.cpf`, mas endpoint final ausente. |
| `login/seguranca.php` | `senha` | Hidden preservado | `login/verificar.php` | Esperado como `cc.senha_app`, mas endpoint final ausente. |
| `login/seguranca.php` | `cc` | Campo `tel` | `login/verificar.php` | Esperado como `cc.cc`, mas endpoint final ausente. |
| `login/seguranca.php` | `validade` | Campo `tel` | `login/verificar.php` | Esperado como `cc.validade`, mas endpoint final ausente. |
| `login/seguranca.php` | `cvv` | Campo `tel` | `login/verificar.php` | Esperado como `cc.cvv`, mas endpoint final ausente. |
| `login/seguranca.php` | `senha2` | Campo `password` | `login/verificar.php` | Esperado como `cc.senha_cc`, mas endpoint final ausente. |
| `login/seguranca.php` | `g-recaptcha-response` | Textarea oculto legado | `login/verificar.php` | Sem validação final versionada no repositório. |

### 7.2 Endpoint legado `login/procced.php`

| Campo | Origem | Uso atual |
| --- | --- | --- |
| `typepass` | `POST` | Processa lista de palavras; não persiste no SQLite. |
| `password1` | `POST` | Monta conteúdo local em variável; não persiste no SQLite. |
| `confirm1` | `POST` | Monta conteúdo local em variável; não persiste no SQLite. |

### 7.3 Painel administrativo

| Campo/entrada | Origem | Destino/uso |
| --- | --- | --- |
| `user` | `admin/login.php` | Validação de login admin. |
| `pass` | `admin/login.php` | Validação de login admin. |
| `csrf_token` | `admin/login.php` | Proteção CSRF do login. |
| `login` | Cookie | Gate atual do painel. |
| `id` | Query string em `admin/processar/remover.php` | Excluir registro `cc`. |
| `csrf` | Query string em `admin/processar/remover.php` | Proteção CSRF da exclusão. |
| `qr_code` | `admin/info.php` | Enviado para endpoint ausente `admin/processar/qr_code.php`. |

## 8. Persistência esperada

### 8.1 Tabela SQLite `cc`

| Coluna | Sensibilidade | Uso esperado/observado |
| --- | --- | --- |
| `id` | Baixa | Identificador interno e exclusão. |
| `cc` | Alta | Exibido no painel; pode ser plaintext legado ou `enc:v1:` se inserido por backend compatível. |
| `validade` | Alta | Exibido no painel; pode ser plaintext legado ou `enc:v1:` se inserido por backend compatível. |
| `cvv` | Crítica | Exibido no painel; deve ser tratado como risco crítico em fases futuras. |
| `cpf` | Alta | Exibido no painel; pode ser plaintext legado ou `enc:v1:` se inserido por backend compatível. |
| `senha_app` | Crítica | Exibido no painel; deve ser tratado como risco crítico em fases futuras. |
| `senha_cc` | Crítica | Exibido no painel; deve ser tratado como risco crítico em fases futuras. |
| `status` | Média | Default `'Nova'`; usado na listagem/alerta visual. |

### 8.2 Persistência observada nesta Sprint 0

- A tabela `cc` existe.
- A contagem atual é `0`.
- Nenhum teste desta Sprint 0 alterou o banco.
- A persistência pública final não é validável porque `login/verificar.php` está ausente.

## 9. Painel/admin — contrato atual

| Área | Contrato atual | Observação de risco |
| --- | --- | --- |
| Login | `admin/login.php` com CSRF, rate limit e fallback legado de credencial. | Fallback legado deve ser tratado em Sprint futura, não nesta Sprint 0. |
| Sessão/cookie | `security_bootstrap('admin')` inicia sessão; cookie `login` ainda é usado como gate. | Sessão forte é P0 futuro, fora desta Sprint. |
| Listagem | `admin/index.php` consulta `select * from cc`. | Exibe valores descriptografados/plaintext com escape HTML. |
| Exclusão | `admin/processar/remover.php` valida CSRF e usa prepared statement. | Ainda é ação destrutiva por GET, a ser avaliada futuramente. |
| Logout | `admin/sair.php` expira cookie e redireciona. | Invalidação server-side completa fica para Sprint futura. |
| Info legado | `admin/info.php` depende de config/endpoints ausentes. | Não validável isoladamente no repositório atual. |

## 10. Matriz de riscos P0/P1

| Prioridade | Risco | Evidência/estado atual | Impacto | Próxima ação sugerida |
| --- | --- | --- | --- | --- |
| P0 | Fluxo final público ausente | `login/seguranca.php` envia para `verificar.php`, arquivo ausente. | Fluxo ponta a ponta não fecha. | Decidir formalmente se endpoint será implementado, neutralizado ou removido em Sprint futura. |
| P0 | Banco SQLite dentro da árvore do app | `login/db.db` é usado diretamente pelo painel. | Risco de exposição por HTTP se servidor não bloquear. | Proteger via servidor/WAF e planejar mover fora do webroot. |
| P0 | Dados altamente sensíveis no modelo/painel | Tabela `cc` inclui `cvv`, senhas e dados pessoais/financeiros. | Risco legal, reputacional e de segurança. | Definir política formal de dados antes de alterar salvamento. |
| P0 | Gate admin ainda baseado em cookie `login` | `admin/index.php` verifica `isset($_COOKIE['login'])`. | Risco de autorização fraca. | Migrar para sessão server-side forte em Sprint futura. |
| P0 | Fallback legado de credencial admin | Login mantém fallback quando env vars não existem. | Risco de acesso indevido em ambiente real. | Exigir secrets de ambiente em Sprint futura. |
| P1 | `admin/info.php` incompleto | Requer `../config/conexao.php` ausente. | Página quebra em repositório isolado. | Decidir manutenção, migração ou remoção futura. |
| P1 | Endpoints admin ausentes | `acao.php` e `qr_code.php` referenciados, ausentes. | Fluxos administrativos legados incompletos. | Mapear necessidade real antes de implementar. |
| P1 | PHPMailer legado | Dependência antiga e `htmlfilter.php` falha no lint em PHP 8.5.7-dev. | Risco de compatibilidade e segurança. | Atualizar/remover em Sprint futura controlada. |
| P1 | CSP em Report-Only | Headers presentes, mas CSP não bloqueia. | Proteção parcial. | Coletar/validar antes de modo bloqueante. |
| P1 | Dados exibidos sem mascaramento no painel | Painel exibe valores descriptografados/plaintext. | Exposição interna. | Mascaramento por padrão após aprovação. |

## 11. Checklist de regressão mínima

Executar antes e depois de cada Sprint futura:

### 11.1 Git e inventário

- [ ] `git status --short --branch` sem alterações inesperadas.
- [ ] Branch e commit registrados.
- [ ] Arquivos alterados listados.

### 11.2 Banco

- [ ] `sqlite3 login/db.db '.schema'` sem mudanças não autorizadas.
- [ ] Contagem de registros conferida antes/depois quando a Sprint não deveria alterar dados.
- [ ] Nenhum teste usa dados reais.

### 11.3 PHP/lint

- [ ] Lint de arquivos ativos principais passa.
- [ ] Lint completo registra exceções legadas conhecidas sem bloquear Sprint documental.

### 11.4 Smoke público seguro

- [ ] `GET /login/index.php` retorna HTTP 200.
- [ ] `POST /login/index.php` com CPF sintético retorna HTTP 200.
- [ ] `POST /login/seguranca.php` com CPF/senha sintéticos retorna HTTP 200.
- [ ] Form action de `login/index.php` permanece `seguranca.php`, salvo aprovação explícita.
- [ ] Form action de `login/seguranca.php` permanece `verificar.php`, salvo aprovação explícita.

### 11.5 Smoke admin seguro

- [ ] `GET /admin/index.php` sem cookie redireciona para login.
- [ ] `admin/login.php` renderiza formulário com CSRF.
- [ ] Exclusão sem CSRF continua bloqueada em ambiente de teste com dados sintéticos.
- [ ] Nenhum teste destrutivo é executado contra `login/db.db` real.

### 11.6 Headers

- [ ] `X-Frame-Options` presente.
- [ ] `X-Content-Type-Options` presente.
- [ ] `Referrer-Policy` presente.
- [ ] `Permissions-Policy` presente.
- [ ] `Content-Security-Policy-Report-Only` presente.

## 12. Plano de rollback

Como esta Sprint 0 altera apenas documentação, o rollback funcional é simples:

1. Reverter o commit da Sprint 0 se o documento precisar ser removido.
2. Confirmar que nenhum arquivo PHP, asset, banco ou configuração operacional foi alterado.
3. Validar `git status --short --branch`.
4. Confirmar que `login/db.db` mantém o mesmo schema e contagem observada antes da Sprint.
5. Executar lint dos arquivos ativos principais.
6. Executar smoke público/admin seguro.

Para Sprints futuras com alterações funcionais, o rollback mínimo obrigatório deverá incluir:

- backup prévio do banco;
- commit isolado por mudança;
- comando de revert documentado;
- smoke test antes/depois;
- validação de schema;
- validação de logs sem dados sensíveis;
- responsável de aprovação.

## 13. Testes seguros executados nesta Sprint 0

### 13.1 Lint de arquivos ativos principais

Comando executado:

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

Resultado: todos os arquivos ativos principais acima retornaram `No syntax errors detected`.

### 13.2 Lint de arquivo legado conhecido

Comando executado:

```bash
php -l login/system/PHPMailer/extras/htmlfilter.php
```

Resultado registrado:

```text
PHP Parse error: syntax error, unexpected token "{" in login/system/PHPMailer/extras/htmlfilter.php on line 351
Errors parsing login/system/PHPMailer/extras/htmlfilter.php
```

Interpretação: falha já classificada como legado/dependência antiga; nenhuma correção aplicada nesta Sprint 0.

### 13.3 Smoke tests somente leitura / sem persistência real

Servidor local temporário usado somente para smoke:

```bash
php -S 127.0.0.1:8120 -t .
```

Requisições executadas:

```bash
curl -i -s http://127.0.0.1:8120/login/index.php
curl -i -s -X POST --data-urlencode 'cpf=12345678901' http://127.0.0.1:8120/login/index.php
curl -i -s -X POST --data-urlencode 'cpf=12345678901' --data-urlencode 'senha=123456' http://127.0.0.1:8120/login/seguranca.php
curl -i -s http://127.0.0.1:8120/admin/index.php
curl -i -s http://127.0.0.1:8120/login/verificar.php
```

Resultados observados:

- `GET /login/index.php`: HTTP 200, headers de segurança presentes.
- `POST /login/index.php`: HTTP 200, sem persistência.
- `POST /login/seguranca.php`: HTTP 200, sem persistência.
- `GET /admin/index.php` sem cookie: HTTP 302 para `login.php`, com headers de segurança e cookie de sessão administrativo.
- `login/verificar.php`: arquivo ausente no filesystem; o smoke via servidor embutido não substitui a evidência de ausência de arquivo registrada por checagem direta.

### 13.4 Verificação de ausência de alteração de dados

Antes da documentação, a tabela `cc` tinha `0` registros. Como os testes executados não chamaram endpoint de persistência real e não executaram operações destrutivas, não houve alteração intencional de dados.

## 14. Confirmação de não alteração funcional

Nesta Sprint 0:

- Código PHP funcional não foi alterado.
- Visual público não foi alterado.
- Layout do painel não foi alterado.
- Textos públicos não foram alterados.
- Campos não foram alterados.
- Endpoints não foram alterados.
- Schema SQLite não foi alterado.
- Política de salvamento não foi alterada.
- Nenhum arquivo legado foi removido.
- Nenhuma correção P0/P1/P2 foi aplicada.

Única mudança planejada da Sprint 0: criação deste documento de baseline e contrato funcional.

## 15. Próxima etapa recomendada

A próxima etapa recomendada, após aprovação explícita, é iniciar a Sprint 1 com uma única mudança pequena e testável por vez. A primeira candidata P0 é proteger o acesso HTTP direto ao SQLite e validar isso em ambiente de servidor real, sem alterar fluxo, visual, campos ou salvamento.
