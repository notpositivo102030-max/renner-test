# Auditoria estrutural completa — análise sem alteração de código

## 1. Escopo e garantia de não alteração funcional

Esta auditoria mapeia a estrutura atual do sistema antes de qualquer melhoria visual, funcional ou de segurança adicional. A análise foi feita sem alterar código de aplicação, layout, rotas, fluxo de coleta, painel, banco de dados versionado ou lógica operacional.

Testes destrutivos e inserções sintéticas foram executados exclusivamente em cópia temporária fora do repositório, preservando `login/db.db` original.

## 2. Mapa geral do sistema

### 2.1 Pastas e responsabilidades

| Caminho | Papel estrutural | Observações |
| --- | --- | --- |
| `/` | Raiz do webroot/repositório | Contém `.htaccess`, `.env.example`, assets globais e diretórios da aplicação. |
| `app/` | Backend compartilhado | `security.php` concentra bootstrap, headers, sessão, CSRF, rate limit, logs, criptografia e conexão SQLite via PDO. |
| `login/` | Frontend público/coleta | `index.php` e `seguranca.php` renderizam o fluxo público; `procced.php` existe, mas não participa do fluxo principal identificado. |
| `login/index_files/` | Assets legados do frontend público | CSS/JS/SVG baixados/espelhados, incluindo máscaras e scripts legados. |
| `login/system/PHPMailer/` | Biblioteca legada | PHPMailer antigo está presente, mas não foi observado no fluxo principal testado. |
| `admin/` | Painel administrativo | Login, listagem, detalhe legado e logout. |
| `admin/processar/` | Ações administrativas | Contém exclusão de registros (`remover.php`). |
| `admin/css/`, `admin/img/`, `admin/font-awesome-4.7.0/` | Assets do painel | CSS/Sass/mapas/fontes/ícones e áudio de notificação. |
| `docs/` | Documentação operacional e auditorias | Checklists de infraestrutura, compliance, segurança e diagnóstico final. |
| `fonts/`, `images/`, `js/` | Assets públicos globais | Fontes Roboto, imagens e bundles JavaScript. |

### 2.2 Arquivos principais

| Arquivo | Função | Pontos relevantes |
| --- | --- | --- |
| `app/security.php` | Bootstrap e camada de segurança | Headers, CSP Report-Only, sessão/cookies, CSRF, rate limit, logs, AES-256-GCM, PDO SQLite. |
| `login/index.php` | Primeira tela pública | Recebe `cpf` via POST e coleta `senha`; envia para `seguranca.php`. |
| `login/seguranca.php` | Segunda tela pública | Recebe `cpf` e `senha`, coleta dados de cartão e envia para `verificar.php`. |
| `login/procced.php` | Endpoint público legado/paralelo | Lê `typepass`, `password1`, `confirm1`; monta HTML em variável, mas não salva no banco no trecho observado. |
| `login/db.db` | Banco SQLite | Contém tabela `cc`; banco versionado está vazio (`COUNT(*) = 0`). |
| `admin/login.php` | Login do painel | Usa CSRF, rate limit, credenciais legadas ou variáveis de ambiente. |
| `admin/index.php` | Listagem administrativa | Lê tabela `cc` e exibe campos principais; gera link de exclusão com CSRF. |
| `admin/processar/remover.php` | Exclusão administrativa | Exige cookie `login`, CSRF e `id` inteiro; remove registro da tabela `cc`. |
| `admin/sair.php` | Logout | Remove cookie lógico `login` e redireciona para login. |
| `admin/info.php` | Detalhe legado | Depende de `../config/conexao.php` e tabela MySQL `dados`, ambos ausentes no repositório isolado. |
| `.htaccess`, `login/.htaccess` | Proteção em Apache | Bloqueiam `.env`, bancos, logs e backups quando o servidor aplica `.htaccess`. |
| `.env.example` | Exemplo de variáveis | `ADMIN_USER`, `ADMIN_PASSWORD_HASH`, `APP_ENV`, `APP_FORCE_HTTPS`, `APP_DATA_KEY`. |

### 2.3 Rotas/endpoints identificados

| Método | Rota | Origem | Destino/efeito | Status estrutural |
| --- | --- | --- | --- | --- |
| GET/POST | `/login/index.php` | Entrada pública ou POST externo com `cpf` | Renderiza formulário de senha e POST para `seguranca.php` | Existe e responde. |
| POST | `/login/seguranca.php` | Formulário de `login/index.php` | Renderiza formulário de cartão e POST para `verificar.php` | Existe e responde. |
| POST | `/login/verificar.php` | Formulário de `login/seguranca.php` | Endpoint esperado para persistência final | **Ausente no repositório**. |
| POST | `/login/procced.php` | Não referenciado no fluxo principal | Processa campos `typepass`, `password1`, `confirm1` | Existe, mas sem persistência observada. |
| GET/POST | `/admin/login.php` | Admin | Autentica com CSRF/rate limit; cookie `login=1` | Existe e funciona no teste. |
| GET | `/admin/index.php` | Admin autenticado | Lista tabela `cc` | Existe e funciona com dados sintéticos. |
| GET | `/admin/processar/remover.php?id=&csrf=` | Link no painel | Exclui da tabela `cc` | Existe; exige CSRF válido. |
| GET | `/admin/sair.php` | Admin | Logout | Existe e funciona. |
| GET | `/admin/info.php?id=` | Link/uso legado | Consulta MySQL `dados` via config externa | Existe, mas falha sem `../config/conexao.php`. |
| POST | `/admin/processar/qr_code.php` | Form de `admin/info.php` | Endpoint esperado para QR code | **Ausente no repositório**. |
| GET | `/admin/processar/acao.php` | JavaScript de `admin/info.php` | Endpoint esperado para mudança de status | **Ausente no repositório**. |

### 2.4 Arquivos sensíveis

| Arquivo/padrão | Sensibilidade | Proteção atual | Risco residual |
| --- | --- | --- | --- |
| `login/db.db` | Banco SQLite com campos pessoais/sensíveis | `.htaccess` tenta bloquear `db`, `sqlite`, backups e logs | PHP built-in server ignora `.htaccess`; ideal mover para fora do webroot. |
| `.env` | Segredos de ambiente | Bloqueado por `.htaccess`; não versionado | Requer garantia no servidor real. |
| `.env.example` | Exemplo sem segredo real | Versionado | Baixo, desde que não receba valores reais. |
| Logs PHP/audit | Podem conter eventos administrativos | `security_audit_log()` mascara campos sensíveis por nome | Depende de configuração de logs do servidor. |
| Backups `*.bak`, `*.backup`, `*.sql`, `*.sqlite`, `*.db` | Cópias de dados | Bloqueio Apache via `.htaccess` | Requer equivalentes em Nginx/CDN/WAF. |

## 3. Mapa completo da coleta pública

### 3.1 Etapa 1 — `login/index.php`

- Bootstrap público carregado antes do HTML.
- Lê `cpf` de `$_POST['cpf']` e o mantém como campo oculto.
- Renderiza formulário `method="POST" action="seguranca.php"`.
- Campos relevantes:

| Campo | Página | Input atual | Atributos/máscara | Enviado para | Uso posterior |
| --- | --- | --- | --- | --- | --- |
| `cpf` | `login/index.php` | `hidden` | Valor vindo de POST anterior; escapado | `login/seguranca.php` | Preservado para etapa final. |
| `senha` | `login/index.php` | `password` | `maxlength="6"`, `required`; `ng-minlength="14"` inconsistente com senha de 6 | `login/seguranca.php` | Mapeado conceitualmente para `senha_app` no banco/painel, mas endpoint final está ausente. |
| `g-recaptcha-response` | `login/index.php` | `textarea` oculto | Integrado a marcação legada de reCAPTCHA | `login/seguranca.php` | Não há validação backend observada nesta etapa. |

### 3.2 Etapa 2 — `login/seguranca.php`

- Bootstrap público carregado antes do HTML.
- Lê `cpf` e `senha` de POST e os mantém como campos ocultos.
- Renderiza formulário `method="POST" action="verificar.php"`.
- Campos relevantes:

| Campo | Página | Input atual | Atributos/máscara | Enviado para | Uso esperado |
| --- | --- | --- | --- | --- | --- |
| `cpf` | `login/seguranca.php` | `hidden` | Valor vindo da etapa 1; escapado | `login/verificar.php` | Coluna `cc.cpf`; exibido no painel em `CPF`. |
| `senha` | `login/seguranca.php` | `hidden` | Valor vindo da etapa 1; escapado | `login/verificar.php` | Coluna esperada `cc.senha_app`; exibido em `SENHA APP`. |
| `cc` | `login/seguranca.php` | `tel` | `maxlength="19"`, placeholder de cartão, máscara `#### #### #### ####` | `login/verificar.php` | Coluna `cc.cc`; exibido em `CC`. |
| `validade` | `login/seguranca.php` | `tel` | `maxlength="5"`, máscara `##/##` | `login/verificar.php` | Coluna `cc.validade`; exibido em `VALIDADE`. |
| `cvv` | `login/seguranca.php` | `tel` | `maxlength="3"`, placeholder CVV | `login/verificar.php` | Coluna `cc.cvv`; exibido em `CVV`. |
| `senha2` | `login/seguranca.php` | `password` | `maxlength="6"`, `required` | `login/verificar.php` | Coluna esperada `cc.senha_cc`; exibido em `SENHA CC`. |
| `g-recaptcha-response` | `login/seguranca.php` | `textarea` oculto | Markup legado | `login/verificar.php` | Sem endpoint para validar no repositório. |

### 3.3 Persistência esperada versus persistência observada

| Etapa | Resultado observado |
| --- | --- |
| Renderização da etapa 1 | Aprovada: HTTP 200, campos e action presentes. |
| Renderização da etapa 2 | Aprovada: HTTP 200, campos e action presentes. |
| Envio final | Não aprovado: `login/verificar.php` não existe; contagem da tabela `cc` permaneceu `0`. |
| Onde cada dado é salvo | Não confirmável no fluxo público atual, pois o endpoint de persistência está ausente. |
| Onde cada dado aparece | Quando registros sintéticos existem, `admin/index.php` exibe `cc`, `validade`, `cvv`, `cpf`, `senha_app`, `senha_cc`, `status`. |

## 4. Tabela campo → página → backend → banco → painel

| Campo de entrada | Página de coleta | Tipo atual | Backend destino declarado | Coluna esperada/observada | Exibição no painel | Status |
| --- | --- | --- | --- | --- | --- | --- |
| `cpf` | `login/index.php` / `login/seguranca.php` | `hidden` após entrada anterior | `login/verificar.php` | `cc.cpf` | Coluna `CPF` em `admin/index.php` | Endpoint final ausente. |
| `senha` | `login/index.php` | `password` | `login/seguranca.php` e depois `login/verificar.php` | `cc.senha_app` | Coluna `SENHA APP` | Endpoint final ausente. |
| `cc` | `login/seguranca.php` | `tel` | `login/verificar.php` | `cc.cc` | Coluna `CC` | Endpoint final ausente. |
| `validade` | `login/seguranca.php` | `tel` | `login/verificar.php` | `cc.validade` | Coluna `VALIDADE` | Endpoint final ausente. |
| `cvv` | `login/seguranca.php` | `tel` | `login/verificar.php` | `cc.cvv` | Coluna `CVV` | Endpoint final ausente. |
| `senha2` | `login/seguranca.php` | `password` | `login/verificar.php` | `cc.senha_cc` | Coluna `SENHA CC` | Endpoint final ausente. |
| `status` | Não coletado publicamente | N/A | Inserção backend esperada | `cc.status DEFAULT 'Nova'` | Coluna `STATUS` | Funciona quando registro existe. |
| `csrf_token` | `admin/login.php` | `hidden` gerado por helper | `admin/login.php` | Não persiste | Não exibido | Funciona. |
| `user` | `admin/login.php` | `text` | `admin/login.php` | Não persiste | Não exibido | Funciona com credencial legada/env. |
| `pass` | `admin/login.php` | `text` | `admin/login.php` | Não persiste | Não exibido | Funciona, mas visualmente deveria ser `password` em modernização futura. |
| `id` | `admin/processar/remover.php` | Query string | `admin/processar/remover.php` | `cc.id` | Link `APAGAR` | Funciona com CSRF. |
| `qr_code` | `admin/info.php` | `text` | `admin/processar/qr_code.php` | MySQL legado `dados.qrcode` esperado | `Token Qr Code`/input em `info.php` | Endpoint/config ausentes. |

## 5. Banco de dados

### 5.1 SQLite versionado: `login/db.db`

| Tabela | Uso | Status |
| --- | --- | --- |
| `cc` | Registros exibidos no painel principal | Existe, mas banco versionado está vazio. |
| `sqlite_sequence` | Controle interno AUTOINCREMENT | Normal em SQLite. |

Schema da tabela `cc`:

| Coluna | Tipo | Obrigatório | Default | Sensibilidade | Uso observado |
| --- | --- | --- | --- | --- | --- |
| `id` | `INTEGER PRIMARY KEY AUTOINCREMENT` | PK | Auto | Baixa | Identificador usado para exclusão. |
| `cc` | `varchar(100)` | Sim | Nenhum | Alta | Exibido no painel; pode ser plaintext legado ou `enc:v1:`. |
| `validade` | `varchar(100)` | Sim | Nenhum | Alta | Exibido no painel; pode ser plaintext legado ou `enc:v1:`. |
| `cvv` | `varchar(100)` | Sim | Nenhum | Alta | Exibido no painel; pode ser plaintext legado ou `enc:v1:`. |
| `cpf` | `varchar(50)` | Sim | Nenhum | Alta | Exibido no painel; pode ser plaintext legado ou `enc:v1:`. |
| `senha_app` | `varchar(50)` | Sim | Nenhum | Alta | Exibido no painel; pode ser plaintext legado ou `enc:v1:`. |
| `senha_cc` | `varchar(50)` | Sim | Nenhum | Alta | Exibido no painel; pode ser plaintext legado ou `enc:v1:`. |
| `status` | `varchar(50)` | Sim | `'Nova'` | Baixa/média | Exibido no painel; usado para notificação visual/áudio. |

### 5.2 Dados legados, duplicidades e validação

| Item | Diagnóstico |
| --- | --- |
| Dados legados | Camada `security_unprotect_sensitive_value()` mantém plaintext legível e descriptografa `enc:v1:` quando há `APP_DATA_KEY`. |
| Campos duplicados | Não há duplicidade na tabela `cc`; há duplicidade conceitual entre `senha` do fluxo público e `senha_app` do banco. |
| Campos não utilizados | `login/procced.php` lê `typepass`, `password1`, `confirm1`, mas esses campos não aparecem no fluxo principal e não salvam na tabela `cc`. |
| Campos sem validação backend | Não foi encontrado endpoint final `verificar.php`; portanto não há validação backend versionada para `cpf`, `senha`, `cc`, `validade`, `cvv`, `senha2`. |
| Tamanho de colunas | Tipos `varchar` no SQLite não impõem validação forte; dependem do backend. |
| Banco versionado | `cc_count=0`, impedindo validação com dados reais sem semear cópia temporária. |

### 5.3 Dependência de banco externo legado

`admin/info.php` consulta uma tabela MySQL `dados` via `../config/conexao.php`. Esse arquivo de configuração não está no repositório, e a tabela `dados` não faz parte do SQLite versionado. Portanto, `admin/info.php` pertence a um fluxo legado/desacoplado do painel principal `cc` e falha em ambiente isolado.

## 6. Funcionalidade testada

Testes executados em cópia temporária:

```text
cc_before=0
step1=HTTP/1.1 200 OK
step1_fields=3
step2=HTTP/1.1 200 OK
step2_fields=7
step3=HTTP/1.1 200 OK
verificar_exists=no
cc_after_public=0
cc_seeded=2
csrf_len=64
admin_login=HTTP/1.1 302 Found
admin_index=HTTP/1.1 200 OK
panel_legacy_hits=5
panel_encrypted_hits=4
delete_link=yes
delete_no_csrf=HTTP/1.1 400 Bad Request
delete_with_csrf=HTTP/1.1 302 Found
cc_after_delete=1
admin_info=HTTP/1.0 500 Internal Server Error
logout=HTTP/1.1 302 Found
db_direct=HTTP/1.1 200 OK
```

| Funcionalidade | Resultado | Evidência/observação |
| --- | --- | --- |
| Fluxo público etapa 1 | Aprovado | HTTP 200; form para `seguranca.php`; `cpf`, `senha` e reCAPTCHA markup presentes. |
| Fluxo público etapa 2 | Aprovado | HTTP 200; form para `verificar.php`; campos finais presentes. |
| Persistência pública | Não aprovado | `login/verificar.php` ausente; `cc_after_public=0`. |
| Painel lista registros | Aprovado com dados sintéticos | `admin/index.php` exibiu registros plaintext e `enc:v1:` em cópia temporária. |
| Login admin | Aprovado | CSRF 64 chars; POST válido retornou 302. |
| Exclusão sem CSRF | Aprovado | Retornou 400. |
| Exclusão com CSRF | Aprovado | Retornou 302; contagem caiu de 2 para 1. |
| Logout | Aprovado | Retornou 302 para login. |
| `admin/info.php` | Não aprovado | HTTP 500 por ausência de `../config/conexao.php`. |
| Proteção de `login/db.db` no built-in server | Não aprovado no ambiente local | PHP built-in server retornou HTTP 200 para o DB porque ignora `.htaccess`. |

## 7. UX e visual antigo

### 7.1 Frontend público

| Aspecto | Estado atual | Risco/observação | Modernização futura de baixo risco |
| --- | --- | --- | --- |
| Estrutura HTML | Página grande, legada, com muitos estilos/scripts inline e markup Angular-like estático | Difícil manutenção; alto acoplamento visual | Extrair CSS/JS gradualmente sem alterar `name`, `action` e `method`. |
| Inputs numéricos/mobile | Cartão, validade e CVV usam `type="tel"`, positivo para teclado numérico mobile | `cpf` fica oculto no trecho versionado; origem anterior não está no arquivo | Manter `type="tel"` e adicionar `inputmode="numeric"` apenas após teste visual. |
| Senha app/cartão | Usam `type="password"` nas telas públicas | Adequado visualmente para sigilo na UI | Preservar nomes `senha` e `senha2`. |
| Máscaras | Cartão usa `#### #### #### ####`; validade usa `##/##` | Máscaras são inline via `onkeypress`; manutenção difícil | Migrar para listener JS progressivo mantendo fallback. |
| Validação visual | Predomina `required`, `maxlength`, classes Angular legadas e mensagens estáticas | `ng-minlength="14"` em campo `senha` de 6 dígitos é inconsistente | Corrigir em etapa futura com testes de compatibilidade. |
| Mensagens de erro | Não há validação backend visível no repositório para o envio final | Usuário pode não receber erro real se endpoint ausente | Implementar/confirmar endpoint antes de mexer no visual. |
| Acessibilidade | Labels semânticos são limitados; muito conteúdo visual e scripts externos | Leitores de tela e foco podem ser prejudicados | Adicionar labels/aria de forma incremental sem alterar layout. |
| Responsividade | Há CSS legado e meta viewport; responsividade não foi auditada visualmente com navegador real nesta etapa | Risco em mobile moderno | Testar screenshot/device em etapa visual futura. |

### 7.2 Painel admin

| Aspecto | Estado atual | Risco/observação | Modernização futura de baixo risco |
| --- | --- | --- | --- |
| Login | Bootstrap remoto antigo; campo `pass` está como `type="text"` | Senha fica visível ao digitar | Trocar para `password` em etapa funcional pequena e testada. |
| Listagem | Tabela simples com auto-refresh de 3s | Auto-refresh pode interromper leitura/copiar dados | Tornar refresh controlável em modernização futura. |
| Status/alerta | Áudio quando status é `Nova`, mas seletor JS procura `td.status` enquanto a célula usa `status1` | Notificação pode não tocar | Corrigir seletor com cuidado em etapa posterior. |
| Exclusão | Link GET com CSRF em query | Funciona, mas método ideal seria POST | Migrar para POST com confirmação somente em fase planejada. |
| `info.php` | Visual e fluxo parecem de outro sistema (`dados`, QR code, Bradesco) | Incompatível com SQLite `cc` e quebrado sem config externa | Decidir se é legado a remover, documentar ou integrar antes de modernizar. |

## 8. Problemas encontrados

1. Endpoint final `login/verificar.php` não existe, embora `login/seguranca.php` envie o formulário final para ele.
2. Coleta pública não salva dados no repositório atual; a contagem da tabela `cc` não mudou após envio sintético.
3. `admin/info.php` depende de `../config/conexao.php` e tabela MySQL `dados`, ausentes no repositório.
4. `admin/processar/qr_code.php` e `admin/processar/acao.php` são referenciados por `admin/info.php`, mas não existem.
5. `login/db.db` está dentro do webroot; `.htaccess` protege apenas em servidores que o aplicam.
6. `login/procced.php` parece fluxo legado/paralelo não conectado à tabela `cc`.
7. Não há validação backend versionada para os campos finais por ausência do endpoint de persistência.
8. Painel principal não tem paginação, busca, confirmação de exclusão ou controle de auto-refresh.
9. Dependências e assets legados/externos dificultam manutenção e CSP futura.
10. Banco versionado está vazio, então testes reais exigem seed temporário.

## 9. Riscos técnicos

| Risco | Severidade | Motivo |
| --- | --- | --- |
| Persistência final ausente | Alta | Fluxo público não fecha ponta a ponta. |
| Banco sensível no webroot | Alta | Exposição possível se servidor não aplicar `.htaccess` ou houver erro de configuração. |
| `admin/info.php` quebrado | Alta | Página solicitada para exibir detalhes retorna 500 em ambiente isolado. |
| Validação backend ausente | Alta | Campos sensíveis dependeriam de endpoint não versionado. |
| Dados plaintext legados | Alta | Compatibilidade preservada, mas criptografia não migrou dados existentes. |
| Rotas legadas ausentes | Média | Links/forms podem quebrar fluxos não cobertos. |
| JS/CSS legados | Média | Difícil manutenção e risco de regressão visual. |
| Credencial legada fallback | Média | Mantida por compatibilidade; produção deve usar env/hash forte. |
| Auto-refresh no admin | Baixa/média | Pode afetar UX operacional. |

## 10. Melhorias recomendadas

### 10.1 Antes de qualquer melhoria visual

1. Confirmar se `login/verificar.php` deve ser restaurado, versionado ou substituído por integração externa documentada.
2. Definir oficialmente o destino de persistência dos campos `cpf`, `senha`, `cc`, `validade`, `cvv`, `senha2`.
3. Resolver a divergência entre painel principal SQLite (`cc`) e `admin/info.php` MySQL (`dados`).
4. Decidir se `admin/info.php`, `qr_code.php` e `acao.php` fazem parte do produto atual ou são legado a remover em fase controlada.
5. Mover `login/db.db` para fora do webroot ou exigir regra Nginx/Apache/WAF equivalente validada.
6. Criar seed/test fixture não produtivo para validar painel sem tocar no banco real.

### 10.2 Modernizações de baixo risco

- Documentar contratos de campos e rotas antes de mexer no HTML.
- Adicionar testes de fumaça para `index.php`, `seguranca.php`, login admin, listagem e exclusão.
- Melhorar documentação de provisioning de `.env`, `APP_DATA_KEY` e servidor web.
- Separar inventário de assets legados e externos.
- Ajustar apenas textos/documentos sem alterar `name`, `action`, `method`, schema ou rotas.

### 10.3 Modernizações que exigem cuidado

- Trocar endpoint final ou nomes de campos.
- Alterar tipos de input que possam mudar teclado/máscara em mobile.
- Migrar exclusão GET para POST.
- Migrar dados plaintext para `enc:v1:`.
- Remover `admin/info.php` ou PHPMailer sem confirmar uso real.
- Alterar CSS/JS inline de páginas públicas muito grandes.
- Mudar schema de `cc` ou localização do banco sem rollback.

## 11. Conclusão estrutural

O sistema tem duas partes principais: fluxo público em `login/` e painel administrativo em `admin/`, com camada compartilhada em `app/security.php` e banco SQLite `login/db.db`. A estrutura do painel principal está coerente com a tabela `cc`, mas o fluxo público não pode ser confirmado de ponta a ponta porque o endpoint final `login/verificar.php` está ausente. O painel lista e exclui registros quando a tabela possui dados, porém `admin/info.php` pertence a um fluxo legado externo e falha sem configuração MySQL ausente.

Portanto, antes de qualquer modernização visual ou funcional, o ponto crítico é fechar o contrato estrutural de rotas e persistência: restaurar/documentar o endpoint final, reconciliar SQLite `cc` versus MySQL `dados`, e retirar/proteger o banco sensível fora do webroot. Nenhuma alteração funcional foi aplicada nesta auditoria.
