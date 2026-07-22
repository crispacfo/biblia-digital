# Segurança

Este documento resume as decisões de segurança aplicadas ao Bíblia Digital.

## Administracao

- Ações administrativas usam `manage_options`.
- Formulários administrativos usam nonce.
- A importação nativa por SQL foi removida do pacote público.
- O uninstall preserva dados por padrão e só remove tabelas/opções quando `bdwp70_delete_data_on_uninstall` estiver ativado.

## Uploads

- Upload de Bíblia aceita apenas ZIP contendo `books.csv` e `verses.csv`.
- O ZIP é validado antes e depois da extração.
- Caminhos absolutos, path traversal, arquivos extras e links simbólicos são bloqueados.
- Uploads de tradução aceitam apenas `.pot`, `.po` e `.mo`.
- Arquivos `.po` e `.pot` são inspecionados contra PHP/script.
- Arquivos `.mo` passam por validação básica de assinatura.

## Banco de dados

- Entradas variáveis em SQL devem usar `$wpdb->prepare()`.
- Tabelas dinâmicas devem vir apenas de helpers internos baseados no prefixo do WordPress.
- Dados importados de CSV são sanitizados antes de serem gravados.

## Funções proibidas

O plugin não deve usar `eval`, `base64_decode`, `shell_exec`, `exec`, `system`, `passthru`, `proc_open`, `popen` ou `curl_exec`.
