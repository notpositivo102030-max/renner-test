# Checklist operacional de produção

## Ambientes

- Produção, homologação e desenvolvimento devem ser separados.
- Nunca usar dados reais em desenvolvimento/homologação sem anonimização formal.
- Variáveis de ambiente devem ser por ambiente.
- Secrets devem ficar fora do repositório e fora do webroot.
- Homologação deve refletir headers/WAF em modo seguro antes de produção.

## Deploy e rollback

- Deploy versionado por commit/tag.
- Checklist pré-deploy:
  - PHP lint.
  - Backup recente validado.
  - Plano de rollback.
  - Janela e responsável definidos.
- Rollback deve restaurar código, configuração e, se necessário, banco.
- Mudanças de WAF/DNS/TLS devem ter rollback independente do deploy de código.

## Monitoramento

Alertas mínimos:

- Uptime HTTP/HTTPS.
- Erros 500 por minuto/acima de baseline.
- Erros 403/429 acima de baseline.
- Falhas de login admin acima de baseline.
- Acesso a arquivos sensíveis (`.env`, `.db`, `.sql`, `.bak`, `.git`).
- Mudança de arquivos críticos: `app/security.php`, `admin/*.php`, `login/*.php`, `.htaccess`, `login/.htaccess`.
- Expiração de certificado TLS.
- Alteração DNS inesperada.

## Logs centralizados

- Enviar logs de aplicação, WAF/CDN, servidor web e sistema operacional para SIEM/log centralizado.
- Não registrar dados sensíveis completos.
- Definir retenção de logs por base legal e necessidade operacional.
- Criar relatório semanal/mensal de eventos de segurança.

## Proteção anti-bot abusivo

- Começar em modo log/simulação.
- Usar score/desafio leve para alto volume anômalo.
- Evitar bloqueio cego em rotas públicas sem análise de falso positivo.
- Separar regras do painel administrativo.

## Reputação e legitimidade

Checklist institucional para reduzir risco de reputação:

- Página institucional com razão social, CNPJ, endereço e canais oficiais.
- Política de Privacidade.
- Termos de Uso.
- Página de Segurança.
- Página de Compliance.
- DPO/encarregado e canal de privacidade, quando aplicável.
- Identidade visual própria e consistente.
- Logos de parceiros apenas com autorização, contexto claro e uso discreto.
- Domínios e e-mails alinhados com a identidade oficial.

## Relatório de eventos de segurança

Periodicidade recomendada: semanal no início, mensal após estabilização.

Campos mínimos:

- Total de requisições por rota crítica.
- Top IPs/ASNs com bloqueios ou alertas.
- Tentativas de login admin e falhas.
- Eventos WAF em modo log/simulação.
- Tentativas de acesso a arquivos sensíveis.
- Incidentes e ações corretivas.
- Mudanças de configuração realizadas.
