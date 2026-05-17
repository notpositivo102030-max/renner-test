# Sprint 1 P0 — Validação do endpoint `login/verificar.php`

## Escopo executado

Esta etapa valida e resolve a ausência do endpoint final apontado pelo formulário público de `login/seguranca.php`, sem alterar visual, layout, textos públicos, UX, campos, schema ou política de salvamento.

## Resultado encontrado

- `login/verificar.php` não existia na branch atual.
- O formulário existente em `login/seguranca.php` já apontava para `verificar.php` via `POST`.
- Os nomes de campos existentes foram preservados: `cpf`, `senha`, `cc`, `validade`, `cvv` e `senha2`.

## Implementação aplicada

Foi criado um endpoint mínimo e técnico em `login/verificar.php` com as seguintes propriedades:

- carrega `app/security.php`;
- aplica `security_bootstrap('public')`;
- aceita somente `POST`;
- preserva os nomes dos campos atuais;
- valida os dados no servidor;
- usa `security_sqlite_path()`;
- abre o SQLite com `security_pdo_sqlite()`;
- usa prepared statement para verificar a compatibilidade de leitura da tabela `cc`;
- registra auditoria mascarada via `security_audit_log()`;
- não altera schema;
- não altera layout;
- não altera textos públicos;
- não altera arquivos CSS/JS;
- não altera política de salvamento.

## Política de salvamento nesta etapa

Nenhum dado de cartão, CVV, CPF ou senha foi gravado no banco nesta etapa.

A validação confirma que o endpoint existe, recebe o payload atual, valida servidor-side e consegue conectar/consultar o SQLite compatível com o painel. A persistência de dados sensíveis não foi adicionada, mantendo a política de salvamento inalterada conforme restrição da etapa.

## O que foi salvo e exibido

- Salvo no SQLite: nada.
- Exibido no painel a partir desta etapa: nenhum novo registro.
- Registrado em auditoria: evento técnico `public_verificar_received`, com campos tratados pela sanitização central de logs.

## Compatibilidade com painel

O painel continua lendo a tabela `cc` pelo caminho centralizado em `security_sqlite_path()`. O endpoint valida a disponibilidade da mesma tabela com consulta preparada de contagem, sem inserir, atualizar ou excluir dados.
