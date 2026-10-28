# Nexo Cursos

Plugin WordPress para administrar cursos EAD, presenciais ou apenas seus cadastros e matrículas. As vendas usam o WooCommerce. Tópicos, aulas e testes são opcionais em cada curso.

Inclui dashboard dos gerentes por curso, matrículas manuais e por compra, acompanhamento de progresso, testes de múltipla escolha, presenças, catálogo e área do aluno. Nome, cores, logo e login podem receber a identidade da sua organização.

## Instalar

Requisitos: WordPress 6.4+, PHP 8.1+ e WooCommerce 8.2+ para vendas. A gestão com matrícula manual funciona sem WooCommerce.

1. Abra [Releases](https://github.com/paulommaeda/cursos/releases/latest) e baixe o anexo **nexo-cursos.zip**.
2. No WordPress, entre em **Plugins → Adicionar plugin → Enviar plugin** e envie esse ZIP.
3. Ative o plugin e abra **Nexo Cursos → Visão geral**.
4. Configure o WooCommerce e seu meio de pagamento. Cadastre o produto e vincule o ID dele no curso.

Use o anexo `nexo-cursos.zip` da release. Os arquivos automáticos “Source code” do GitHub contêm o repositório e não são o pacote de distribuição preparado pelo projeto.

O [guia de instalação e uso](GUIA.md) explica cursos, componentes, gerentes, páginas, identidade visual e matrículas. Faça a primeira instalação e uma compra de teste em uma cópia do seu site.

## Atualizar pelo painel do WordPress

A partir da versão **1.1.0**, o plugin consulta as releases estáveis deste repositório público e anuncia novas versões em **Plugins** e **Painel → Atualizações**. Não é necessário informar token do GitHub. O site precisa conseguir acessar o GitHub pela internet.

Quem instalou o ZIP **1.0.0** precisa enviar manualmente o ZIP **1.1.0** uma vez, confirmando a substituição da versão anterior. Essa primeira versão não continha o mecanismo de atualização. As próximas versões poderão ser instaladas pelo painel.

A atualização automática é uma escolha do administrador em **Plugins → Ativar atualizações automáticas**. O plugin não força essa opção nem instala atualizações por conta própria. Uma release precisa estar publicada, ser estável e conter o anexo `nexo-cursos.zip`; alterações apenas na branch `main`, rascunhos e pré-lançamentos não são distribuídos ao WordPress.

## Preparar uma nova versão

O código do plugin fica na raiz deste repositório. Para publicar uma versão, atualize estes três valores para o mesmo número `X.Y.Z`:

- Cabeçalho `Version` em `nexo-cursos.php`.
- Constante `NEXO_VERSION` no mesmo arquivo.
- Campo `Stable tag` em `readme.txt`.

Atualize o changelog e valide o comportamento no ambiente de testes. O fluxo **Verificar plugin** confere sintaxe PHP/JavaScript, versões e a estrutura do ZIP. Essas verificações não substituem testes no WordPress com WooCommerce, tema e meios de pagamento do site.

Ao enviar para `main` uma alteração em `nexo-cursos.php`, o fluxo **Publicar versao** lê a versão do cabeçalho, valida os três números, cria a tag correspondente no commit e publica o ZIP. Assim, salvar os três valores da nova versão e enviar esse commit para `main` inicia a distribuição.

Também existem duas alternativas de publicação:

- **Pelo GitHub:** abra **Actions → Publicar versao → Run workflow**, selecione a branch que contém a versão e informe a tag `vX.Y.Z`. O fluxo cria a tag no commit selecionado quando ela ainda não existe, monta o ZIP e publica a release.
- **Por uma tag:** envie uma tag `vX.Y.Z` que aponte para o commit da versão. O fluxo de publicação roda automaticamente.

A tag deve coincidir com os três valores da versão. Nos fluxos por tag ou execução manual, uma tag existente que aponta para outro commit é rejeitada. Uma execução automática da `main` que encontre uma release daquele número termina preservando a release, seu anexo e sua tag, mesmo que o código da branch tenha avançado; ela não substitui uma versão publicada. Rascunhos existentes também são preservados. Para corrigir uma versão já publicada, atualize os três valores e publique um novo número.

O fluxo usa apenas o token temporário do próprio GitHub Actions, com permissão de escrita no conteúdo do repositório para a etapa de publicação. Não é necessário cadastrar uma chave pessoal. Se o repositório bloquear Actions ou suas permissões de escrita, a publicação precisa ser habilitada nas configurações do repositório.

O pacote é criado por `tools/build-release.py` com uma lista permitida de arquivos de produção. A pasta interna permanece `nexo-cursos/`, preservando o identificador do plugin em instalações e atualizações. Ferramentas, workflows, credenciais e arquivos locais de teste ficam fora do ZIP.

Para montar o mesmo pacote localmente, execute a partir da raiz do repositório:

```sh
python3 tools/build-release.py --tag v1.1.0
```

O arquivo será criado em `dist/nexo-cursos.zip`.

## Alcance

A entrega inclui gestão educacional básica e vendas individuais pela conta compradora. Não inclui contabilidade, emissão fiscal, certificados, turmas avançadas, comissões ou gateway de pagamento próprio. Vídeos externos dependem do provedor e não têm DRM. Desativar ou excluir o plugin preserva registros educacionais por padrão; consulte `readme.txt` antes de optar pela limpeza definitiva.

## Referências

- [Integração de atualizações externas do WordPress](https://developer.wordpress.org/reference/hooks/update_plugins_hostname/)
- [GitHub Releases](https://docs.github.com/en/rest/releases/releases)
- [Comando de publicação do GitHub CLI](https://cli.github.com/manual/gh_release_create)
- [WooCommerce e HPOS](https://developer.woocommerce.com/docs/features/orders/high-performance-order-storage/recipe-book/)

Licença [GPL-2.0-or-later](LICENSE.txt).

