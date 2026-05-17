# Checklist backup, restauração e desastre

## Política de backup criptografado

- Backups devem ser gerados fora do webroot.
- Backups devem ser criptografados antes de sair do servidor.
- Chaves de backup devem ficar em secret manager/KMS, separadas da aplicação.
- O backup deve incluir banco SQLite, arquivos de configuração necessários e metadados de versão.
- Nunca armazenar `.db`, `.sqlite`, `.sql`, `.bak`, `.backup` ou `.log` em diretórios públicos.

## Frequência sugerida

| Tipo | Frequência | Retenção inicial | Observação |
| --- | --- | --- | --- |
| Snapshot operacional | Diário | 7–14 dias | Criptografado e fora do webroot. |
| Backup semanal | Semanal | 8–12 semanas | Testar restauração amostral. |
| Backup mensal | Mensal | Conforme política/LGPD | Depende de base legal e necessidade operacional. |

## Checklist de geração

1. Colocar aplicação em modo consistente, quando necessário.
2. Copiar SQLite com método seguro (`sqlite3 .backup` ou snapshot consistente).
3. Gerar hash SHA-256 do backup bruto.
4. Criptografar backup.
5. Enviar para armazenamento externo restrito.
6. Registrar timestamp, versão do commit/deploy e hash.
7. Remover cópias temporárias não criptografadas.

## Checklist de restauração

1. Selecionar backup e validar autorização.
2. Baixar para ambiente isolado/homologação.
3. Validar assinatura/hash.
4. Descriptografar com chave autorizada.
5. Restaurar banco em caminho fora do webroot quando possível.
6. Validar schema, permissões e contagem de registros.
7. Validar login admin e leitura do painel.
8. Validar logs sem dados sensíveis completos.
9. Documentar resultado e tempo de restauração.

## Plano de desastre

- Definir RPO/RTO aceitos pelo negócio.
- Manter contato de responsáveis técnicos, infraestrutura, segurança e jurídico.
- Ter procedimento de rotação de credenciais após incidente.
- Ter plano de revogação/rotação de `APP_DATA_KEY` e chaves de backup.
- Testar restauração completa pelo menos trimestralmente.

## Controle de acesso

- Acesso a backups somente por operadores autorizados.
- Registro de acesso a backups em log imutável/centralizado.
- Separação de funções: quem opera produção não deve ser o único aprovador de restauração.
- Revisão periódica de permissões.
