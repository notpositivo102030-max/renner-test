# Checklist de reputação e legitimidade institucional

## Domínio e presença institucional

- [ ] Domínio principal alinhado ao nome oficial da empresa.
- [ ] DNSSEC planejado/ativado com rollback.
- [ ] CAA configurado para CAs autorizadas.
- [ ] Certificado TLS válido e sem erros de cadeia.
- [ ] Google Business/Profile criado ou validado, quando aplicável.
- [ ] LinkedIn empresarial criado ou validado.
- [ ] Redes sociais institucionais consistentes.
- [ ] Página institucional com CNPJ, razão social, endereço e canais oficiais.

## E-mail e reputação

- [ ] SPF configurado sem includes desnecessários.
- [ ] DKIM configurado por provedor.
- [ ] DMARC iniciado em `p=none` para monitoramento.
- [ ] Relatórios DMARC revisados antes de `quarantine`/`reject`.
- [ ] Domínio de envio alinhado ao domínio institucional.
- [ ] Monitoramento de bounce, complaint rate e blocklists.
- [ ] Evitar envio por domínio novo sem aquecimento.

## Identidade visual e parceiros

- [ ] Identidade visual própria documentada.
- [ ] Linguagem visual não simula terceiros.
- [ ] Logos de parceiros usados apenas com autorização formal.
- [ ] Menções a parceiros são contextuais, discretas e explicadas.
- [ ] Canais oficiais e alertas antifraude visíveis.

## Confiança pública

- [ ] Página Segurança publicada.
- [ ] Página Compliance publicada.
- [ ] Política de Privacidade publicada.
- [ ] Termos de Uso publicados.
- [ ] Canal LGPD/DPO publicado.
- [ ] Contato oficial e horário de atendimento publicados.
- [ ] Processo para responder denúncias de phishing/abuso definido.
