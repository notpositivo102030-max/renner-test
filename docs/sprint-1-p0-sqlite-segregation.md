# Sprint 1 P0 — SQLite fora do webroot com `APP_DB_PATH`

## Escopo executado

Esta etapa permite configurar o banco SQLite fora da pasta pública servida pelo site usando a variável de ambiente `APP_DB_PATH`.

Não foram alterados:

- visual;
- layout;
- textos públicos;
- fluxo público;
- endpoints públicos;
- schema do SQLite;
- política de salvamento.

## Arquivos de aplicação alterados

- `app/security.php`
  - Centraliza a resolução do caminho do SQLite em `security_sqlite_path()`.
  - Usa `APP_DB_PATH` quando a variável estiver configurada e não vazia.
  - Mantém fallback compatível para `login/db.db` somente quando `APP_DB_PATH` não estiver configurada ou estiver vazia.

- `admin/index.php`
  - Passa a abrir o SQLite pelo caminho centralizado em `security_sqlite_path()`.

- `admin/processar/remover.php`
  - Passa a abrir o SQLite pelo caminho centralizado em `security_sqlite_path()`.

## Configuração recomendada em produção

1. Criar um diretório fora do webroot, por exemplo:

```bash
sudo mkdir -p /var/lib/renner
```

2. Copiar o banco atual para esse diretório durante uma janela controlada:

```bash
sudo cp login/db.db /var/lib/renner/db.db
```

3. Ajustar dono e permissões para o usuário do PHP/webserver:

```bash
sudo chown <usuario-php>:<grupo-php> /var/lib/renner/db.db
sudo chmod 600 /var/lib/renner/db.db
```

4. Configurar a variável de ambiente no servidor de aplicação:

```bash
APP_DB_PATH=/var/lib/renner/db.db
```

5. Reiniciar/recarregar o serviço PHP/webserver para garantir que a variável seja carregada.

## Comportamento de compatibilidade local

Se `APP_DB_PATH` não estiver configurada ou estiver vazia, a aplicação continua usando o caminho legado:

```text
login/db.db
```

Esse fallback existe para preservar compatibilidade local. Em produção, o valor recomendado é sempre um caminho absoluto fora do webroot.

## Observações operacionais

- Se `APP_DB_PATH` estiver configurada com um caminho inválido, inexistente ou sem permissão para o usuário do PHP, a conexão SQLite falhará. A aplicação não volta silenciosamente para `login/db.db` quando `APP_DB_PATH` está definida.
- A migração física do arquivo em produção deve ser feita com cuidado operacional para evitar perda de escrita durante a cópia.
- Esta etapa não altera tabelas, colunas, índices, dados ou regras de gravação.
