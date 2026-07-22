# Importação de Bíblias

O pacote público do Bíblia Digital não distribui arquivos SQL com texto bíblico e não inclui traduções bíblicas protegidas.

Para usar o plugin, o administrador deve importar um arquivo ZIP contendo exatamente:

- `books.csv`
- `verses.csv`

Antes de importar, confirme se a versão bíblica escolhida é de domínio público, possui licença compatível ou conta com autorização do titular. A responsabilidade por obter, validar e licenciar o pacote de dados bíblicos importado é do usuário final/administrador do site.

Algumas versões da Bíblia exigem autorização específica para uso, cópia, publicação ou distribuição. O plugin apenas valida a estrutura do ZIP/CSV e armazena o conteúdo localmente no WordPress.

## Requisitos

- O ZIP deve conter `books.csv` e `verses.csv` na raiz.
- Os arquivos devem estar em UTF-8.
- `books.csv` deve conter os 66 livros.
- `verses.csv` deve conter os versículos com referência de livro, capítulo e versículo.
- Arquivos extras, caminhos absolutos e path traversal são bloqueados.
