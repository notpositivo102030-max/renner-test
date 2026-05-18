# Hardening de segurança — MFA/admin e preservação de fluxo público — 2026-05-18

## Escopo desta entrega

Esta entrega corrige a direção da tentativa anterior: **nenhum layout público, imagem, texto público, ordem de campos públicos, fluxo de preenchimento público ou fluxo de navegação público foi alterado**. As mudanças ficaram concentradas em controles internos de segurança, painel administrativo, sessão, MFA, rate limit, logs, headers já centralizados e documentação operacional.

A única alteração visual adicionada é no **login administrativo**: campo `MFA`, estritamente necessário para TOTP. Não houve mudança em imagens, CSS, textos públicos ou telas públicas.

## Stack e superfície revisadas

- PHP procedural sem framework central.
- Painel administrativo em `admin/login.php`, `admin/index.php`, `admin/info.php`, `admin/sair.php` e `admin/processar/remover.php`.
- SQLite em `login/db.db` com tabela `cc`; caminho configurável via `APP_DB_PATH`.
- Segundo banco externo referenciado por `admin/info.php` via `../config/conexao.php`, fora do repositório.
- Dependência PHPMailer legada foi mantida nesta entrega para não remover integração potencial sem homologação funcional; apenas `extras/htmlfilter.php` recebeu ajuste sintático de compatibilidade para passar em `php -l` no PHP atual.
- Fluxos públicos principais seguem em `login/index.php`, `login/seguranca.php` e `login/procced.php` sem alteração de campos/rotas/ordem.
- Templates operacionais de NGINX, PHP-FPM, php.ini, WAF/CRS e workflow DevSecOps foram adicionados como arquivos não ativos; exigem aplicação controlada em staging/produção.

## Mudanças aplicadas

1. **MFA/TOTP no admin**
   - Login administrativo agora exige `ADMIN_TOTP_SECRET` além de `ADMIN_USER` e `ADMIN_PASSWORD_HASH`.
   - TOTP é validado no backend com janela curta de tolerância de horário.
   - Falhas são registradas em auditoria sem gravar senha, token ou código MFA completo.

2. **Sessão e fixation**
   - Mantida regeneração de `session_id` após login administrativo.
   - Mantida expiração por inatividade via `ADMIN_SESSION_TIMEOUT`.
   - Logout continua invalidando sessão e cookies.
   - Cookies continuam `HttpOnly`, `SameSite=Lax` e `Secure` quando servidos via HTTPS.

3. **Rate limit**
   - Rate limit agora mantém buckets por IP e por sessão quando houver sessão ativa, reduzindo colisões em IP compartilhado sem permitir bypass simples por troca de sessão.
   - Arquivos temporários de rate limit são gravados com permissão restrita (`0600`).

4. **Proteção de dados sensíveis no admin**
   - Dados de cartão, validade, CVV, CPF e senhas exibidos em listagens administrativas foram mascarados.
   - Dados sensíveis em `admin/info.php` foram mascarados nas áreas de leitura.
   - Campos editáveis operacionais não foram convertidos para valores mascarados para evitar submissão acidental de máscara e quebra de fluxo.

5. **Segredos**
   - A credencial `AUTHORIZATION_CANAL` deixou de ficar hardcoded nas páginas públicas e passou a ser lida de `PUBLIC_AUTHORIZATION_CANAL`.
   - Este valor deve ser configurado no ambiente/cofre de segredos antes do deploy se a integração pública depender dele.

6. **CSRF/admin**
   - Link administrativo remanescente para ação destrutiva em `admin/info.php` passou a incluir CSRF já exigido por `admin/processar/remover.php`.

7. **Logs/auditoria**
   - Sanitização de logs foi ampliada para mascarar chaves que contenham nomes de campos sensíveis, incluindo `mfa`, `otp`, `totp`, `authorization`, `api_key` e `secret`.
   - Valores sensíveis complexos/arrays são redigidos sem gerar warnings.

8. **Infraestrutura e DevSecOps prontos para execução**
   - Adicionados templates de NGINX, PHP-FPM, PHP hardening e WAF enterprise/ModSecurity CRS.
   - Adicionado workflow de segurança com lint PHP, Composer audit, secret scan e busca por padrões de SQL/execução dinâmica insegura.
   - Criado runbook operacional para WAF, DDoS, TLS, Linux hardening, SIEM/SOC, backup, rollback, DevSecOps e resposta a incidentes.

## Arquivos alterados

- `app/security.php`
- `admin/login.php`
- `admin/index.php`
- `admin/info.php`
- `login/index.php`
- `login/seguranca.php`
- `docs/security-hardening-2026-05-18.md`
- `login/system/PHPMailer/extras/htmlfilter.php`
- `.github/workflows/security.yml`
- `deploy/security/nginx-site.conf`
- `deploy/security/php-fpm-pool.conf`
- `deploy/security/php-security.ini`
- `deploy/security/modsecurity-crs-notes.md`
- `docs/security-enterprise-operations.md`

## Vulnerabilidades reduzidas

- Ausência de MFA no painel administrativo.
- Brute force com controle insuficiente por apenas um bucket de rate limit.
- Exposição administrativa de cartão/CVV/CPF/senhas em claro.
- Token/credencial Basic hardcoded em páginas públicas.
- Logs com risco de manter chaves sensíveis por nomes parciais ou valores estruturados.
- Ação administrativa destrutiva sem CSRF no link remanescente de `admin/info.php`.

## Riscos restantes

- `login/db.db` ainda existe no webroot; `.htaccess` protege apenas Apache/LiteSpeed com `AllowOverride` ativo. Nginx/Caddy/CDN exigem regra equivalente.
- PHPMailer legado permanece para não remover integração sem homologação; foi ajustado apenas para lint de sintaxe no PHP atual e deve ser atualizado/removido em sprint específica com teste funcional.
- Bibliotecas frontend legadas/minificadas continuam sem lockfile/manifests confiáveis.
- CSP continua em `Report-Only` para evitar quebra de scripts legados; enforcement deve ocorrer após coleta de violações em staging.
- `../config/conexao.php` não está versionado; permissões, TLS e usuário do banco externo precisam ser auditados no ambiente real.
- DAST real depende de uma URL de staging, não disponível neste workspace.
- WAF enterprise, anti-DDoS, SIEM/SOC, DNSSEC, EDR, hardening Linux e backups criptografados dependem do provedor/servidor e estão prontos como runbook/template, não ativados automaticamente pelo código.

## Segredos necessários para deploy

- `ADMIN_USER`: usuário administrativo.
- `ADMIN_PASSWORD_HASH`: hash bcrypt/Argon2id gerado fora do repositório.
- `ADMIN_TOTP_SECRET`: segredo TOTP Base32, armazenado em variável de ambiente/cofre.
- `PUBLIC_AUTHORIZATION_CANAL`: valor de autorização público atualmente usado pelo frontend legado, se a integração depender dele.
- `APP_FORCE_HTTPS=true`: obrigatório em produção com TLS válido.
- `APP_DATA_KEY`: chave de 32 bytes para criptografia de dados sensíveis legados quando necessário.
- `APP_DB_PATH`: caminho fora do webroot para SQLite, recomendado após homologação.

## Instruções de deploy

1. Configurar todos os segredos acima no ambiente ou cofre de segredos antes de publicar.
2. Sincronizar `ADMIN_TOTP_SECRET` com o aplicativo autenticador dos operadores autorizados.
3. Ativar HTTPS no proxy/servidor e definir `APP_FORCE_HTTPS=true`.
4. Aplicar os templates de `deploy/security/` ajustados ao domínio/paths reais ou controles equivalentes do provedor.
5. Validar regra de bloqueio para `*.db`, `*.sqlite`, backups, logs e `.env` no servidor real.
6. Publicar primeiro em staging.
7. Executar o checklist pós-deploy abaixo.
8. Promover para produção somente após login admin com MFA, fluxos públicos principais e ações administrativas passarem.

## Plano de rollback

1. Reverter o commit desta entrega com `git revert <commit>`.
2. Restaurar variáveis de ambiente anteriores se o login admin ficar indisponível.
3. Limpar cookies/sessões administrativas dos navegadores afetados.
4. Validar `GET /login/index.php`, `POST /login/seguranca.php`, `GET /admin/login.php`, login admin e `GET /admin/index.php`.
5. Manter regras de bloqueio de banco/arquivos sensíveis mesmo em rollback.

## Checklist pós-deploy

- `GET /login/index.php` retorna 200 e HTML público esperado.
- `POST /login/seguranca.php` mantém rota e formulário público esperado.
- `GET /admin/login.php` retorna 200, exibe campo MFA e mantém os demais elementos do formulário.
- `POST /admin/login.php` com usuário/senha/MFA corretos retorna 302 para `index.php`.
- `POST /admin/login.php` com MFA incorreto não autentica e não revela detalhes técnicos.
- `GET /admin/index.php` sem sessão válida redireciona para login.
- Dados sensíveis no admin aparecem mascarados.
- `/login/db.db` retorna 403/404 no servidor real.
- Logs contêm eventos de auditoria sem senha, cartão, CVV, token, código MFA ou segredos completos.
