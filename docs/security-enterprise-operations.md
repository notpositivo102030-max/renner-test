# Operação de segurança enterprise/bancária

## Controles implementados no repositório

- Bootstrap de headers, sessão segura, CSRF, auditoria, criptografia de campo e acesso SQLite seguro em `app/security.php`.
- MFA TOTP obrigatório no admin por `ADMIN_TOTP_SECRET`.
- Rate limit por IP e sessão para endpoints que usam os helpers centrais.
- Mascaramento de dados sensíveis em telas administrativas.
- Segredos documentados para variáveis de ambiente, sem valores reais versionados.
- Templates de NGINX, PHP-FPM, PHP hardening e WAF em `deploy/security/`.
- Workflow DevSecOps de lint/auditoria/SAST/secrets em `.github/workflows/security.yml`.

## Controles obrigatórios fora do repositório

### Borda, DNS, WAF e DDoS

- Usar WAF enterprise com OWASP CRS/managed rules, bot management e rate limit L7.
- Ativar anti-DDoS always-on e proteger IP de origem.
- DNS com DNSSEC, CAA e alteração controlada por MFA.
- Bloquear acesso direto ao origin fora dos ranges do WAF/proxy.

### Linux e servidor

- Sistema com atualização automática de segurança, EDR, auditd, NTP, firewall default-deny e SSH somente por chave/MFA/VPN.
- NGINX/PHP-FPM dedicados com usuário sem shell, `open_basedir`, logs separados e permissões mínimas.
- Banco SQLite fora do webroot via `APP_DB_PATH`, com permissão `0600` e backup criptografado.

### TLS

- TLS 1.2/1.3 apenas, certificados automatizados, HSTS preload após validação de todos os subdomínios.
- Desabilitar cifras fracas, TLS tickets persistentes e protocolos legados.

### SIEM/SOC

- Enviar logs de aplicação, WAF, NGINX, PHP-FPM, sistema, EDR e backup para SIEM.
- Alertas mínimos: falha de login admin, MFA inválido repetido, CSRF falho, rate limit, acesso a arquivos proibidos, erro 5xx, alteração de segredos, backup falho.
- Retenção conforme LGPD/PCI/política: logs quentes 90 dias, arquivados 1 ano ou conforme jurídico.

### Backup e rollback

- Backup criptografado diário com teste de restore mensal.
- Chaves de backup em KMS/cofre com rotação e segregação.
- Rollback por release imutável: código, config, variáveis, banco e regras WAF versionadas.

### DevSecOps obrigatório

- Bloquear merge se falhar: lint PHP, SAST, secret scan, dependency audit, IaC/config scan e testes de smoke.
- Pentest anual e após mudanças críticas.
- Threat modeling e revisão ASVS a cada release relevante.

## Resposta a incidentes

1. Triagem: classificar severidade, preservar evidências, abrir war room.
2. Contenção: bloquear IP/ASN/assinatura no WAF, desabilitar credenciais afetadas, rotacionar segredos.
3. Erradicação: aplicar correção, revisar logs, invalidar sessões, verificar integridade do servidor.
4. Recuperação: restaurar serviço por release limpo, monitorar indicadores e comunicar stakeholders.
5. Pós-incidente: RCA, lições aprendidas, atualização de controles e evidências para auditoria.
