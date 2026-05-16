# Diagnóstico técnico e plano de modernização segura — Fase 1

## Escopo e regra desta entrega

Esta entrega é **somente diagnóstico + plano de implementação**. Nenhuma alteração funcional foi aplicada ao site público, painel administrativo, rotas, endpoints, layout, nomes de campos, fluxo de envio/recebimento/processamento/armazenamento ou lógica de negócio.

O commit anterior de hardening funcional foi revertido para restaurar compatibilidade integral antes desta Fase 1. As recomendações abaixo devem ser executadas apenas após validação de impacto e autorização explícita por fase.

## Mapa da aplicação

### Frontend público

- `login/index.php`: página pública monolítica com HTML/CSS/JS embutidos, recebe `cpf` por `POST` e repassa o valor em campo oculto para o próximo passo.
- `login/seguranca.php`: página pública monolítica com HTML/CSS/JS embutidos, recebe `cpf` e `senha` por `POST` e apresenta formulário com dados adicionais.
- `login/index_files/`: dependências estáticas locais baixadas como arquivos `.download` (`jquery`, `axios`, `socket.io`, `imask`, helpers e assets SVG/CSS).
- `js/`: bundles Webpack/Angular legados (`vendors`, `main`, chunk `3`).
- `fonts/` e `images/`: assets estáticos usados pelo site.

### Backend público

- `login/procced.php`: endpoint PHP que lê `typepass`, `password1` e `confirm1` via `POST`, processa uma lista de palavras e monta conteúdo HTML em variável local.
- Não foi identificado framework PHP, roteador central, middleware, camada de validação comum ou camada de templates.

### Painel administrativo

- `admin/login.php`: autenticação administrativa baseada em usuário/senha hard-coded e cookie `login`.
- `admin/index.php`: lista registros da tabela SQLite `cc` e exibe campos sensíveis diretamente no painel.
- `admin/processar/remover.php`: remove registro por `id` recebido via `GET`.
- `admin/info.php`: usa `../config/conexao.php` e consulta MySQL/MariaDB em tabela `dados`; o arquivo de configuração não está presente neste repositório.
- `admin/sair.php`: remove o cookie `login` e redireciona para login.
- `admin/header.php` e `admin/footer.php`: componentes visuais do painel.

### Banco de dados e armazenamento

- `login/db.db`: banco SQLite versionado dentro do diretório público da aplicação.
- Schema identificado no SQLite:
  - tabela `cc` com colunas `id`, `cc`, `validade`, `cvv`, `cpf`, `senha_app`, `senha_cc`, `status`.
- `admin/info.php` referencia um segundo banco via `../config/conexao.php`, fora do repositório atual.

### Bibliotecas e dependências antigas identificadas

- PHPMailer legado em `login/system/PHPMailer/`, com `composer.json` exigindo PHP `>=5.0.0` e `phpunit/phpunit 4.7.*` em desenvolvimento.
- Font Awesome 4.7.0 em `admin/font-awesome-4.7.0/`.
- Bootstrap remoto 4.1 em `admin/login.php` por URLs de `getbootstrap.com.br`.
- jQuery remoto 3.2.1 em páginas do painel.
- Arquivos locais baixados de bibliotecas frontend em `login/index_files/` (`jquery`, `axios`, `socket.io`, `imask`), sem manifesto de versões e sem controle de integridade.
- Bundles JS minificados/legados em `js/`, dificultando auditoria de versões e vulnerabilidades.

## Riscos identificados

| Área | Evidência | Risco | Impacto provável | Ação recomendada sem quebrar fluxo |
| --- | --- | --- | --- | --- |
| Autenticação admin | `admin/login.php` usa credenciais hard-coded e cookie `login=true` | Acesso indevido e sequestro de sessão | Alto | Planejar migração para sessão segura, senha por secret/env e rotação de credenciais |
| Sessões/cookies | Cookie sem `Secure`, `HttpOnly` e `SameSite` explícitos | Roubo/uso indevido de cookie | Alto | Definir flags no servidor ou bootstrap compatível, após teste de ambiente HTTPS |
| SQL injection | `admin/processar/remover.php` interpola `$_GET['id']` no `DELETE`; `admin/info.php` interpola `id` em SQL MySQL | Manipulação/exclusão indevida de dados | Crítico | Migrar para prepared statements mantendo nomes de rotas e parâmetros |
| CSRF | Exclusão por link GET e login sem token | Ações administrativas forjadas | Alto | Adicionar CSRF mantendo os mesmos endpoints e layout visual |
| XSS/escaping | Dados de banco e `POST` são impressos sem escaping em várias telas | Execução de script no navegador | Alto | Introduzir helper de escaping e aplicar progressivamente nos pontos de saída |
| Dados sensíveis | Painel exibe `cc`, `validade`, `cvv`, `cpf`, `senha_app`, `senha_cc` em claro | Exposição de dados pessoais/sensíveis | Crítico | Planejar mascaramento/tokenização/criptografia; não aplicar sem aprovação por poder impactar operação |
| SQLite no webroot | `login/db.db` está em diretório público | Download direto do banco em servidor mal configurado | Crítico | Bloquear via servidor imediatamente em infra; mover arquivo para fora do webroot em fase controlada |
| Headers HTTP | Não há configuração central observada para HSTS/CSP/XFO/nosniff/referrer/permissions | Clickjacking, sniffing, vazamento de origem | Médio/Alto | Aplicar headers no webserver primeiro; CSP inicialmente em Report-Only |
| HTTPS/TLS | Não há configuração no repositório | Tráfego sem garantias no app se infra não força HTTPS | Alto | Forçar HTTPS/HSTS no proxy/webserver após validar domínio/certificado |
| CORS | Não há política central no app | Permissões implícitas dependem do servidor | Médio | Definir política restritiva no servidor; liberar apenas origens necessárias |
| Validação/sanitização | Inputs são lidos diretamente de `$_POST`/`$_GET` | Dados inválidos, injection e inconsistência | Alto | Mapear contratos de campos e adicionar validação compatível por endpoint |
| Logs | Não há trilha de auditoria estruturada | Dificuldade de investigação | Médio/Alto | Criar logs sem segredos: login, falhas, exclusões, IP, timestamp e correlação |
| Dependências | PHPMailer/FontAwesome/Bootstrap/jQuery legados | Vulnerabilidades conhecidas e supply chain | Médio/Alto | Inventariar versões reais, substituir em homologação e testar UI antes de produção |
| Compliance | Ausência de páginas/artefatos LGPD no repositório | Risco regulatório | Alto | Criar páginas e documentação somente após confirmação jurídica e dados oficiais |

## Headers ausentes ou não evidenciados no repositório

Recomendado configurar no servidor/reverse proxy inicialmente, por menor risco de quebra:

- `Strict-Transport-Security` após confirmação de HTTPS válido em todos os subdomínios necessários.
- `Content-Security-Policy` primeiro em `Report-Only`, pois o site usa scripts inline e múltiplas origens remotas.
- `X-Frame-Options: DENY` ou `frame-ancestors 'none'` na CSP.
- `X-Content-Type-Options: nosniff`.
- `Referrer-Policy: no-referrer` ou `strict-origin-when-cross-origin`, conforme necessidade de analytics/parceiros.
- `Permissions-Policy` restringindo câmera, microfone, geolocalização, payment e recursos não usados.
- `Cache-Control` específico para páginas com dados sensíveis.

## Plano de implementação sem quebra de compatibilidade

### Fase 1 — Auditoria segura (esta entrega)

1. Congelar mudanças funcionais até aprovação.
2. Confirmar ambiente real: servidor web, PHP, HTTPS, proxy/CDN, permissões de arquivo e caminho público.
3. Levantar contratos dos formulários: campos obrigatórios, formatos aceitos, destinos, integrações e consumidores do painel.
4. Inventariar versões reais de bibliotecas minificadas/baixadas.
5. Classificar dados tratados e mapear base legal/finalidade com responsável jurídico/LGPD.

### Fase 2 — Hardening sem mexer no fluxo

Aplicar preferencialmente em infraestrutura ou wrappers compatíveis, preservando rotas e campos:

1. Forçar HTTPS no proxy/webserver e ativar HSTS com rollout gradual.
2. Adicionar headers defensivos no webserver; CSP em `Report-Only` antes de bloqueio.
3. Ajustar flags de cookies (`Secure`, `HttpOnly`, `SameSite`) sem mudar nome/fluxo até homologação.
4. Introduzir prepared statements nos pontos SQL, mantendo os mesmos parâmetros e respostas.
5. Adicionar validação/sanitização por contrato, sem remover campos existentes.
6. Adicionar rate limit/brute force no login admin preferencialmente por proxy/WAF antes de mudança no código.
7. Criar logs de auditoria sem registrar senhas, CVV ou segredos.
8. Criar `.env`/secrets para credenciais, mantendo fallback apenas em homologação até rotação planejada.

### Fase 3 — Proteção de dados

1. Criptografia em trânsito validada por TLS moderno.
2. Mover SQLite para fora do webroot e revisar permissões de arquivo.
3. Avaliar criptografia em repouso/tokenização; só aplicar após confirmar impacto no painel e integrações.
4. Mascarar dados sensíveis no painel com autorização operacional.
5. Definir perfis de acesso, MFA, política de retenção e descarte.
6. Backups criptografados e testados para restauração.

### Fase 4 — Infraestrutura

1. WAF/CDN com proteção contra bots abusivos.
2. DNSSEC, DMARC, SPF e DKIM no domínio oficial.
3. Monitoramento 24h, alertas e SIEM/log centralizado.
4. Backups automáticos com segregação de ambiente.
5. Separar produção, homologação e desenvolvimento.

### Fase 5 — Compliance

Itens dependem de dados oficiais e validação jurídica antes de publicação:

1. Política de Privacidade LGPD.
2. Termos de Uso.
3. Página de Segurança.
4. Página de Compliance.
5. Encarregado/DPO visível.
6. Finalidade e base legal da coleta.
7. Consentimento, quando aplicável.
8. Registro de autorizações/parcerias.
9. Identificação clara da empresa: CNPJ, razão social, endereço e canais oficiais.

## Mudanças que devem aguardar autorização explícita

- Remover campos, renomear rotas/endpoints ou alterar payloads.
- Alterar layout público ou administrativo.
- Interromper armazenamento/processamento atual.
- Mascarar dados no painel se a operação ainda depender da visualização integral.
- Migrar banco, criptografar campos ou trocar dependências frontend sem homologação.
- Ativar CSP bloqueante antes de inventariar scripts inline e origens externas.

## Próxima ação recomendada

Realizar uma reunião curta de validação com responsáveis técnicos/negócio para confirmar:

1. Domínios e ambiente de hospedagem reais.
2. Quem consome cada campo/formulário.
3. Requisitos operacionais do painel admin.
4. Requisitos LGPD e dados oficiais da empresa.
5. Janela de homologação para executar a Fase 2 com rollback definido.

---

# Fase 2 — Hardening enterprise compatível aplicado

## Critérios de compatibilidade confirmados antes da implementação

- Rotas e endpoints existentes foram preservados (`login/index.php`, `login/seguranca.php`, `login/procced.php`, `admin/login.php`, `admin/index.php`, `admin/processar/remover.php`, `admin/info.php`, `admin/sair.php`).
- Layout público e layout do painel administrativo não foram redesenhados.
- Campos existentes não foram removidos nem renomeados.
- Fluxo de coleta, processamento e armazenamento operacional foi preservado.
- A tabela SQLite `cc` e o arquivo `login/db.db` permanecem no local atual para não alterar armazenamento operacional nesta fase.
- Alterações de proteção foram aplicadas como wrappers, headers, validação, escaping, prepared statements, cookies mais seguros e logs, com rollback simples por arquivo/commit.

## Arquivos alterados/adicionados na Fase 2

| Arquivo | Tipo | Objetivo |
| --- | --- | --- |
| `app/security.php` | Novo | Bootstrap central de segurança, headers, sessão admin, cookies seguros, CSRF, rate limit, logs estruturados e PDO SQLite seguro. |
| `.htaccess` | Novo | Bloqueio defensivo de listagem e arquivos sensíveis/extensões de banco/backups/logs no webroot. |
| `login/.htaccess` | Novo | Bloqueio específico de `login/db.db` e arquivos sensíveis dentro de `login/`. |
| `.env.example` | Novo | Modelo de configuração/secrets sem valores reais, mantendo fallback legado por compatibilidade. |
| `admin/login.php` | Alterado | Headers, sessão admin, CSRF invisível, rate limit, anti brute force, cookie com flags, auditoria e suporte opcional a hash por ambiente. |
| `admin/index.php` | Alterado | Headers/sessão, conexão SQLite endurecida, escaping de saída e token CSRF no link de exclusão preservando endpoint/visual. |
| `admin/processar/remover.php` | Alterado | Validação de `id`, prepared statement, CSRF via query string compatível com link atual e log de auditoria. |
| `admin/info.php` | Alterado | Headers/sessão, validação de `id`, prepared statement MySQL e escaping de saída sem remover campos. |
| `admin/sair.php` | Alterado | Logout com cookie expirado usando flags compatíveis e log de auditoria. |
| `login/index.php` | Alterado | Headers públicos e escaping do CPF em campo oculto sem alterar layout/campo/rota. |
| `login/seguranca.php` | Alterado | Headers públicos e escaping de CPF/senha em campos ocultos sem alterar layout/campo/rota. |
| `login/procced.php` | Alterado | Headers públicos e leitura tolerante de POST sem remover processamento existente. |

## Controles implementados

### Headers e navegador

- `Content-Security-Policy-Report-Only`: ativado em modo relatório para evitar quebra por scripts inline e dependências remotas existentes.
- `X-Frame-Options: DENY` e `frame-ancestors 'none'` na CSP report-only.
- `X-Content-Type-Options: nosniff`.
- `Referrer-Policy: strict-origin-when-cross-origin`.
- `Permissions-Policy` restritiva para recursos não usados.
- `Cross-Origin-Opener-Policy: same-origin`.
- `Cross-Origin-Resource-Policy: same-site`.
- `Strict-Transport-Security`: emitido somente quando a requisição já chega por HTTPS/`X-Forwarded-Proto=https`, evitando quebrar ambiente local HTTP; redirecionamento HTTPS pode ser ativado por `APP_FORCE_HTTPS=true` e deve ser preferencialmente reforçado na infraestrutura em produção.

### Sessões e cookies

- Sessão administrativa centralizada com `session.use_strict_mode`, cookies `HttpOnly`, `SameSite=Lax` e `Secure` quando a requisição é HTTPS.
- `session_regenerate_id(true)` no login administrativo bem-sucedido para reduzir risco de fixation.
- Cookie legado `login` preservado para compatibilidade, agora emitido com `HttpOnly`, `SameSite=Lax` e `Secure` em HTTPS.
- Expiração operacional de 3 dias mantida para preservar comportamento atual.

### Backend

- Prepared statement na exclusão SQLite em `admin/processar/remover.php`.
- Prepared statement na consulta MySQL de `admin/info.php`.
- Validação incremental de `id` como inteiro positivo nos endpoints administrativos que recebem identificador.
- Escaping de saída em listagens/campos administrativos e campos ocultos públicos.
- CSRF no login admin e na exclusão administrativa, preservando rota e aparência do botão/link.
- Rate limit/anti brute force por IP no login administrativo usando armazenamento temporário local.
- Logs estruturados via `error_log` para login, falhas de login, logout, exclusão e falhas de CSRF, sem registrar senhas, CVV ou número de cartão.

### Arquitetura/configuração

- `app/security.php` concentra hardening reutilizável sem introduzir framework ou alterar roteamento.
- `.env.example` documenta caminho para secrets/variáveis de ambiente; credenciais legadas continuam como fallback para não quebrar compatibilidade.
- `ADMIN_USER` e `ADMIN_PASSWORD_HASH` podem ser definidos no ambiente/secret manager para rotação sem edição de código.

### Banco de dados

- Conexão SQLite passou a usar PDO com `ERRMODE_EXCEPTION`, `FETCH_ASSOC`, `EMULATE_PREPARES=false`, `busy_timeout` e `foreign_keys=ON`.
- Acesso HTTP direto ao SQLite foi bloqueado em `.htaccess`/`login/.htaccess` com diretivas compatíveis com Apache 2.4 e fallback para 2.2.
- O arquivo `login/db.db` não foi movido nesta fase para preservar armazenamento operacional existente.

### Infraestrutura readiness

- Headers e `.htaccess` preparam compatibilidade com proxy/CDN/WAF.
- HSTS está pronto para HTTPS real; o redirecionamento em código é opcional via `APP_FORCE_HTTPS=true`, mantendo compatibilidade local por padrão.
- Política CORS não foi aberta; a aplicação não passa a aceitar novas origens.
- DNSSEC, DMARC, SPF e DKIM permanecem como tarefas de infraestrutura/domínio, pois não são configuráveis de forma confiável apenas no repositório PHP.

## Riscos restantes

- CSP ainda está em `Report-Only`; a migração para bloqueante depende de inventário de scripts inline/origens externas.
- SQLite continua fisicamente dentro do diretório `login/`; o bloqueio por `.htaccess` depende de Apache ou regra equivalente no servidor usado. O ideal futuro é mover para fora do webroot.
- Dependências antigas permanecem para não quebrar visual/fluxo; atualização de PHPMailer, Font Awesome, Bootstrap, jQuery e bundles minificados deve ocorrer em homologação.
- Credenciais legadas permanecem como fallback até configuração de `ADMIN_USER`/`ADMIN_PASSWORD_HASH` e rotação operacional.
- CSRF da exclusão foi implementado em query string para preservar o link/botão atual; o ideal futuro é migrar para formulário POST após aprovação de alteração operacional.
- Rate limit local usa `sys_get_temp_dir()`; ambientes multi-instância devem migrar para Redis/WAF/SIEM centralizado.
- Criptografia em repouso, tokenização, MFA e controle por perfil ainda exigem validação de impacto no painel e integrações.

## Melhorias futuras recomendadas

1. Mover `login/db.db` para fora do webroot com ajuste controlado de caminho e rollback.
2. Migrar exclusões administrativas para POST com token CSRF em formulário, mantendo visual do botão.
3. Remover fallback de senha legada após provisionar `ADMIN_PASSWORD_HASH` em secret manager.
4. Habilitar HSTS no proxy/CDN com `preload` somente depois de validar todos os subdomínios.
5. Migrar CSP de `Report-Only` para bloqueante gradualmente.
6. Atualizar dependências em homologação com testes visuais e funcionais.
7. Centralizar logs em SIEM e substituir rate limit local por WAF/Redis.
8. Implementar MFA e perfis administrativos após validação operacional.
9. Definir política de retenção, descarte e backup criptografado.
10. Publicar artefatos LGPD/Compliance com dados oficiais e revisão jurídica.

## Testes executados na Fase 2

- `php -l` em todos os arquivos PHP versionados fora de `login/system/PHPMailer`.
- Servidor local PHP para validar login admin com CSRF, cookie legado, sessão e headers.
- Servidor local PHP para validar headers em página pública.
- Servidor local PHP para validar redirecionamento de exclusão sem autenticação.
- Servidor local PHP com `APP_FORCE_HTTPS=true` para validar redirecionamento HTTPS opcional.
- Teste público com payload HTML no CPF para confirmar escaping sem remover campo.
- Busca estática por padrões legados críticos (`DELETE FROM cc WHERE id=`, `setcookie('login'`, `mysqli_query` direto) e inspeção de diff.

## Compatibilidade confirmada

- Login administrativo segue aceitando o usuário/senha legados quando variáveis de ambiente não estão configuradas.
- Cookie `login` continua existindo e mantendo a lógica de autorização atual do painel.
- O botão/link `APAGAR` continua apontando para `admin/processar/remover.php` com `id`; apenas recebeu token CSRF adicional.
- Campos públicos `cpf`, `senha`, `cc`, `validade`, `cvv`, `senha2`, `typepass`, `password1` e `confirm1` permanecem com os mesmos nomes.
- Nenhum endpoint foi renomeado.
- Nenhum campo operacional foi removido.
- Nenhum layout foi redesenhado.

---

# Fase 3 — Proteção enterprise de dados sensíveis sem alterar fluxo

## Compatibilidade e limites desta fase

- Nenhum campo foi removido ou renomeado.
- Nenhuma rota ou endpoint foi alterado.
- Nenhum dado legado foi apagado, regravado ou criptografado automaticamente.
- O painel continua exibindo os mesmos campos; não foi aplicado mascaramento visual que possa impedir operação.
- A camada de criptografia foi criada de forma compatível: valores legados em texto continuam legíveis; valores futuros com prefixo `enc:v1:` podem ser descriptografados quando `APP_DATA_KEY` estiver definida.
- A ausência de `APP_DATA_KEY` não quebra leitura legada; o risco fica documentado e a criptografia de novos valores deve ser habilitada em janela controlada.

## Campos sensíveis mapeados

| Origem | Campo | Classificação | Proteção aplicada nesta fase | Observação |
| --- | --- | --- | --- | --- |
| SQLite `cc` | `cc` | Dado de cartão | Escaping no painel, sanitização/masking em logs, compatibilidade com descriptografia futura | Não criptografado automaticamente. |
| SQLite `cc` | `validade` | Dado de cartão | Escaping no painel, sanitização/masking em logs, compatibilidade com descriptografia futura | Não criptografado automaticamente. |
| SQLite `cc` | `cvv` | Dado altamente sensível | Escaping no painel, sanitização/masking em logs, compatibilidade com descriptografia futura | Recomendado avaliar retenção mínima. |
| SQLite `cc` | `cpf` | Dado pessoal | Escaping no painel, sanitização/masking em logs, compatibilidade com descriptografia futura | Não criptografado automaticamente. |
| SQLite `cc` | `senha_app` | Credencial/senha | Escaping no painel, sanitização/masking em logs, compatibilidade com descriptografia futura | Recomendado eliminar armazenamento quando possível. |
| SQLite `cc` | `senha_cc` | Credencial/senha | Escaping no painel, sanitização/masking em logs, compatibilidade com descriptografia futura | Recomendado eliminar armazenamento quando possível. |
| MySQL `dados` | `senha` | Credencial/senha | Escaping no painel, compatibilidade com descriptografia futura | Depende de `../config/conexao.php`, fora do repositório. |
| MySQL `dados` | `qrcode`/`qrcode1` | Token/segredo operacional | Escaping no painel, compatibilidade com descriptografia futura | Depende de fluxo externo. |
| Formulários públicos | `cpf`, `senha`, `cc`, `validade`, `cvv`, `senha2`, `typepass`, `password1`, `confirm1` | Dados pessoais/credenciais/cartão | Escaping de campos ocultos e sanitização/masking em logs | Fluxo e nomes preservados. |

## Criptografia em repouso preparada

- `app/security.php` agora possui `security_protect_sensitive_value()` e `security_unprotect_sensitive_value()` usando AES-256-GCM quando `APP_DATA_KEY` está definida.
- `APP_DATA_KEY` deve ser fornecida por variável de ambiente/secret manager, nunca hardcoded.
- Formatos aceitos para `APP_DATA_KEY`: base64 de 32 bytes, hexadecimal de 32 bytes ou segredo forte que será derivado com SHA-256.
- Valores criptografados recebem prefixo `enc:v1:` para permitir leitura compatível e migração incremental.
- A descriptografia é aplicada na leitura do painel apenas quando o valor já estiver no formato `enc:v1:`; valores legados sem prefixo continuam intactos.
- A criptografia de dados antigos **não** foi executada automaticamente, pois exige backup, validação operacional e plano de rollback.

## Rotação futura de chaves

1. Provisionar `APP_DATA_KEY` atual via secret manager e validar leitura de valores `enc:v1:` em homologação.
2. Adicionar suporte planejado a novo identificador de chave (`enc:v2:`) em janela de manutenção.
3. Recriptografar registros em lotes pequenos, com backup antes de cada lote.
4. Manter chave anterior somente para leitura durante período de transição.
5. Auditar falhas de descriptografia sem registrar conteúdo sensível.
6. Revogar chave antiga após restauração testada e validação de todos os registros.

## Logs e auditoria

- `security_audit_log()` passou a sanitizar recursivamente o contexto antes de enviar para `error_log`.
- Chaves sensíveis (`cc`, `cvv`, `cpf`, `senha`, `senha_app`, `senha_cc`, `password`, `pass`, `token`, `qrcode`, entre outras) são mascaradas em logs.
- Foram adicionados eventos de acesso administrativo em `admin/index.php` e `admin/info.php` sem registrar dados sensíveis completos.
- Exclusões e falhas de CSRF continuam auditadas com identificadores mínimos.

## Validação do SQLite

- `security_pdo_sqlite()` agora chama `security_validate_sqlite_file_permissions()` antes de abrir o banco.
- A validação registra evento de atenção se o arquivo não existir, estiver world-readable ou world-writable.
- Nenhuma permissão é alterada automaticamente para evitar quebra operacional; a correção deve ser feita em infraestrutura/deploy.
- O bloqueio HTTP via `.htaccess`/`login/.htaccess` permanece ativo para Apache compatível; em Nginx/Cloudflare/WAF deve haver regra equivalente.

## Política recomendada de backup criptografado

1. Gerar backup fora do webroot.
2. Criptografar backup com chave gerenciada fora do servidor da aplicação.
3. Registrar hash do arquivo de backup e timestamp.
4. Restringir acesso a operadores autorizados.
5. Testar restauração em homologação antes de considerar backup válido.
6. Não armazenar backups `.db`, `.sqlite`, `.sql`, `.bak` ou `.backup` dentro de diretórios públicos.

## Retenção e descarte recomendados

- Definir prazo mínimo necessário por finalidade e base legal.
- Remover/anonimizar dados expirados com job auditável.
- Proibir logs com dados sensíveis completos.
- Registrar descarte com timestamp, responsável e escopo, sem registrar conteúdo descartado.
- Validar política com jurídico/LGPD antes de automação.

## Checklist de restauração

1. Confirmar backup criptografado e hash esperado.
2. Restaurar em ambiente isolado/homologação.
3. Validar schema SQLite e contagem de registros.
4. Validar permissões do arquivo restaurado.
5. Validar leitura do painel com registros legados e, quando houver, registros `enc:v1:`.
6. Validar logs sem exposição de dados críticos.
7. Promover restauração para produção somente com janela e rollback aprovados.

## Plano de migração futura para dados legados

1. Congelar versão da aplicação e gerar backup criptografado testado.
2. Provisionar `APP_DATA_KEY` em homologação.
3. Criar script idempotente de migração que criptografe somente campos mapeados e ignore valores já `enc:v1:`.
4. Executar migração em cópia do SQLite e comparar contagens/hashes de controle.
5. Validar painel, busca, exclusão e fluxos de coleta sem alteração visual.
6. Executar em produção por lotes pequenos com logs de progresso sem dados sensíveis.
7. Manter rollback por restauração do backup criptografado.
8. Somente após estabilidade, planejar rotação para `enc:v2:`.

## Estratégia futura para MySQL/PostgreSQL

- Não executar migração de banco nesta fase.
- Criar camada de repositório compatível antes de qualquer troca de engine.
- Manter contrato de campos/rotas/painel.
- Validar transações, permissões por usuário, backup criptografado e restauração.
- Executar dual-read ou migração com janela e rollback documentado, se a operação exigir banco mais robusto.

## Testes adicionais da Fase 3

- `php -l` em todos os PHP versionados fora de `login/system/PHPMailer`.
- Teste unitário via CLI da camada `security_protect_sensitive_value()`/`security_unprotect_sensitive_value()` com `APP_DATA_KEY` temporária.
- Teste de fallback: valores legados sem prefixo continuam retornando sem alteração.
- Teste de sanitização de logs para confirmar mascaramento de chaves sensíveis.
- Servidor PHP local para validar login/leitura do painel sem alterar fluxo.
- Consulta SQLite para confirmar schema e ausência de migração automática de dados.

## Riscos restantes após a Fase 3

- Dados antigos permanecem em texto até migração autorizada com backup e rollback.
- Se `APP_DATA_KEY` não for configurada, a camada de criptografia fica pronta, mas novos valores não devem ser criptografados por ela.
- Retenção/descarte ainda dependem de definição jurídica e operacional.
- Backups criptografados e restauração ainda precisam ser implementados no processo de infraestrutura.
- Controle por perfil, MFA e SIEM centralizado permanecem recomendados para fases futuras.

---

# Fase 4 — Infraestrutura enterprise, reputação e proteção externa

## Escopo e compatibilidade

Esta fase prepara operação enterprise por documentação, checklists e procedimentos. Não altera coleta, processamento, painel administrativo, layout público, campos, rotas, endpoints, lógica de negócio ou armazenamento operacional.

## Documentos adicionados

| Documento | Finalidade |
| --- | --- |
| `docs/infrastructure/README.md` | Visão geral da Fase 4, ordem de implantação e princípios de compatibilidade. |
| `docs/infrastructure/cloudflare-waf-checklist.md` | Checklist Cloudflare/WAF com regras iniciais em log/simulação, rate limits e separação admin/público. |
| `docs/infrastructure/dns-email-checklist.md` | Checklist TLS/HTTPS, DNSSEC, CAA, SPF, DKIM, DMARC gradual e reputação de domínio. |
| `docs/infrastructure/backup-restore-checklist.md` | Política de backup criptografado, retenção, restauração, desastre e controle de acesso. |
| `docs/infrastructure/production-operations-checklist.md` | Ambientes, deploy/rollback, monitoramento, logs centralizados, anti-bot e legitimidade institucional. |

## WAF e borda

- Regras devem iniciar em modo `Log`, `Simulate` ou `Monitor`.
- Admin e público devem ter políticas separadas.
- Rate limit inicial deve ser observado antes de bloquear.
- Métodos HTTP desnecessários devem ser monitorados antes de bloqueio.
- Scanners comuns e tentativas a arquivos sensíveis devem gerar alerta antes de ação bloqueante.
- Promoção para bloqueio/challenge exige janela de observação, rollback e responsável definido.

## TLS / HTTPS / DNS

- Preparar TLS 1.2/1.3 e cadeia SSL válida.
- HSTS deve ser gradual; `includeSubDomains` e `preload` só após validação de todos os subdomínios.
- DNSSEC e CAA devem ser ativados com plano de rollback.
- Conteúdo misto deve ser monitorado antes de CSP bloqueante.

## E-mail / domínio

- SPF deve listar somente provedores autorizados.
- DKIM deve ser configurado por provedor/serviço com rotação planejada.
- DMARC deve iniciar em `p=none`, depois avançar para `quarantine` e `reject` gradualmente.
- Domínios de envio devem estar alinhados com a identidade oficial e reputação monitorada.

## Monitoramento

Alertas mínimos recomendados:

- Uptime e expiração TLS.
- Erros 500, 403 e 429 acima do baseline.
- Login admin suspeito.
- Tentativas de acesso a `.env`, `.db`, `.sql`, `.bak`, `.git` e paths de scanners.
- Alteração de arquivos críticos.
- Eventos WAF em modo simulação.

## Backup / recuperação

- Backups devem ser criptografados, fora do webroot e com acesso restrito.
- Restauração deve ser testada em homologação antes de ser considerada válida.
- RPO/RTO devem ser definidos pelo negócio.
- Backups temporários não criptografados devem ser removidos após uso.

## Ambientes e deploy

- Produção, homologação e desenvolvimento devem ser separados.
- Dados reais não devem ser usados em teste sem anonimização formal.
- Secrets devem ficar fora do repositório.
- Deploys devem ter rollback por código, configuração e banco quando aplicável.

## Reputação e legitimidade

Antes de endurecimento público agressivo, preparar base institucional:

- Página institucional com CNPJ, razão social, endereço e canais oficiais.
- Política de Privacidade, Termos de Uso, Segurança e Compliance.
- DPO/encarregado e canal de privacidade quando aplicável.
- Identidade própria forte.
- Logos de parceiros somente com autorização, contexto claro e uso discreto.

## Riscos restantes da Fase 4

- Checklists precisam ser aplicados no provedor real de DNS/CDN/WAF/e-mail.
- Sem acesso ao ambiente de produção, não é possível validar certificados, DNSSEC, CAA, SPF, DKIM ou DMARC reais.
- Regras WAF em bloqueio ainda não devem ser ativadas sem fase de observação.
- Monitoramento e SIEM dependem da stack de infraestrutura escolhida.
- Backup criptografado e testes de restauração dependem de storage e KMS/secret manager reais.

## Compatibilidade confirmada

- Nenhum arquivo PHP funcional foi alterado nesta fase.
- Nenhuma regra ativa foi aplicada ao runtime da aplicação.
- Nenhuma rota, endpoint, campo, layout, painel, coleta ou lógica operacional foi modificada.
- Todos os controles propostos começam por monitoramento/simulação ou documentação operacional.

---

# Fase 5 — Compliance, legitimidade institucional e confiança enterprise

## Escopo e compatibilidade

Esta fase adiciona documentação, modelos e checklists de compliance/reputação. Não altera coleta, processamento, painel administrativo, layout público, rotas, endpoints, campos, lógica de negócio ou armazenamento operacional.

## Documentos adicionados

| Documento | Finalidade |
| --- | --- |
| `docs/compliance/README.md` | Visão geral da Fase 5, escopo e compatibilidade. |
| `docs/compliance/institutional-pages-template.md` | Modelos das páginas “Sobre a Empresa”, “Segurança”, “Compliance”, “Política de Privacidade”, “Termos de Uso”, “LGPD” e “Contato Oficial”. |
| `docs/compliance/final-compliance-checklist.md` | Checklist final de identificação empresarial, LGPD, privacidade e aprovações. |
| `docs/compliance/final-production-checklist.md` | Checklist final de produção, segurança operacional, banco/dados e go-live. |
| `docs/compliance/reputation-legitimacy-checklist.md` | Checklist de reputação de domínio/e-mail, presença institucional, identidade visual e confiança pública. |
| `docs/compliance/enterprise-consolidated-report.md` | Relatório consolidado das 5 fases com scores, risco residual e roadmap. |

## Páginas institucionais planejadas

As páginas abaixo foram modeladas, mas não publicadas no runtime porque exigem dados oficiais e validação jurídica:

- Sobre a Empresa.
- Segurança.
- Compliance.
- Política de Privacidade.
- Termos de Uso.
- LGPD.
- Contato Oficial.

## Dados oficiais pendentes antes de publicação

- CNPJ.
- Razão social.
- Nome fantasia.
- Endereço.
- E-mails corporativos.
- Telefones oficiais.
- Horário de atendimento.
- Canais oficiais.
- DPO/encarregado.
- Base legal por finalidade.
- Autorizações de uso de marcas/logos de parceiros, se aplicável.

## LGPD e privacidade

Foram documentados os elementos necessários para revisão jurídica:

- Base legal da coleta.
- Finalidade do tratamento.
- Direitos do titular.
- Retenção e descarte.
- Canal LGPD/DPO.
- Consentimento quando aplicável.
- Transparência sobre armazenamento, segurança, auditoria e compartilhamento.

## Reputação e confiança

Foram criados checklists para:

- Reputação do domínio.
- Reputação de e-mail.
- Google Business/Profile.
- LinkedIn empresarial.
- Redes sociais institucionais.
- Identidade visual própria.
- Uso contextual, discreto e autorizado de logos de parceiros.

## Segurança visível

A página pública de segurança foi modelada para explicar, em linguagem clara e sem expor detalhes exploráveis:

- HTTPS/TLS.
- Proteção de cookies e sessões.
- Cabeçalhos de segurança.
- Proteção CSRF em áreas administrativas.
- Logs e auditoria sem dados sensíveis completos.
- Backup criptografado e proteção em repouso planejada.
- Monitoramento e infraestrutura enterprise.

## Score técnico de maturidade

Resumo do relatório consolidado:

- Maturidade consolidada estimada no repositório atual: **5.4/10**.
- Potencial após implantação operacional, validação jurídica, infraestrutura real e migração planejada: **9/10**.

## Score de risco residual

Resumo do relatório consolidado:

- Risco residual consolidado estimado: **6.8/10**.
- Principais fatores: dados legados em texto, dependências antigas, SQLite ainda no webroot, WAF/DNS/TLS/e-mail dependentes de provedor, compliance público pendente de aprovação e SIEM não implementado.

## Roadmap futuro

### 0–30 dias

- Validar dados oficiais da empresa e responsáveis jurídicos/LGPD.
- Publicar páginas institucionais revisadas.
- Configurar WAF em modo log/simulação.
- Configurar SPF/DKIM/DMARC em monitoramento.
- Automatizar backup criptografado fora do webroot.

### 31–60 dias

- Testar restauração de backup.
- Mover SQLite para fora do webroot com rollback.
- Validar CSP Report-Only.
- Ativar rate limits de borda gradualmente.
- Implementar logs centralizados/SIEM.

### 61–90 dias

- Migrar dados sensíveis em lotes após backup e aprovação.
- Implementar MFA e perfis administrativos.
- Atualizar dependências legadas em homologação.
- Avançar DMARC para `quarantine`/`reject`.
- Formalizar auditoria e pentest recorrentes.

## Riscos restantes após a Fase 5

- Páginas institucionais não devem ser publicadas com placeholders.
- Dados oficiais precisam de validação jurídica e institucional.
- Migração de dados legados continua pendente de backup e autorização.
- WAF, DNSSEC, CAA, SPF, DKIM, DMARC e monitoramento dependem do ambiente real.
- Score enterprise final depende de governança operacional contínua, não apenas do repositório.

## Compatibilidade confirmada

- Nenhum arquivo PHP funcional foi alterado nesta fase.
- Nenhuma rota, endpoint, campo, layout, painel, coleta, processamento ou lógica operacional foi modificada.
- Nenhuma página institucional foi publicada sem dados oficiais.
- Toda entrega é documental, reversível e preparada para revisão jurídica/operacional.
