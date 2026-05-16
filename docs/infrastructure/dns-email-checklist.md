# Checklist DNS, TLS, HTTPS e e-mail

## TLS / HTTPS

- Usar TLS 1.2 e TLS 1.3; desabilitar protocolos antigos quando o provedor permitir.
- Validar cadeia completa do certificado, SANs e expiração.
- Ativar redirecionamento HTTP → HTTPS na borda/proxy com rollback.
- Ativar HSTS gradualmente:
  1. `max-age` curto em homologação.
  2. Produção com `max-age=15552000` após validação.
  3. `includeSubDomains` somente se todos os subdomínios suportarem HTTPS.
  4. `preload` apenas após validação formal.
- Monitorar conteúdo misto antes de CSP bloqueante.

## DNSSEC readiness

- Confirmar se registrador e DNS autoritativo suportam DNSSEC.
- Habilitar DNSSEC primeiro em domínio de homologação quando possível.
- Registrar DS no registrador após validar cadeia.
- Monitorar falhas de resolução após ativação.
- Documentar procedimento de rollback/desativação.

## CAA records

Exemplo de política inicial, ajustar ao CA real:

```dns
example.com. 3600 IN CAA 0 issue "letsencrypt.org"
example.com. 3600 IN CAA 0 iodef "mailto:security@example.com"
```

Checklist:

- Listar CAs autorizadas pela organização.
- Adicionar `iodef` para alertas.
- Validar emissão/renovação após CAA.

## SPF

Exemplo monitorável, ajustar ao provedor de e-mail real:

```dns
example.com. 3600 IN TXT "v=spf1 include:provedor-email.example -all"
```

Regras:

- Incluir apenas provedores autorizados.
- Evitar múltiplos registros SPF no mesmo domínio.
- Validar limite de consultas DNS.

## DKIM

- Gerar chaves por provedor/serviço de e-mail.
- Usar seletor por serviço, por exemplo `selector1._domainkey`.
- Rotacionar chaves periodicamente.
- Não reutilizar chave DKIM entre ambientes/provedores.

## DMARC gradual

Começar em monitoramento:

```dns
_dmarc.example.com. 3600 IN TXT "v=DMARC1; p=none; rua=mailto:dmarc@example.com; ruf=mailto:dmarc-forensic@example.com; fo=1; adkim=s; aspf=s"
```

Escalonamento:

1. `p=none` por 2–4 semanas para coletar relatórios.
2. Corrigir provedores desalinhados.
3. Migrar para `p=quarantine; pct=25`, depois `pct=50`, `pct=100`.
4. Migrar para `p=reject` somente após estabilidade.

## Reputação de domínio

- Evitar envio de e-mail por domínios sem reputação/aquecimento.
- Separar subdomínios para transacional, marketing e segurança se aplicável.
- Monitorar bounce, complaint rate e blocklists.
- Garantir alinhamento SPF/DKIM/DMARC no domínio visível ao usuário.
