# Relatório consolidado final — hardening técnico sem alteração visual

## Escopo

Foram aplicadas melhorias internas de segurança, compatibilidade, auditoria, infraestrutura e governança sem alterar layout, identidade visual, textos públicos, placeholders, botões, nomes de campos, fluxo visual ou estrutura visual das páginas públicas.

## Alterações técnicas aplicadas

1. `admin/info.php` foi neutralizado como fluxo legado MySQL quebrado: continua protegido por sessão admin, registra auditoria e redireciona ao painel principal.
2. `login/procced.php` foi neutralizado como endpoint legado órfão: mantém resposta visual mínima existente (`window.close`) e deixa de processar payload sensível.
3. `login/verificar.php` deixou de incluir valores sensíveis no contexto de auditoria; registra apenas presença e tamanho dos campos.
4. `app/security.php` recebeu request ID por requisição, header `X-Request-Id`, auditoria com correlação, opção `APP_CSP_ENFORCE`, cache no-store para admin, diretório configurável para rate limit e auditoria do caminho SQLite efetivo.
5. PHPMailer legado foi isolado de acesso HTTP direto via `login/system/.htaccess` e o arquivo extra `htmlfilter.php` foi compatibilizado com PHP atual.
6. `.htaccess` raiz passou a bloquear metadados `.git`, manifests comuns e documentação local quando servido por Apache/LiteSpeed.
7. `.env.example` foi atualizado para refletir as variáveis operacionais atuais: `APP_DB_PATH`, `APP_RATE_LIMIT_DIR` e `APP_CSP_ENFORCE`.
8. Foi criado checklist de Apache/Nginx/WAF para homologação de bloqueios equivalentes fora do PHP built-in server.

## O que não foi alterado

- Visual público.
- Layout público.
- Layout do painel principal.
- CSS visual.
- Imagens, banners e identidade visual.
- Textos públicos.
- Placeholders.
- Botões.
- Nomes de campos.
- Fluxo visual de preenchimento.
- Schema SQLite.
- Política de persistência do endpoint `login/verificar.php`.

## Estado final de maturidade

Scores estimados após esta etapa:

- Segurança geral: 5/10.
- Backend: 6/10.
- Admin: 6/10.
- Infraestrutura: 5/10.
- Operacional: 5/10.
- Compliance: 3/10.
- Frontend visual: preservado, 3/10 em maturidade técnica por legado e dependências externas.
- Prontidão homologação: 5/10.
- Prontidão produção bancária/governamental: 3/10.

## Pendências críticas remanescentes

- O frontend ainda possui campos sensíveis de cartão, CVV, CPF e senhas; a mitigação enterprise real exige tokenização/provedor certificado e governança formal.
- O schema SQLite ainda é compatível com armazenamento de dados sensíveis.
- CSP enforcement depende de homologação visual completa por causa dos scripts externos legados.
- Validação de `.htaccess` precisa ocorrer em Apache/LiteSpeed real; em Nginx é obrigatória regra equivalente.
- Ainda não há SIEM, trilha imutável, RBAC granular, backup/restore testado, pipeline CI/CD ou testes automatizados versionados.

## Checklist mínimo para homologação/produção

1. Configurar `ADMIN_USER` e `ADMIN_PASSWORD_HASH` no secret manager.
2. Configurar `APP_DB_PATH` absoluto fora do webroot.
3. Configurar `APP_DATA_KEY` com 32 bytes base64.
4. Configurar `APP_RATE_LIMIT_DIR` persistente e privado.
5. Ativar `APP_FORCE_HTTPS=true` atrás de proxy corretamente configurado.
6. Validar Apache/LiteSpeed ou Nginx contra arquivos sensíveis.
7. Validar smoke público, smoke admin, sessão, logout e CSRF.
8. Validar CSP em Report-Only antes de `APP_CSP_ENFORCE=true`.
9. Conectar logs a SIEM ou centralizador com retenção formal.
10. Validar backup/restore do SQLite fora do webroot.
