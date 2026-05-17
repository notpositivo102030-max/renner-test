# Infraestrutura enterprise — Fase 4

## Objetivo

Preparar a operação da aplicação para um padrão enterprise de borda, DNS, e-mail, monitoramento, backup, recuperação e reputação **sem alterar fluxo funcional**, layout, painel, campos, rotas, endpoints ou lógica de negócio.

## Princípios obrigatórios

- Começar com regras em **modo log/simulação/monitoramento**.
- Não ativar bloqueios agressivos sem observar falsos positivos.
- Separar regras do público e do painel administrativo.
- Manter rollback documentado para toda regra de borda, DNS, TLS e deploy.
- Não usar dados reais em homologação/desenvolvimento.
- Manter secrets fora do repositório.

## Ordem recomendada de implantação

1. Validar inventário de domínios, subdomínios, certificados e provedores.
2. Aplicar monitoramento e logs centralizados antes de bloqueios.
3. Configurar WAF/Cloudflare em modo log/simulação.
4. Ativar rate limits leves apenas em endpoints sensíveis após observabilidade.
5. Validar TLS/HSTS e conteúdo misto em homologação.
6. Configurar DNSSEC/CAA e política de e-mail em monitoramento.
7. Formalizar backups criptografados e teste de restauração.
8. Separar ambientes com variáveis e secrets independentes.
9. Publicar páginas institucionais/compliance com validação jurídica.

## Entregáveis desta pasta

- [`cloudflare-waf-checklist.md`](cloudflare-waf-checklist.md): checklist de WAF/borda com regras iniciais em log/simulação.
- [`dns-email-checklist.md`](dns-email-checklist.md): DNSSEC, CAA, TLS e e-mail SPF/DKIM/DMARC.
- [`backup-restore-checklist.md`](backup-restore-checklist.md): backup criptografado, retenção, restauração e desastre.
- [`production-operations-checklist.md`](production-operations-checklist.md): ambientes, deploy, monitoramento, alertas e reputação.

## Compatibilidade confirmada

Esta Fase 4 adiciona documentação operacional e checklists. Não altera código de coleta, processamento, armazenamento, layout, painel, rotas, endpoints ou nomes de campos.
