# Shalom Formação — instalação e uso

A versão 1.2.0 reúne o plugin Nexo Cursos e o tema Shalom Formação. A home apresenta os cursos livres da Igreja Shalom e o propósito de **discipular, aperfeiçoar e enviar**, inspirado em Efésios 4:12.

## Instalar os dois pacotes

1. Ative o WooCommerce se houver cursos pagos. Configure moeda, meios de pagamento e visibilidade pública da loja no WooCommerce.
2. Em **Plugins → Adicionar plugin → Enviar plugin**, envie **nexo-cursos.zip** e ative. Se já usa o plugin, substitua pela nova versão ou atualize pelo painel.
3. Em **Aparência → Temas → Adicionar tema → Enviar tema**, envie **shalom-formacao.zip** e ative.
4. A página **Formação Shalom** será criada e definida como início. O conteúdo da sua home anterior não é apagado. Ao trocar de tema, a configuração anterior é restaurada se a home ainda for a página criada pelo tema.

Os ZIPs são separados: envie o plugin na área de plugins e o tema na área de temas. Os arquivos automáticos “Source code” do GitHub não são esses pacotes instaláveis.

Requisitos: WordPress 6.4+, PHP 8.1+ e WooCommerce 8.2+ para vendas. Cursos gratuitos e matrículas manuais funcionam sem WooCommerce.

## Cadastrar um curso

Abra **Nexo Cursos → Cursos → Adicionar curso**. Preencha o título, apresentação pública, resumo e imagem. Escolha a inscrição:

- **Gratuito:** o curso aparece com o botão **Inscreva-se grátis**. O aluno vai ao cadastro/login e recebe a matrícula após autenticação, sem carrinho ou pedido.
- **Pago:** informe um preço maior que zero. Ao salvar, um produto virtual de venda individual é criado automaticamente no WooCommerce. Título, descrição, resumo e imagem vêm do curso. Atualizações do curso sincronizam o produto gerado.
- **Somente matrícula manual:** a equipe matricula usuários já cadastrados pelo painel de gestão. O curso não é liberado gratuitamente por falta de produto ou preço.

Publicar o curso disponibiliza o produto automático quando o preço é válido. Cursos em rascunho, protegidos por senha ou sem preço válido mantêm o produto automático em rascunho. Ao mudar um curso automático para gratuito ou enviá-lo à lixeira, sua antiga venda é desativada. Pedidos e matrículas existentes são preservados.

O campo de produto vinculado continua disponível para casos avançados e vínculos antigos. Produtos vinculados manualmente não têm título, preço ou descrição sobrescritos pelo sincronizador. Configure desconto, impostos, estoque e meios de pagamento conforme as opções do WooCommerce. O campo de vagas do curso é uma referência; use estoque para limitar vendas.

## Cadastro, login e área do aluno

A navegação é fornecida pelo plugin: início, cursos, propósito, meus cursos, conta, carrinho e entrada. O painel do gerente aparece apenas para administradores e gerentes. O menu se adapta ao celular.

No cadastro, o aluno informa nome, e-mail e senha. A conta recebe apenas o papel de assinante/aluno. Não é necessário abrir o cadastro geral do WordPress nem alterar o papel padrão de novos usuários. Uma conta existente deve usar o login; ela não é recriada nem associada a compras apenas por compartilhar um e-mail.

O fluxo gratuito transporta o curso escolhido até o cadastro/login. Se o aluno já estiver conectado, confirma a matrícula gratuita com um botão. Nos cursos pagos, o acesso é liberado quando o pedido da conta compradora estiver Processando ou Concluído. Pagamentos pendentes não liberam conteúdo.

Cancelamentos e reembolsos revogam a origem correspondente da matrícula; outra compra válida ou uma matrícula manual preserva o acesso. Reembolsos de item dependem da quantidade integral devolvida. A compra em quantidade não distribui vagas automaticamente para outras contas.

## Tópicos, aulas, testes e gestão presencial

Todos os componentes são opcionais. Crie tópicos, aulas e testes no menu Nexo Cursos e associe cada um ao curso correspondente. Aulas e testes podem ficar sem tópico. Use a ordem numérica para organizar o conteúdo. O texto principal do curso é a apresentação pública; o texto das aulas e dos tópicos fica protegido para alunos matriculados.

Nos testes, adicione perguntas, pelo menos duas alternativas por pergunta, uma resposta correta e nota mínima. Publique quando as perguntas estiverem prontas; um teste vazio não pode ser concluído. O aluno pode repetir e a melhor nota é preservada.

O formato **Gestão / presencial** mantém matrículas, início, local, carga horária e presenças. A área de aprendizagem fica desativada, mesmo que o curso tenha aulas cadastradas. Um curso pode ter matrícula gratuita ou paga independentemente de ser EAD ou presencial.

Crie usuários com papel **Gerente de cursos** e atribua-os nos cursos. Gerentes acompanham apenas os cursos atribuídos, com matrículas, progresso e presença por data. A edição de cursos, conteúdos e identidade visual permanece com o administrador.

## Personalizar

- **Aparência → Personalizar:** logo, título principal e texto de apresentação da home.
- **Nexo Cursos → Identidade visual:** nome, logo, cores e textos de login. O tema compartilha essa identidade e fornece a marca Shalom como padrão, preservando configurações personalizadas.
- O bloco da home mostra até três cursos publicados mais recentes. O catálogo completo permanece na página Cursos. Sem cursos publicados, aparece uma mensagem de preparação, sem dados fictícios.

## Páginas e atalhos

O plugin cria catálogo, meus cursos, login e painel dos gerentes. Com WooCommerce, prepara loja, carrinho, checkout e conta, reaproveitando páginas existentes. Se ativar o WooCommerce depois, abra o painel para concluir a preparação. Em Identidade visual, use Verificar e criar páginas ausentes se necessário.

Shortcodes disponíveis: `[nexo_catalog]`, `[nexo_available_courses limit="3"]`, `[nexo_navigation]`, `[nexo_my_courses]`, `[nexo_login]`, `[nexo_manager_dashboard]` e `[nexo_course id="123"]`.

## Atualizações e alcance

Plugin e tema usam as Releases estáveis de [paulommaeda/cursos](https://github.com/paulommaeda/cursos/releases). O servidor precisa acessar o GitHub por HTTPS; não é necessário token. O plugin oferece os controles nativos de atualização do plugin e do tema. As atualizações automáticas são uma escolha do administrador.

A entrega inclui gestão educacional, cursos livres, matrículas, testes, presença e integração de vendas. Não inclui gateway próprio, contabilidade, emissão fiscal, certificados, comissões ou licenças para múltiplos alunos por compra. Vídeos externos dependem do provedor e não têm DRM. Arquivos enviados à biblioteca de mídia do WordPress podem ter URL pública; conteúdo confidencial não deve ser distribuído como mídia pública.

Desativar ou excluir o plugin preserva os dados educacionais por padrão. A limpeza definitiva é uma opção explícita documentada no readme. Páginas e registros financeiros do WooCommerce são mantidos.

Antes de abrir inscrições, teste cadastro/login, matrícula gratuita, compra em modo de testes, acesso, permissões e reembolso na combinação de tema, gateway e extensões do seu site. Nenhuma instalação de produção foi alterada nesta entrega.
