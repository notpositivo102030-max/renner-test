# Checklist final de produção enterprise

## Pré-produção

- [ ] Backup criptografado recente e restauração testada.
- [ ] Plano de rollback validado.
- [ ] Variáveis de ambiente por ambiente configuradas.
- [ ] Secrets fora do repositório.
- [ ] `APP_DATA_KEY` provisionada em secret manager quando criptografia for ativada.
- [ ] Certificado TLS válido e monitorado.
- [ ] Headers de segurança revisados.
- [ ] CSP ainda em Report-Only até validação completa.

## Segurança operacional

- [ ] WAF em modo log/simulação revisado por janela mínima.
- [ ] Rate limits revisados contra falsos positivos.
- [ ] Alertas para 500/403/429 ativos.
- [ ] Alertas de login admin suspeito ativos.
- [ ] Monitoramento de arquivos críticos ativo.
- [ ] Logs centralizados sem dados sensíveis completos.
- [ ] Procedimento de incidente documentado.

## Banco e dados

- [ ] SQLite protegido contra acesso HTTP direto.
- [ ] Permissões do SQLite revisadas.
- [ ] Plano para mover banco fora do webroot aprovado.
- [ ] Migração/criptografia de dados legados não executada sem backup e autorização.
- [ ] Retenção e descarte aprovados.

## Go-live

- [ ] Janela de publicação aprovada.
- [ ] Responsáveis de plantão definidos.
- [ ] Checklist de smoke test definido.
- [ ] Métricas e dashboards acompanhados durante publicação.
- [ ] Rollback testado ou documentado.
