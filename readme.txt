=== Nexo Cursos ===
Contributors: nexo-cursos
Tags: courses, lms, woocommerce, education, erp
Requires at least: 6.4
Requires PHP: 8.1
Stable tag: 1.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Gestão de cursos EAD e presenciais com identidade visual configurável e vendas pelo WooCommerce.

== Description ==
* Cursos podem conter tópicos, aulas e testes, todos opcionais.
* Modo EAD com conteúdo protegido, progresso e testes de múltipla escolha.
* Modo administrativo para cursos presenciais: matrículas, local, data, carga horária e presença.
* Dashboard de gerentes com acesso apenas aos cursos atribuídos.
* Matrícula automática nos status de pedido processing/completed; revogação por origem em cancelamentos e reembolsos.
* Catálogo, loja de produtos, carrinho, checkout, área do aluno e login.
* Logo, nome, cores e textos de login configuráveis.

WooCommerce é necessário para vendas. Gestão e matrículas manuais funcionam sem WooCommerce.

== Installation ==
1. Em Plugins > Adicionar plugin > Enviar plugin, selecione nexo-cursos.zip e ative.
2. Instale/ative WooCommerce para vendas e configure moeda, pagamento e emails.
3. Abra Nexo Cursos e cadastre um curso. Selecione inscrição paga e informe o preço para criar o produto automaticamente. Nos gratuitos, a matrícula é feita após cadastro/login.
4. Atribua usuários com papel Gerente de cursos para conceder acesso ao dashboard.
5. Configure marca, logo e cores nas configurações do Nexo Cursos.

== Shortcodes ==
[nexo_catalog] — catálogo público de cursos.
[nexo_my_courses] — cursos do usuário autenticado.
[nexo_course] — curso selecionado pelo parâmetro nexo_course ou contexto do curso.
[nexo_login] — formulário de login personalizado.
[nexo_manager_dashboard] — visão dos cursos para gerentes.

== Data ==
Desativar e excluir o plugin preserva os registros educacionais por padrão. A constante NEXO_DELETE_DATA=true no wp-config.php permite apagar os dados do Nexo na exclusão do plugin. Páginas, produtos e pedidos WooCommerce são preservados. Faça backup antes de optar pela limpeza.

== Updates ==
As atualizações são distribuídas nas Releases públicas de https://github.com/paulommaeda/cursos. Instale a versão 1.1.0 uma vez para ativar o atualizador nas instalações 1.0.0. Consulte Plugins > Plugins instalados > Verificar atualizações. As atualizações automáticas são opcionais e controladas pelo WordPress.

== Changelog ==
= 1.2.0 =
Tema Shalom Formação, navegação compartilhada, bloco de cursos, cadastro de alunos, matrícula gratuita e criação automática de produtos para cursos pagos.
= 1.1.0 =
Atualizações nativas no painel do WordPress a partir de Releases estáveis do GitHub; consulta manual, cache e detalhes da versão.

= 1.0.0 =
Primeira versão: gestão de cursos, EAD opcional, testes, presença, WooCommerce e identidade visual.
