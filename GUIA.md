# Área de gestão Shalom — versão 1.4.1

## Instalação e acesso

O administrador instala **nexo-cursos.zip** em Plugins e **shalom-formacao.zip** em Aparência → Temas. Para vender, ativa o WooCommerce e configura pagamentos, moeda e e-mails. Nas instalações existentes, os dois pacotes podem ser atualizados pelo painel do WordPress. A instalação inicial 1.0.0 precisa receber o ZIP atual manualmente uma vez para ganhar o atualizador.

Depois disso, a operação acontece no próprio site: entre pela página **Entrar** e abra **Área de gestão**. Contas da equipe vão diretamente para a gestão após o login. Alunos continuam na área de aprendizagem. O plugin prepara a página **Painel dos cursos** automaticamente; seu endereço padrão é `/painel-dos-cursos/`, respeitando a página já existente.

O wp-admin fica disponível somente a contas com permissão de administrador (`manage_options`). Gerentes, coordenação, equipe da loja e alunos são redirecionados para suas áreas no site quando tentam abrir o painel WordPress. As rotas internas de formulários e AJAX continuam funcionando, sem apresentar o painel.

## Perfis da equipe

O administrador usa **Área de gestão → Equipe e acessos** para criar pessoas, localizar uma conta existente pelo e-mail ou alterar o perfil:

- **Gerente de cursos:** edita os cursos atribuídos, conteúdos, testes, matrículas, aulas e chamadas. Acompanha produtos e os itens dos pedidos ligados aos seus cursos. Pode criar um pedido pendente e concluir um pedido já pago, quando todos os itens pertencem à sua gestão.
- **Coordenação:** administra todos os cursos, a apresentação do site e as vendas dos cursos. Pode confirmar pagamentos recebidos manualmente, vincular a conta do aluno e registrar reembolsos dos pedidos inteiramente sob sua gestão. Não recebe acesso ao wp-admin.
- **Gerente da loja:** quando o WooCommerce está ativo, mantém a gestão dos produtos e pedidos da loja no site. Não recebe, por esse perfil, edição de conteúdo pedagógico de todos os cursos.
- **Administrador:** tem a gestão completa no site e conserva o wp-admin para instalação de extensões, atualizações e configurações técnicas do WordPress/WooCommerce.

Atribua os gerentes em **Cursos → Editar curso → Gerentes responsáveis**. Contas de administrador são preservadas pela edição da equipe no site. A área de alunos não oferece alteração de senha, e-mail ou permissões de terceiros.

## Cursos, conteúdos e testes

Em **Cursos**, crie ou edite título, apresentação, resumo, capa, cor e frase da capa, modalidade, situação, local, carga horária, vagas de referência e inscrição.

- **Gratuita:** o visitante segue ao cadastro/login e confirma a matrícula, sem carrinho ou pedido.
- **Paga:** informe o preço e, opcionalmente, um preço promocional menor. O produto virtual é criado e sincronizado automaticamente com título, descrição, resumo e capa do curso.
- **Somente matrícula manual:** a equipe matricula contas já cadastradas. A ausência de preço não torna um curso pago gratuito.

Vínculos anteriores com produtos manuais são preservados. O estoque controla a disponibilidade para venda; o campo de vagas do curso é uma referência de planejamento.

Em **Conteúdos e testes**, selecione o curso para criar tópicos, aulas e testes. Todos são opcionais. Organize por ordem numérica, associe um tópico quando desejar e adicione o vídeo de uma aula ou seu texto. Nos testes, informe as perguntas, duas a seis alternativas por pergunta, a resposta correta e a nota mínima. O conteúdo principal do curso é público; aulas, tópicos e testes ficam protegidos para quem tem acesso. As tentativas dos testes preservam a melhor nota.

**Voltar para rascunho** oculta o curso do catálogo, desativa a venda do produto automático e suspende o acesso dos alunos até republicar. Não apaga matrículas, conteúdos ou histórico. Conteúdos também podem ser movidos para rascunho.

## Alunos e matrículas

Em **Alunos e matrículas**, filtre pelo curso ou busque entre os alunos sob sua gestão. Abra o acompanhamento para consultar progresso e presenças.

Use **Matricular uma conta existente** para informar o e-mail do aluno. Ou use **Cadastrar e matricular novo aluno**: a conta é criada somente como aluno, com a opção de enviar um link para ele definir a senha. Configure a entrega de e-mails do site para que os convites cheguem.

A data da matrícula pode ser informada, até a data de hoje, para a primeira matrícula manual. Isso permite registrar a data real de entrada de uma turma já iniciada. Uma matrícula manual existente não tem sua data alterada por uma repetição da operação.

Remover a matrícula manual preserva o acesso concedido por outras compras válidas. Compras, cancelamentos e reembolsos são conciliados por origem. Uma compra de quantidade maior não distribui vagas automaticamente a contas diferentes.

A coordenação pode corrigir o nome de um aluno no acompanhamento. Contas da equipe e de administradores ficam protegidas dessa edição de dados de alunos.

## Programação presencial e chamada

Em **Cursos → Editar curso**, selecione **Gestão / presencial**. Preencha início, fim inclusive, horário, duração e frequência. Habilite a geração das aulas ao salvar.

Há encontro único, diário, dias úteis, semanal, quinzenal, mensal e intervalo personalizado em dias, semanas ou meses. É possível selecionar mais de um dia da semana. Sem seleção, usa o dia da data inicial. A frequência mensal ajusta para o último dia dos meses curtos e volta ao dia original quando possível. O limite é de 500 encontros por programação.

Exemplo: 16/02/2027 a 20/04/2027, semanal, terça-feira, 20h gera dez aulas. O exemplo não é importado na instalação.

Em **Aulas e chamadas**, selecione o curso e a aula. A chamada abre diretamente nos alunos. No celular, o calendário e os gráficos podem ser expandidos quando necessários. Registre **presente, ausente, justificada ou pendente** e salve. A chamada fica disponível a partir da data da aula.

Aulas extras, cancelamentos de encontros sem chamada, conclusão e reabertura são feitos nessa mesma área. Alterar a programação não duplica datas nem apaga aulas passadas, realizadas ou com presença. Encontros futuros retirados são cancelados; cancelamentos manuais não são desfeitos automaticamente. Para uma reposição, cancele um encontro sem registros e adicione uma aula extra.

O gráfico usa até os 12 últimos encontros considerados:

- **Frequência registrada:** presentes ÷ (presentes + ausentes). Justificadas e pendentes ficam fora dessa porcentagem.
- **Cobertura:** registros preenchidos ÷ registros esperados dos alunos elegíveis.
- **Pendências:** campos de chamada ainda sem registro. Eles não viram faltas automaticamente.

Aulas futuras, canceladas e alunos matriculados depois do encontro ficam fora da base correspondente. As contagens consideram alunos com matrícula ativa.

Chamadas antigas por data continuam preservadas. A opção **Importar chamadas antigas por data** copia registros somente quando existe uma única aula naquela data, sem sobrescrever chamadas já preenchidas. Nos cursos EAD, registros por data podem ser consultados e preenchidos no acompanhamento do aluno.

## Produtos, vendas e pedidos

**Produtos:** altere preços, promoção, código do produto, disponibilidade, estoque e imagem. Produtos gerados pelo curso mantêm título, descrição, preços e capa compartilhados com ele. Produtos com variações permitem selecionar a variação para editar valores e estoque.

**Vendas:** filtre por período, curso e situação. Consulte pedidos pagos, pagamentos pendentes e vendas líquidas dos reembolsos. Os totais são separados por moeda e usam a data de criação dos pedidos. Baixe o relatório CSV dos itens visíveis para sua conta.

**Pedidos:** consulte os detalhes e abra o link de pagamento de um pedido pendente. A criação manual exige uma conta de aluno existente, usa o preço atual do produto e aceita um cupom existente. O pedido nasce pendente; criar o pedido não libera o curso.

A equipe financeira pode confirmar o recebimento manualmente, alterar a situação, vincular uma conta e reembolsar o saldo restante. Alterar manualmente a situação para paga não realiza cobrança no cartão. Pedidos processando/concluídos liberam a matrícula da conta compradora; pagamentos pendentes não liberam acesso.

Pedidos com itens fora dos cursos de um gerente mostram somente seus itens e valores. Esse gerente não pode alterar a situação, as notas, a conta ou o reembolso do pedido inteiro. A coordenação dos cursos também não altera um pedido que contenha produtos alheios à gestão de cursos; o administrador ou o gerente da loja pode tratar o pedido completo.

Observações da equipe são internas. No reembolso, escolha **devolver pelo meio de pagamento** ou **registrar um valor já devolvido por outro meio**. O registro manual não transfere dinheiro. A devolução pelo serviço de pagamento depende do suporte do serviço usado na compra. Reembolsos exigem motivo e confirmação; o saldo restante e as quantidades são conciliados com o WooCommerce.

O histórico anterior de pedidos é organizado em segundo plano. Se necessário, use **Continuar organização do histórico** na área de pedidos. Essa organização não altera pagamentos ou pedidos.

## Identidade visual e página inicial

Em **Identidade visual**, altere nome, logo, paleta de 12 cores, fontes de títulos e textos, tamanho base de 16 a 20 px, arredondamento e textos da tela de acesso. O padrão de leitura é 17 px, com campos de 16 px e controles confortáveis para toque. As fontes são locais, sem serviços externos.

Em **Página inicial**, personalize textos, botões e destinos, imagem principal, rodapé, rótulos do menu, quantidade de cursos, ordem e visibilidade das seções. A imagem principal pode substituir a ilustração de montanhas e caminho. As alterações também são reconhecidas pelo Personalizador nativo, disponível ao administrador.

Na ordem das seções use `hero,strip,purpose,courses,scripture,journey`: reordene ou remova os nomes para ocultar seções. Cada curso aceita capa, frase e cor próprias. A identidade Shalom usa azul profundo, ciano, branco e luz dourada como ponto de partida e pode ser substituída para outra organização. Valores personalizados não são reaplicados automaticamente pela migração.

## Atualizações, dados e alcance

Plugin e tema usam Releases estáveis de https://github.com/paulommaeda/cursos. O servidor precisa acessar o GitHub por HTTPS; não é necessário token. Atualizações automáticas são uma opção do administrador do WordPress.

Requisitos: WordPress 6.4+, PHP 8.1+ e WooCommerce 8.2+ para vendas. A gestão de cursos gratuitos e matrículas manuais funciona sem WooCommerce.

Desativar ou excluir o plugin preserva dados educacionais por padrão. A constante `NEXO_DELETE_DATA=true` é uma opção explícita de limpeza na exclusão, documentada no readme. Páginas, produtos e registros financeiros do WooCommerce são mantidos. As presenças participam das ferramentas de exportação e exclusão de dados pessoais.

A entrega não instala gateway próprio, contabilidade, emissão fiscal, certificados ou comissões. Configurações técnicas de extensões, pagamentos e WordPress continuam com o administrador. Vídeos externos dependem do provedor e não têm DRM; arquivos de mídia podem ter URLs públicas.

As imagens e prévias usam dados demonstrativos da instalação de testes. Eles não são importados ao instalar os pacotes e nenhum site de produção foi alterado nesta entrega.
