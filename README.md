# Nexo Cursos e Shalom Formação — 1.5.0

Plugin WordPress e tema para cursos livres da Igreja Shalom: discipular, aperfeiçoar e enviar, conforme Efésios 4:12. Gestão EAD ou presencial, com tópicos, aulas e testes opcionais e vendas pelo WooCommerce.

A operação acontece na **Área de gestão do próprio site**, com navegação independente do wp-admin: cursos, conteúdos e testes, alunos, matrículas, aulas, chamada, produtos, vendas, pedidos, identidade visual, home e equipe. O painel WordPress fica reservado ao administrador.

## Instalar e usar

1. Baixe **nexo-cursos.zip** e **shalom-formacao.zip** em [Releases](https://github.com/paulommaeda/cursos/releases/latest).
2. O administrador instala o plugin na área de plugins e o tema na área de temas. Para vendas, configura o WooCommerce, os meios de pagamento, a moeda e os e-mails.
3. Entre pela página **Entrar** do site e abra **Área de gestão**. A equipe segue direto para a gestão após o login.
4. Em **Equipe e acessos**, o administrador cria ou localiza pessoas. Gerentes recebem os cursos atribuídos; a coordenação acompanha todos os cursos e as vendas dos cursos. O perfil Gerente da loja mantém a gestão dos pedidos e produtos da loja.

Requisitos: WordPress 6.4+, PHP 8.1+ e WooCommerce 8.2+ para vendas. Cursos gratuitos e gestão manual funcionam sem WooCommerce. Leia o [guia completo](GUIA.md).

## Cursos e aulas presenciais

Cursos gratuitos têm cadastro rápido na própria página: nome, e-mail e senha criam a conta e confirmam automaticamente a matrícula no curso de origem. Quem já tem conta entra ali mesmo; quem já está conectado confirma em um toque. Cursos pagos criam um produto virtual, sincronizando título, descrição, resumo, capa e preços. Vínculos manuais anteriores são preservados. Progresso, testes e dados de aprendizagem continuam protegidos.

Defina início, fim, horário, duração e frequência para gerar aulas presenciais: encontro único, diário, dias úteis, semanal, quinzenal, mensal ou intervalo personalizado. A geração não duplica datas nem apaga histórico. Registre a chamada por encontro e acompanhe frequência, cobertura e pendências por aluno e por aula. Campos em branco não são faltas automáticas.

## Operação comercial no site

A equipe autorizada consulta produtos, preços e estoque; cria pedidos pendentes; acompanha pagamentos; adiciona observações; vincula a conta compradora; consulta relatórios e registra reembolsos. As operações usam o WooCommerce, com suporte ao armazenamento HPOS.

As permissões são conferidas no servidor. Um gerente vê apenas itens e valores ligados aos seus cursos. Pedidos que misturam itens fora de sua gestão exigem alguém com acesso ao pedido completo para alterar situação, conta ou reembolso. O registro manual de reembolso não transfere dinheiro; a devolução pelo meio de pagamento depende do serviço usado na compra.

## Leitura e personalização

A interface parte de uma coluna no celular e amplia a organização em telas maiores. Textos com base de 17 px, campos de 16 px, controles de toque e tabelas que se tornam fichas identificadas nas telas pequenas.

Nome, logos, cores, fontes, tamanho do texto, capas, imagens, botões, textos da home, rótulos da navegação, ordem e visibilidade das seções são editáveis. A identidade Shalom é um ponto de partida e pode ser adaptada para outras organizações. Os exemplos das prévias não são importados para o site.

## Atualizações pelo WordPress

O plugin consulta Releases estáveis deste repositório e informa versões novas em Plugins e Painel → Atualizações. Também reconhece o ZIP do tema separadamente. Não exige token. O servidor precisa acessar o GitHub por HTTPS.

Instalações 1.0.0 precisam receber o ZIP atual manualmente uma vez, pois não possuíam atualizador. As atualizações automáticas são uma escolha do administrador. Rascunhos e pré-lançamentos não são distribuídos.

## Publicar uma versão

Mantenha o mesmo número X.Y.Z no cabeçalho Version e na constante NEXO_VERSION de `nexo-cursos.php`, no Stable tag de `readme.txt` e no Version de `themes/shalom-formacao/style.css`. Atualize guia, readme, changelog e o valor sugerido no workflow de publicação.

O fluxo **Verificar plugin** valida PHP, JavaScript e os pacotes. Um envio para main que altere `nexo-cursos.php` aciona **Publicar versao**: cria a tag correspondente no commit e publica os dois ZIPs. Também é possível usar Actions → Run workflow ou enviar uma tag estável vX.Y.Z.

O publicador preserva tags e Releases já existentes. Para corrigir uma versão publicada, use um novo número. A publicação usa o token temporário do GitHub Actions e não requer chave pessoal.

O plugin fica na raiz do repositório e o tema em `themes/shalom-formacao`. A montagem inclui somente arquivos de produção, mantendo as pastas `nexo-cursos/` e `shalom-formacao/` dentro dos ZIPs. Testes locais, credenciais e dados demonstrativos ficam fora dos pacotes.

```sh
python3 tools/build-release.py --tag v1.5.0
```

## Dados e alcance

Desativar ou excluir o plugin preserva os registros educacionais por padrão. A limpeza definitiva exige a opção explícita NEXO_DELETE_DATA; consulte o readme. Páginas, produtos e registros financeiros do WooCommerce são preservados.

Configurações técnicas de extensões, pagamentos e WordPress permanecem sob responsabilidade do administrador. A entrega não inclui gateway próprio, emissão fiscal, contabilidade, certificados, comissões ou distribuição de vagas a várias contas por compra.
