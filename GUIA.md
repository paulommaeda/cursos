# Nexo Cursos — guia de instalação e uso

O Nexo Cursos organiza cursos, alunos, conteúdo e presenças no WordPress. As vendas usam o WooCommerce. Cada curso pode funcionar como EAD, gestão presencial ou cadastro administrativo sem aulas: tópicos, aulas e testes são opcionais.

## 1. Instalar

Requisitos: WordPress 6.4 ou superior e PHP 8.1 ou superior. Para vendas, ative WooCommerce 8.2 ou superior. A gestão e as matrículas manuais também funcionam sem WooCommerce.

1. Faça a primeira instalação em uma cópia de testes do seu site.
2. No WordPress, entre em **Plugins → Adicionar plugin → Enviar plugin**.
3. Selecione **nexo-cursos.zip**, clique em **Instalar agora** e depois em **Ativar**.
4. Abra **Nexo Cursos → Visão geral** para acessar o painel.

O ZIP já contém a pasta do plugin. Não precisa editar arquivos nem inserir credenciais no código.

## 2. Preparar a loja

No assistente e nas configurações do WooCommerce, defina moeda, endereço, impostos quando aplicáveis, e-mails e forma de pagamento. Instale o gateway desejado e cadastre suas credenciais na configuração desse gateway. O Nexo Cursos não inclui gateway, credenciais de pagamento ou serviço de emissão fiscal.

A ativação cria estas páginas, quando necessárias:

| Página | Função |
| --- | --- |
| Cursos | Catálogo público dos cursos publicados |
| Meus cursos | Cursos do aluno autenticado |
| Entrar | Login com identidade visual configurável |
| Painel dos cursos | Visão dos cursos para gerentes autenticados |
| Produtos | Loja nativa do WooCommerce |
| Carrinho | Carrinho nativo do WooCommerce |
| Finalizar compra | Checkout nativo do WooCommerce |
| Minha conta | Conta e pedidos do WooCommerce |

As quatro páginas da loja são preparadas quando o WooCommerce está ativo. Páginas já configuradas no WooCommerce são reaproveitadas, preservando seu conteúdo. Se o WooCommerce for ativado depois, abra o painel administrativo para concluir essa preparação.

Os links podem ser consultados em **Nexo Cursos → Identidade visual**. Use **Verificar e criar páginas ausentes** se alguma página do plugin tiver sido removida. Adicione os links desejados ao menu do seu tema. A aparência externa da loja e do checkout também depende do tema e das extensões instaladas.

## 3. Cadastrar o produto e o curso

1. Em **Produtos → Adicionar novo**, crie o produto que será vendido.
2. Defina nome, preço e descrição no WooCommerce. Para curso sem entrega física, marque **Virtual**. Configure estoque se quiser limitar as vendas.
3. Copie o ID do produto, exibido na lista de produtos ou no endereço da edição.
4. Em **Nexo Cursos → Cursos → Adicionar curso**, preencha título, apresentação, resumo e imagem.
5. Em **Configuração do curso**, informe o ID do produto ou da variação. Depois de salvar, o nome do produto vinculado aparecerá no formulário.
6. Escolha o formato, atribua gerentes se necessário e publique o curso.

O preço é mantido no WooCommerce. Um curso sem produto pode receber matrículas manuais. O campo **Vagas de referência** é informativo; o estoque do produto é que limita as vendas. Quando vendido em quantidade, o produto concede acesso à conta compradora; esta versão não distribui licenças automaticamente para vários alunos.

### Como a matrícula por compra funciona

O comprador precisa ter uma conta vinculada ao pedido. Nos carrinhos com curso, o checkout solicita login ou criação de conta. A matrícula é liberada quando o pedido chega a **Processando** ou **Concluído**. Pedidos pendentes ou aguardando pagamento não liberam acesso.

Cancelamentos e reembolsos revogam a origem correspondente da matrícula. Em reembolsos por item, a revogação depende da quantidade integral reembolsada daquele item; um reembolso parcial de valor não encerra automaticamente todo o acesso. Uma matrícula manual ou outra compra válida preserva o acesso.

Pedidos antigos não são importados em lote ao instalar o plugin. Para um pedido pago existente, confira a conta do aluno, os produtos vinculados e altere novamente o status para um status pago para disparar a sincronização. Faça isso primeiro na cópia de testes.

## 4. Escolher o formato

**EAD:** mostra a área de aprendizagem, com aulas, testes e progresso. Você pode começar só com a apresentação do curso e acrescentar conteúdo depois.

**Gestão / presencial:** mantém matrícula, início, local, carga horária, referência de vagas e presenças. A aprendizagem on-line fica desativada, mesmo que existam aulas ou testes cadastrados. É útil para um curso presencial ou um cadastro administrativo.

## 5. Adicionar tópicos, aulas e testes

Use os submenus de **Nexo Cursos** para cadastrar cada componente. Selecione seu curso em **Organização do conteúdo** e publique o item para exibi-lo.

- **Tópico:** organiza a estrutura do curso. É opcional.
- **Aula:** recebe texto pelo editor e, se desejado, URL de vídeo. Pode estar ligada a um tópico do mesmo curso ou ficar sem tópico.
- **Teste:** recebe perguntas de múltipla escolha, uma resposta correta por pergunta e nota mínima de aprovação.

A **Ordem no curso** usa números crescentes. Por exemplo: tópico 10, aula 20, aula 30, teste 40. O seletor de tópico mostra os tópicos compatíveis com o curso escolhido. Não é necessário criar um tópico para cadastrar uma aula ou um teste.

No teste, clique em **Adicionar pergunta**, escreva o enunciado, preencha pelo menos duas alternativas e marque a correta. Perguntas vazias ou sem configuração válida são descartadas ao salvar. As tentativas são livres e a melhor nota é preservada. Um teste vazio pode ficar preparado no cadastro, mas só poderá ser concluído depois de receber perguntas; confira sua configuração antes de publicá-lo.

O progresso considera aulas marcadas como concluídas e testes aprovados. Cursos apenas de gestão ou sem conteúdo ficam fora da média de aprendizagem no painel administrativo.

Vídeos externos dependem das permissões do provedor e não recebem DRM do plugin. Os arquivos da biblioteca de mídia do WordPress podem ter URL pública; use essa biblioteca para materiais públicos, sem arquivos confidenciais.

## 6. Dar acesso aos gerentes

1. Em **Usuários**, crie ou edite uma conta com o papel **Gerente de cursos**.
2. Edite o curso como administrador.
3. Em **Gerentes responsáveis**, selecione a conta e salve. Use Ctrl para selecionar mais de uma.
4. O gerente entra com seu próprio usuário e acessa **Nexo Cursos** ou a página **Painel dos cursos**.

O gerente acompanha apenas os cursos atribuídos e pode matricular usuários existentes, remover matrículas manuais e registrar presenças. A criação e edição de cursos, conteúdo e identidade visual ficam com o administrador. Gerentes não recebem acesso financeiro amplo ao WooCommerce por meio deste papel.

O painel apresenta cursos, matrículas ativas, progresso médio e acompanhamento dos alunos. Os dados vêm dos cadastros reais do site. A lista de alunos tem filtros por curso, nome ou e-mail e paginação.

## 7. Matricular manualmente e registrar presença

Em **Nexo Cursos → Visão geral**, use **Adicionar matrícula manual**. Informe o e-mail ou ID de um usuário já cadastrado e selecione o curso. Cadastre a conta em **Usuários** antes, se ela ainda não existir.

A tabela identifica matrículas **Manual**, **WooCommerce** ou **Manual + compra**. **Remover manual** remove somente a matrícula adicionada pelo painel. Matrículas de compras seguem o pedido correspondente no WooCommerce.

Para presença, escolha a data no filtro, encontre o aluno e marque **Presente**, **Ausente** ou **Justificada**, depois clique em **Salvar**. O registro é por aluno, curso e data. A coluna de progresso mostra **Não se aplica** nos cursos sem aprendizagem.

## 8. Personalizar marca e login

Em **Nexo Cursos → Identidade visual**, configure:

- Nome da marca.
- Cor principal e cor de destaque.
- Logo pela biblioteca de mídia.
- Título e mensagem de boas-vindas da tela de login.

Salve e abra as páginas de entrada e catálogo para conferir. A identidade é aplicada às telas do plugin e à tela padrão de login do WordPress. Os componentes próprios do tema, dos gateways e de outras extensões mantêm suas configurações.

## 9. Usar páginas próprias

Os shortcodes podem ser colocados em páginas do WordPress:

| Shortcode | Conteúdo |
| --- | --- |
| `[nexo_catalog]` | Catálogo público |
| `[nexo_my_courses]` | Área de cursos do usuário |
| `[nexo_login]` | Login personalizado |
| `[nexo_manager_dashboard]` | Painel do gerente autenticado |
| `[nexo_course id="123"]` | Curso de ID 123, respeitando as permissões de acesso |

Quando cria novas páginas de carrinho e checkout, o plugin usa os shortcodes oficiais do WooCommerce. Páginas existentes, inclusive páginas com os blocos do WooCommerce, são preservadas. O fluxo de cobrança, cálculo de valores e pedidos permanece no WooCommerce.

## 10. Antes de abrir as inscrições

Faça uma compra de teste com o gateway no modo de testes, confirme a conta compradora e a matrícula, acesse uma aula, envie um teste e confira cancelamento/reembolso. Entre também com uma conta de gerente para conferir os cursos atribuídos. Esses passos verificam a combinação do seu tema, gateway e extensões.

Esta versão cobre gestão educacional básica, EAD opcional, matrículas, testes, presenças e integração de vendas. Não inclui contabilidade, emissão fiscal, certificados, turmas avançadas, videoconferência ou pagamento de comissões a instrutores.

Desativar ou excluir o plugin preserva os dados educacionais por padrão. A limpeza definitiva é uma opção técnica explícita documentada no readme do plugin. Produtos, pedidos e páginas não são removidos automaticamente.

### Documentação de apoio

- [Tipos de conteúdo do WordPress](https://developer.wordpress.org/reference/functions/register_post_type/)
- [Formulário de login do WordPress](https://developer.wordpress.org/reference/functions/wp_login_form/)
- [API de pedidos do WooCommerce](https://developer.woocommerce.com/docs/features/orders/wc-get-orders/)
- [Compatibilidade com o armazenamento de pedidos HPOS](https://developer.woocommerce.com/docs/features/orders/high-performance-order-storage/recipe-book/)

O pacote precisa ser validado no ambiente específico do seu site antes de uso comercial. A entrega não representa validação em produção de temas, gateways ou extensões de terceiros.

## 11. Atualizações pelo WordPress

A partir da versão 1.1.0, o plugin consulta as versões estáveis publicadas no [GitHub](https://github.com/paulommaeda/cursos/releases). Em **Plugins → Plugins instalados**, use **Verificar atualizações** na linha do Nexo Cursos. Quando houver uma versão mais recente, o WordPress oferecerá a atualização na própria lista e em **Painel → Atualizações**.

Quem instalou a versão 1.0.0 deve enviar e substituir o ZIP pela versão 1.1.0 uma vez. Os cursos e as matrículas são preservados. Depois, as próximas versões compatíveis poderão ser atualizadas pelo painel. É possível optar pelas atualizações automáticas na lista de plugins; o Nexo não força essa opção.

O servidor precisa conseguir acessar api.github.com e github.com por HTTPS. O repositório é público e não exige token. Apenas Releases estáveis com o anexo **nexo-cursos.zip** são distribuídas; commits na branch principal não atualizam automaticamente os sites.
