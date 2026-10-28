<?php
/** Administration and course operations. */
namespace NexoCursos;
defined('ABSPATH') || exit;
final class Admin {
    private const TYPES = ['nexo_course', 'nexo_topic', 'nexo_lesson', 'nexo_quiz'];
    public static function boot(): void {
        add_action('admin_menu', [self::class, 'menu']);
        add_action('admin_menu', [self::class, 'reorder_menu'], 99);
        add_action('admin_enqueue_scripts', [self::class, 'assets']);
        add_action('add_meta_boxes', [self::class, 'meta_boxes']);
        add_action('save_post', [self::class, 'save_meta'], 10, 2);
        add_action('admin_post_nexo_save_brand', [self::class, 'save_brand']);
        add_action('admin_post_nexo_enrollment', [self::class, 'enrollment_action']);
        add_action('admin_post_nexo_attendance', [self::class, 'attendance_action']);
        add_filter('manage_nexo_course_posts_columns', [self::class, 'course_columns']);
        add_action('manage_nexo_course_posts_custom_column', [self::class, 'course_column'], 10, 2);
    }
    private static function allowed(): bool {
        return current_user_can('manage_options') || current_user_can('nexo_manage_assigned');
    }
    public static function menu(): void {
        if (!self::allowed()) { return; }
        add_menu_page('Nexo Cursos', 'Nexo Cursos', 'read', 'nexo-cursos', [self::class, 'dashboard'], 'dashicons-welcome-learn-more', 26);
        add_submenu_page('nexo-cursos', 'Visão geral', 'Visão geral', 'read', 'nexo-cursos', [self::class, 'dashboard']);
        add_submenu_page('nexo-cursos', 'Identidade visual', 'Identidade visual', 'manage_options', 'nexo-identidade', [self::class, 'brand_page']);
    }
    /** Keep the dashboard as the top-level destination after WordPress adds CPT links. */
    public static function reorder_menu(): void {
        global $submenu;
        if (empty($submenu['nexo-cursos'])) { return; }
        usort($submenu['nexo-cursos'], static function (array $a, array $b): int {
            return (int)(($b[2] ?? '') === 'nexo-cursos') - (int)(($a[2] ?? '') === 'nexo-cursos');
        });
    }
    public static function assets(string $hook): void {
        $screen = get_current_screen();
        if (strpos($hook, 'nexo') === false && (!$screen || !in_array($screen->post_type, self::TYPES, true))) { return; }
        wp_enqueue_style('nexo-admin', NEXO_URL . 'assets/admin.css', [], NEXO_VERSION);
        wp_add_inline_style('nexo-admin', ':root{' . \nexo_brand_styles() . '}');
        wp_enqueue_script('nexo-admin', NEXO_URL . 'assets/admin.js', ['jquery'], NEXO_VERSION, true);
        if (strpos($hook, 'nexo-identidade') !== false) { wp_enqueue_media(); }
    }
    private static function url(array $args = []): string {
        return add_query_arg(array_merge(['page' => 'nexo-cursos'], $args), admin_url('admin.php'));
    }
    private static function check_course(int $course): void {
        if (!self::allowed() || !$course || get_post_type($course) !== 'nexo_course' || !Core::can_manage($course)) {
            wp_die(esc_html__('Você não tem permissão para gerenciar este curso.', 'nexo-cursos'), '', ['response' => 403]);
        }
    }
    private static function redirect(string $notice, int $course = 0): void {
        wp_safe_redirect(self::url(['nexo_notice' => $notice, 'course_id' => $course])); exit;
    }
    public static function enrollment_action(): void {
        check_admin_referer('nexo_enrollment');
        $course = absint($_POST['course_id'] ?? 0); self::check_course($course);
        $identifier = sanitize_text_field(wp_unslash($_POST['student'] ?? ''));
        $user = ctype_digit($identifier) ? get_user_by('id', absint($identifier)) : get_user_by('email', sanitize_email($identifier));
        if (!$user) { self::redirect('user_missing', $course); }
        $operation = sanitize_key($_POST['operation'] ?? 'enroll');
        if (!in_array($operation, ['enroll', 'revoke'], true)) { self::redirect('invalid', $course); }
        $result = $operation === 'revoke' ? Core::revoke((int)$user->ID, $course, 0) : Core::enroll((int)$user->ID, $course, 0);
        self::redirect(is_wp_error($result) || !$result ? 'error' : ($operation === 'revoke' ? 'revoked' : 'enrolled'), $course);
    }
    public static function attendance_action(): void {
        check_admin_referer('nexo_attendance');
        $course = absint($_POST['course_id'] ?? 0); self::check_course($course);
        $student = absint($_POST['user_id'] ?? 0);
        $date = sanitize_text_field(wp_unslash($_POST['attendance_date'] ?? ''));
        $status = sanitize_key($_POST['attendance_status'] ?? '');
        $active = false;
        foreach (Core::enrollments($course) as $row) { if ((int)$row->user_id === $student && $row->status === 'active') { $active = true; break; } }
        if (!$active) { self::redirect('invalid', $course); }
        $result = Core::set_attendance($course, $student, $date, $status);
        self::redirect(is_wp_error($result) || !$result ? 'error' : 'attendance', $course);
    }
    private static function shell_start(string $active = 'overview'): void {
        $settings = \nexo_settings(); $name = $settings['brand_name'];
        $logo = !empty($settings['logo_id']) ? wp_get_attachment_image((int)$settings['logo_id'], 'thumbnail', false, ['class' => 'nexo-brand-logo', 'alt' => $name]) : '';
        echo '<div class="nexo-admin"><aside class="nexo-sidebar"><a class="nexo-brand" href="' . esc_url(self::url()) . '">' . ($logo ?: '<span class="nexo-brand-symbol">n</span>') . '<span>' . esc_html($name) . '<small>GESTÃO DE APRENDIZAGEM</small></span></a><div class="nexo-nav-label">PLATAFORMA</div><nav><a class="' . ($active === 'overview' ? 'is-active' : '') . '" href="' . esc_url(self::url()) . '"><span class="dashicons dashicons-chart-area"></span> Visão geral</a>';
        if (current_user_can('manage_options')) {
            echo '<a href="' . esc_url(admin_url('edit.php?post_type=nexo_course')) . '"><span class="dashicons dashicons-welcome-learn-more"></span> Cursos</a><a href="' . esc_url(admin_url('edit.php?post_type=nexo_lesson')) . '"><span class="dashicons dashicons-video-alt3"></span> Conteúdos</a><a class="' . ($active === 'brand' ? 'is-active' : '') . '" href="' . esc_url(admin_url('admin.php?page=nexo-identidade')) . '"><span class="dashicons dashicons-art"></span> Identidade visual</a>';
        }
        echo '<a href="' . esc_url(\nexo_page_url('catalog')) . '"><span class="dashicons dashicons-store"></span> Ver catálogo ↗</a></nav><div class="nexo-sidebar-note"><span class="nexo-status-dot"></span> Tudo em um só lugar<p>Cursos, alunos e aprendizado conectados.</p></div><div class="nexo-sidebar-user">' . get_avatar(get_current_user_id(), 36) . '<span>' . esc_html(wp_get_current_user()->display_name) . '<small>' . (current_user_can('manage_options') ? 'Administrador' : 'Gerente de cursos') . '</small></span></div></aside><main class="nexo-main">';
    }
    private static function shell_end(): void { echo '</main></div>'; }
    public static function dashboard(): void {
        if (!self::allowed()) { wp_die(esc_html__('Acesso restrito.', 'nexo-cursos'), '', ['response' => 403]); }
        $ids = array_map('absint', Core::course_ids_for_manager());
        $courses = $ids ? get_posts(['post_type' => 'nexo_course', 'post_status' => ['publish', 'draft', 'pending', 'private', 'future'], 'post__in' => $ids, 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC']) : [];
        $selected = absint($_GET['course_id'] ?? 0);
        if ($selected && !in_array($selected, $ids, true)) { $selected = 0; }
        $search = sanitize_text_field(wp_unslash($_GET['student_search'] ?? ''));
        $students = []; $online = 0; $active_counts = []; $completion_sum = 0; $completed = 0; $tracked = 0;
        foreach ($courses as $course) {
            if (get_post_meta($course->ID, '_nexo_mode', true) !== 'erp') { $online++; }
            $track_progress = get_post_meta($course->ID, '_nexo_mode', true) !== 'erp' && count(array_filter(Core::curriculum((int)$course->ID), static fn($item) => in_array($item->post_type, ['nexo_lesson', 'nexo_quiz'], true))) > 0;
            $active_counts[$course->ID] = [];
            foreach (Core::enrollments((int)$course->ID) as $row) {
                if ($row->status !== 'active') { continue; }
                $active_counts[$course->ID][(int)$row->user_id] = true;
                if ($selected && $selected !== (int)$course->ID) { continue; }
                $key = $row->user_id . '-' . $course->ID;
                if (isset($students[$key])) { $students[$key]['sources'][] = (int)$row->order_id; continue; }
                $user = get_user_by('id', $row->user_id); if (!$user) { continue; }
                $progress = Core::progress((int)$course->ID, (int)$user->ID);
                $students[$key] = ['user' => $user, 'course' => $course, 'date' => $row->created_at, 'sources' => [(int)$row->order_id], 'progress' => (int)$progress['percent'], 'track_progress' => $track_progress];
            }
        }
        foreach ($students as $student) { if (!$student['track_progress']) { continue; } $tracked++; $completion_sum += $student['progress']; if ($student['progress'] >= 100) { $completed++; } }
        $total = count($students); $mean = $tracked ? (int)round($completion_sum / $tracked) : 0;
        self::shell_start();
        echo '<div class="nexo-topline"><span>Seu espaço de gestão <b>/ Visão geral</b></span><span>' . esc_html(wp_date('d \d\e F, Y')) . '</span></div><header class="nexo-page-header"><div><span class="nexo-eyebrow">PAINEL DO GERENTE</span><h1>Ensinar transforma.<br><span>Gerenciar pode ser simples.</span></h1><p>Acompanhe seus cursos e cuide de cada etapa da jornada.</p></div>';
        if (current_user_can('manage_options')) { echo '<a class="nexo-button" href="' . esc_url(admin_url('post-new.php?post_type=nexo_course')) . '"><span>＋</span> Criar curso</a>'; }
        echo '</header>'; self::notice(); echo '<div class="nexo-kpis">';
        self::kpi('Cursos na plataforma', count($courses), 'Organizados do seu jeito', 'welcome-learn-more');
        self::kpi('Matrículas ativas', $total, $selected ? 'No curso selecionado' : 'Em todos os seus cursos', 'groups');
        self::kpi('Progresso médio', $mean . '%', 'Dos cursos com aulas ou testes', 'chart-bar');
        self::kpi('Cursos com EAD', $online, 'Aulas e testes opcionais', 'video-alt3');
        echo '</div><div class="nexo-overview-grid"><section class="nexo-panel nexo-course-panel"><div class="nexo-panel-title"><div><h2>Seus cursos</h2><p>Uma visão clara de cada experiência.</p></div><span class="nexo-count">' . count($courses) . '</span></div>';
        if (!$courses) { echo '<div class="nexo-empty"><span class="dashicons dashicons-welcome-learn-more"></span><h3>Seu próximo curso começa aqui</h3><p>' . (current_user_can('manage_options') ? 'Crie um curso, vincule um produto e escolha como vai ensinar.' : 'Peça ao administrador para atribuir cursos ao seu usuário.') . '</p></div>'; }
        foreach ($courses as $course) {
            $mode = get_post_meta($course->ID, '_nexo_mode', true) === 'erp' ? 'erp' : 'online';
            $count = count($active_counts[$course->ID] ?? []); $capacity = absint(get_post_meta($course->ID, '_nexo_capacity', true));
            $lesson_count = count(array_filter(Core::curriculum((int)$course->ID), static fn($item) => $item->post_type === 'nexo_lesson'));
            $thumb = get_the_post_thumbnail($course->ID, 'thumbnail', ['class' => 'nexo-course-thumb']);
            echo '<article class="nexo-course-row"><div class="nexo-course-cover ' . ($mode === 'erp' ? 'is-erp' : '') . '">' . ($thumb ?: '<span class="dashicons dashicons-' . ($mode === 'erp' ? 'businessperson' : 'welcome-learn-more') . '"></span>') . '</div><div class="nexo-course-copy"><span class="nexo-pill ' . ($mode === 'erp' ? 'is-erp' : '') . '">' . ($mode === 'erp' ? 'Gestão / presencial' : 'Ensino a distância') . '</span><h3>' . esc_html($course->post_title ?: '(Sem título)') . '</h3><p>' . esc_html($count . ' alunos' . ($capacity ? ' · referência de ' . $capacity . ' vagas' : '') . ' · ' . $lesson_count . ' aulas') . ' <span class="nexo-status-label">' . esc_html(get_post_status_object($course->post_status)->label) . '</span></p></div><a class="nexo-icon-link" aria-label="' . esc_attr('Gerenciar ' . $course->post_title) . '" href="' . esc_url(self::url(['course_id' => $course->ID])) . '">↗</a>';
            if (current_user_can('manage_options')) { echo '<a class="nexo-small-link" href="' . esc_url(get_edit_post_link($course->ID)) . '">Editar</a>'; } echo '</article>';
        }
        echo '</section><aside class="nexo-panel nexo-progress-panel"><span class="nexo-eyebrow">APRENDIZAGEM EM MOVIMENTO</span><h2>Cada passo conta.</h2><div class="nexo-donut" style="--progress:' . esc_attr((string)$mean) . '"><div><strong>' . $mean . '%</strong><span>progresso médio</span></div></div><div class="nexo-mini-stat"><span><i></i> Matrículas concluídas</span><strong>' . $completed . '</strong></div><div class="nexo-mini-stat"><span><i class="muted"></i> Em desenvolvimento</span><strong>' . ($tracked - $completed) . '</strong></div><p class="nexo-footnote">O progresso considera aulas concluídas e testes aprovados. Cursos de gestão e sem conteúdo ficam fora desta média.</p></aside></div>';
        self::students_section($courses, $students, $selected, $search);
        echo '<footer class="nexo-footer">' . esc_html(\nexo_settings()['brand_name']) . ' <span>Mais espaço para ensinar. Mais clareza para crescer.</span></footer>'; self::shell_end();
    }
    private static function kpi(string $label, $value, string $caption, string $icon): void {
        echo '<article class="nexo-kpi"><span class="nexo-kpi-icon dashicons dashicons-' . esc_attr($icon) . '"></span><span class="nexo-kpi-label">' . esc_html($label) . '</span><strong>' . esc_html((string)$value) . '</strong><small>' . esc_html($caption) . '</small></article>';
    }
    private static function students_section(array $courses, array $students, int $selected, string $search): void {
        $date = sanitize_text_field(wp_unslash($_GET['attendance_date'] ?? wp_date('Y-m-d')));
        $date_object = preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? \DateTimeImmutable::createFromFormat('!Y-m-d', $date, wp_timezone()) : false;
        if (!$date_object || $date_object->format('Y-m-d') !== $date || (int)$date_object->format('Y') < 1) {
            $date = wp_date('Y-m-d');
            $date_object = \DateTimeImmutable::createFromFormat('!Y-m-d', $date, wp_timezone());
        }
        echo '<section class="nexo-panel nexo-students"><div class="nexo-panel-title"><div><h2>Alunos & matrículas</h2><p>Gente real, acompanhamento próximo.</p></div></div><form method="get" class="nexo-filters"><input type="hidden" name="page" value="nexo-cursos"><label>Curso<select name="course_id"><option value="0">Todos os cursos</option>';
        foreach ($courses as $course) { echo '<option value="' . absint($course->ID) . '" ' . selected($selected, $course->ID, false) . '>' . esc_html($course->post_title) . '</option>'; }
        echo '</select></label><label>Buscar aluno<input name="student_search" placeholder="Nome ou e-mail" value="' . esc_attr($search) . '"></label><label>Data da presença<input type="date" name="attendance_date" value="' . esc_attr($date) . '"></label><button class="nexo-button nexo-button-secondary">Aplicar filtros</button></form><div class="nexo-table-scroll"><table class="nexo-table"><thead><tr><th>Aluno</th><th>Curso</th><th>Origem</th><th>Progresso</th><th>Presença em ' . esc_html($date_object->format('d/m')) . '</th><th>Ações</th></tr></thead><tbody>';
        if ($search !== '') { $students = array_filter($students, static fn($s) => stripos($s['user']->display_name . ' ' . $s['user']->user_email, $search) !== false); }
        $page = max(1, absint($_GET['nexo_paged'] ?? 1)); $page_count = max(1, (int)ceil(count($students) / 25)); $page = min($page, $page_count);
        $students_page = array_slice($students, ($page - 1) * 25, 25, true);
        foreach ($students_page as $student) {
            $user = $student['user']; $course = $student['course']; $attendance = Core::attendance((int)$course->ID, (int)$user->ID); $sources = array_unique($student['sources']);
            $source_label = in_array(0, $sources, true) ? 'Manual' : 'WooCommerce';
            if (in_array(0, $sources, true) && count($sources) > 1) { $source_label = 'Manual + compra'; }
            $progress_html = $student['track_progress'] ? '<div class="nexo-progress-bar"><span style="width:' . absint($student['progress']) . '%"></span></div><small>' . absint($student['progress']) . '%</small>' : '<small>Não se aplica</small>';
            echo '<tr><td><div class="nexo-student-cell">' . get_avatar($user->ID, 36) . '<div><strong>' . esc_html($user->display_name) . '</strong><small>' . esc_html($user->user_email) . '</small></div></div></td><td>' . esc_html($course->post_title) . '</td><td><span class="nexo-source">' . esc_html($source_label) . '</span></td><td>' . $progress_html . '</td><td><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="nexo-attendance-form">'; wp_nonce_field('nexo_attendance');
            echo '<input type="hidden" name="action" value="nexo_attendance"><input type="hidden" name="course_id" value="' . absint($course->ID) . '"><input type="hidden" name="user_id" value="' . absint($user->ID) . '"><input type="hidden" name="attendance_date" value="' . esc_attr($date) . '"><select name="attendance_status" aria-label="' . esc_attr('Presença de ' . $user->display_name) . '"><option value="">Registrar</option>';
            foreach (['present' => 'Presente', 'absent' => 'Ausente', 'excused' => 'Justificada'] as $value => $label) { echo '<option value="' . esc_attr($value) . '" ' . selected($attendance[$date] ?? '', $value, false) . '>' . esc_html($label) . '</option>'; }
            echo '</select><button class="nexo-text-button">Salvar</button></form></td><td>';
            if (in_array(0, $sources, true)) {
                echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" data-nexo-confirm="Remover apenas a matrícula manual deste aluno? Matrículas de compras serão preservadas.">'; wp_nonce_field('nexo_enrollment');
                echo '<input type="hidden" name="action" value="nexo_enrollment"><input type="hidden" name="operation" value="revoke"><input type="hidden" name="course_id" value="' . absint($course->ID) . '"><input type="hidden" name="student" value="' . absint($user->ID) . '"><button class="nexo-text-button nexo-danger">Remover manual</button></form>';
            } else { echo '<span class="nexo-footnote">Gerenciada pelo pedido</span>'; }
            echo '</td></tr>';
        }
        if (!$students_page) { echo '<tr><td colspan="6"><div class="nexo-empty"><h3>Nenhuma matrícula por aqui</h3><p>As compras aprovadas entram automaticamente. Você também pode matricular um usuário abaixo.</p></div></td></tr>'; }
        echo '</tbody></table></div>';
        if ($page_count > 1) {
            echo '<div class="nexo-pagination">' . wp_kses_post(paginate_links(['base' => add_query_arg('nexo_paged', '%#%', self::url(['course_id' => $selected, 'student_search' => $search, 'attendance_date' => $date])), 'current' => $page, 'total' => $page_count, 'prev_text' => '←', 'next_text' => '→'])) . '</div>';
        }
        if ($courses) {
            echo '<div class="nexo-enroll-box"><div><h3>Adicionar matrícula manual</h3><p>Use um usuário existente no WordPress. O limite de vagas é uma referência de planejamento.</p></div><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">'; wp_nonce_field('nexo_enrollment');
            echo '<input type="hidden" name="action" value="nexo_enrollment"><input type="hidden" name="operation" value="enroll"><label class="screen-reader-text" for="nexo-student">E-mail ou ID do usuário</label><input id="nexo-student" name="student" required placeholder="E-mail ou ID do usuário"><label class="screen-reader-text" for="nexo-enroll-course">Curso</label><select id="nexo-enroll-course" name="course_id" required>';
            foreach ($courses as $course) { echo '<option value="' . absint($course->ID) . '" ' . selected($selected, $course->ID, false) . '>' . esc_html($course->post_title) . '</option>'; }
            echo '</select><button class="nexo-button">Matricular aluno</button></form></div>';
        }
        echo '</section>';
    }
    private static function notice(): void {
        $messages = ['enrolled' => 'Matrícula manual adicionada.', 'revoked' => 'Matrícula manual removida. Compras continuam válidas.', 'attendance' => 'Presença registrada.', 'user_missing' => 'Usuário não encontrado. Cadastre o aluno no WordPress primeiro.', 'error' => 'Não foi possível salvar. Confira os dados e tente novamente.', 'invalid' => 'Dados inválidos para esta operação.', 'brand' => 'Identidade visual atualizada.'];
        $key = sanitize_key($_GET['nexo_notice'] ?? '');
        if (isset($messages[$key])) { echo '<div class="nexo-notice" role="status">' . esc_html($messages[$key]) . '</div>'; }
    }
    public static function meta_boxes(): void {
        if (!current_user_can('manage_options')) { return; }
        add_meta_box('nexo-course-config', 'Configuração do curso', [self::class, 'course_meta'], 'nexo_course', 'normal', 'high');
        foreach (['nexo_topic', 'nexo_lesson', 'nexo_quiz'] as $type) { add_meta_box('nexo-content-config', 'Organização do conteúdo', [self::class, 'content_meta'], $type, 'normal', 'high'); }
        add_meta_box('nexo-quiz-questions', 'Perguntas do teste', [self::class, 'quiz_meta'], 'nexo_quiz', 'normal', 'high');
    }
    public static function course_meta(\WP_Post $post): void {
        wp_nonce_field('nexo_meta', 'nexo_meta_nonce'); $get = static fn($key) => get_post_meta($post->ID, '_nexo_' . $key, true);
        $sale_mode = $post->post_status === 'auto-draft' ? 'free' : Commerce::pricing_mode((int)$post->ID);
        echo '<div class="nexo-meta-grid"><label>Inscrição<select name="nexo_enrollment_mode">';
        foreach (['free'=>'Gratuito — cadastro e matrícula direta','paid'=>'Pago — produto automático no WooCommerce','manual'=>'Somente matrícula manual'] as $value=>$label) { echo '<option value="'.esc_attr($value).'" '.selected($sale_mode,$value,false).'>'.esc_html($label).'</option>'; }
        echo '</select></label><label>Preço do curso<input type="number" step="0.01" min="0" name="nexo_price" value="'.esc_attr($get('price')).'" placeholder="Ex.: 90.00"><small>Nos cursos pagos, informe valor maior que zero. Título e descrição do produto vêm do curso.</small></label></div>';
        echo '<div class="nexo-meta-grid"><label>Formato<select name="nexo_mode"><option value="online" ' . selected($get('mode'), 'online', false) . '>EAD — aulas e testes opcionais</option><option value="erp" ' . selected($get('mode'), 'erp', false) . '>Gestão / presencial — sem área de aprendizagem</option></select></label><label>Produto vinculado (opcional / avançado)<input name="nexo_product_id" type="number" min="0" value="' . absint($get('product_id')) . '"><small>Deixe vazio para criar o produto automaticamente ao salvar um curso pago. Vínculos manuais existentes são preservados.</small></label>';
        if (function_exists('wc_get_product') && $get('product_id')) { $product = wc_get_product(absint($get('product_id'))); if ($product) { echo '<p class="nexo-field-wide">Produto vinculado: <a href="' . esc_url(get_edit_post_link($product->get_id())) . '">' . esc_html($product->get_name()) . '</a></p>'; } }
        echo '<label>Início<input type="date" name="nexo_start_date" value="' . esc_attr($get('start_date')) . '"></label><label>Carga horária (texto)<input name="nexo_workload" value="' . esc_attr($get('workload')) . '" placeholder="Ex.: 40 horas"></label><label>Local / unidade<input name="nexo_location" value="' . esc_attr($get('location')) . '" placeholder="Ex.: Unidade Centro"></label><label>Vagas de referência<input type="number" min="0" name="nexo_capacity" value="' . absint($get('capacity')) . '"><small>Informativo. O estoque do produto controla as vendas.</small></label><label class="nexo-field-wide">Gerentes responsáveis<select multiple name="nexo_manager_ids[]" size="5">';
        $managers = array_map('absint', (array)$get('manager_ids'));
        foreach (get_users(['role__in' => ['administrator', 'nexo_manager'], 'orderby' => 'display_name', 'number' => 500]) as $user) { echo '<option value="' . absint($user->ID) . '" ' . selected(in_array((int)$user->ID, $managers, true), true, false) . '>' . esc_html($user->display_name . ' — ' . $user->user_email) . '</option>'; }
        echo '</select><small>Crie usuários com o papel “Gerente de cursos” em Usuários. Segure Ctrl para selecionar mais de um.</small></label></div><p>Todos os tópicos, aulas e testes são opcionais. Use o conteúdo principal como apresentação pública do curso.</p>';
    }
    public static function content_meta(\WP_Post $post): void {
        wp_nonce_field('nexo_meta', 'nexo_meta_nonce'); $course = absint(get_post_meta($post->ID, '_nexo_course_id', true)); $topic = absint(get_post_meta($post->ID, '_nexo_topic_id', true));
        echo '<div class="nexo-meta-grid"><label>Curso<select name="nexo_course_id" required><option value="">Selecione um curso</option>';
        foreach (get_posts(['post_type' => 'nexo_course', 'post_status' => ['publish', 'draft', 'private', 'future', 'pending'], 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC']) as $item) { echo '<option value="' . absint($item->ID) . '" ' . selected($course, $item->ID, false) . '>' . esc_html($item->post_title) . '</option>'; }
        echo '</select></label><label>Ordem no curso<input type="number" name="nexo_menu_order" value="' . (int)$post->menu_order . '"><small>Itens aparecem em ordem crescente.</small></label>';
        if ($post->post_type !== 'nexo_topic') {
            echo '<label>Tópico (opcional)<select name="nexo_topic_id"><option value="0">Sem tópico</option>';
            foreach (get_posts(['post_type' => 'nexo_topic', 'post_status' => ['publish', 'draft', 'private', 'future', 'pending'], 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC']) as $item) { echo '<option data-course="' . absint(get_post_meta($item->ID, '_nexo_course_id', true)) . '" value="' . absint($item->ID) . '" ' . selected($topic, $item->ID, false) . '>' . esc_html(get_the_title(absint(get_post_meta($item->ID, '_nexo_course_id', true))) . ' / ' . $item->post_title) . '</option>'; }
            echo '</select></label>';
        }
        if ($post->post_type === 'nexo_lesson') { echo '<label>URL do vídeo (opcional)<input type="url" name="nexo_video_url" value="' . esc_attr(get_post_meta($post->ID, '_nexo_video_url', true)) . '" placeholder="https://…"><small>Use YouTube ou Vimeo com incorporação permitida. Adicione texto no editor.</small></label>'; }
        echo '</div>';
    }
    public static function quiz_meta(\WP_Post $post): void {
        $questions = get_post_meta($post->ID, '_nexo_questions', true); if (!is_array($questions)) { $questions = []; }
        echo '<label>Nota mínima para aprovação (%) <input type="number" min="0" max="100" name="nexo_pass_score" value="' . esc_attr(get_post_meta($post->ID, '_nexo_pass_score', true) === '' ? '70' : get_post_meta($post->ID, '_nexo_pass_score', true)) . '"></label><p>Cada pergunta tem uma resposta correta. As tentativas são livres. Perguntas vazias não serão salvas.</p><div id="nexo-questions">';
        foreach ($questions as $index => $question) { self::question_fields((string)$index, $question); }
        echo '</div><button type="button" class="button" id="nexo-add-question">＋ Adicionar pergunta</button><template id="nexo-question-template">'; self::question_fields('__INDEX__', ['question' => '', 'options' => ['', '', '', ''], 'correct' => 0]); echo '</template>';
    }
    private static function question_fields(string $index, array $question): void {
        echo '<fieldset class="nexo-question"><legend>Pergunta</legend><label>Enunciado<input class="widefat" name="nexo_questions[' . esc_attr($index) . '][question]" value="' . esc_attr($question['question'] ?? '') . '"></label><div class="nexo-question-options">';
        $options = array_pad((array)($question['options'] ?? []), 4, '');
        foreach ($options as $j => $option) { echo '<label><input type="radio" name="nexo_questions[' . esc_attr($index) . '][correct]" value="' . absint($j) . '" ' . checked((int)($question['correct'] ?? 0), $j, false) . ' aria-label="' . esc_attr('Alternativa correta ' . ($j + 1)) . '"><input name="nexo_questions[' . esc_attr($index) . '][options][' . absint($j) . ']" value="' . esc_attr($option) . '" placeholder="Alternativa ' . ($j + 1) . '"></label>'; }
        echo '</div><small>Marque o círculo da alternativa correta. Preencha pelo menos duas alternativas.</small><button type="button" class="button-link-delete nexo-remove-question">Remover pergunta</button></fieldset>';
    }
    public static function save_meta(int $id, \WP_Post $post): void {
        if (!in_array($post->post_type, self::TYPES, true) || !current_user_can('manage_options') || !current_user_can('edit_post', $id) || wp_is_post_revision($id) || wp_is_post_autosave($id) || empty($_POST['nexo_meta_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nexo_meta_nonce'])), 'nexo_meta')) { return; }
        if ($post->post_type === 'nexo_course') {
            if (isset($_POST['nexo_enrollment_mode'])) {
                $enrollment = sanitize_key($_POST['nexo_enrollment_mode']);
                update_post_meta($id, '_nexo_enrollment_mode', in_array($enrollment,['free','paid','manual'],true) ? $enrollment : 'manual');
                $raw_price = sanitize_text_field(wp_unslash($_POST['nexo_price'] ?? ''));
                $price = function_exists('wc_format_decimal') ? wc_format_decimal($raw_price) : str_replace(',', '.', $raw_price);
                update_post_meta($id, '_nexo_price', is_numeric($price) && (float)$price > 0 ? (string)$price : '');
            }
            update_post_meta($id, '_nexo_mode', ($_POST['nexo_mode'] ?? '') === 'erp' ? 'erp' : 'online');
            $product_id = absint($_POST['nexo_product_id'] ?? 0);
            if ($product_id && (!function_exists('wc_get_product') || !wc_get_product($product_id))) { $product_id = 0; }
            update_post_meta($id, '_nexo_product_id', $product_id); update_post_meta($id, '_nexo_capacity', absint($_POST['nexo_capacity'] ?? 0));
            foreach (['location', 'workload'] as $key) { update_post_meta($id, '_nexo_' . $key, sanitize_text_field(wp_unslash($_POST['nexo_' . $key] ?? ''))); }
            $date = sanitize_text_field(wp_unslash($_POST['nexo_start_date'] ?? ''));
            if ($date && (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $parts) || !checkdate((int)$parts[2], (int)$parts[3], (int)$parts[1]))) { $date = ''; }
            update_post_meta($id, '_nexo_start_date', $date); $managers = [];
            foreach (array_slice((array)($_POST['nexo_manager_ids'] ?? []), 0, 500) as $user_id) {
                $user = get_user_by('id', absint($user_id));
                if ($user && (user_can($user, 'manage_options') || user_can($user, 'nexo_manage_assigned'))) { $managers[] = (int)$user->ID; }
            }
            update_post_meta($id, '_nexo_manager_ids', array_values(array_unique($managers))); return;
        }
        $course = absint($_POST['nexo_course_id'] ?? 0); if (get_post_type($course) !== 'nexo_course') { $course = 0; }
        update_post_meta($id, '_nexo_course_id', $course); $topic = absint($_POST['nexo_topic_id'] ?? 0);
        if (get_post_type($topic) !== 'nexo_topic' || (int)get_post_meta($topic, '_nexo_course_id', true) !== $course) { $topic = 0; }
        update_post_meta($id, '_nexo_topic_id', $topic);
        if (isset($_POST['nexo_menu_order'])) {
            remove_action('save_post', [self::class, 'save_meta'], 10); wp_update_post(['ID' => $id, 'menu_order' => (int)$_POST['nexo_menu_order']]); add_action('save_post', [self::class, 'save_meta'], 10, 2);
        }
        if ($post->post_type === 'nexo_lesson') { update_post_meta($id, '_nexo_video_url', esc_url_raw(wp_unslash($_POST['nexo_video_url'] ?? ''), ['https', 'http'])); }
        if ($post->post_type === 'nexo_quiz') {
            $questions = [];
            foreach (array_slice((array)($_POST['nexo_questions'] ?? []), 0, 100) as $raw) {
                if (!is_array($raw)) { continue; }
                $text = sanitize_text_field(wp_unslash($raw['question'] ?? '')); $options = []; $correct = -1;
                foreach (array_slice((array)($raw['options'] ?? []), 0, 6, true) as $key => $value) {
                    $option = sanitize_text_field(wp_unslash($value)); if ($option === '') { continue; }
                    if ((int)$key === (int)($raw['correct'] ?? -1)) { $correct = count($options); } $options[] = $option;
                }
                if ($text !== '' && count($options) >= 2 && $correct >= 0) { $questions[] = ['question' => $text, 'options' => $options, 'correct' => $correct]; }
            }
            update_post_meta($id, '_nexo_questions', $questions); update_post_meta($id, '_nexo_pass_score', max(0, min(100, (int)($_POST['nexo_pass_score'] ?? 70))));
        }
    }
    public static function course_columns(array $columns): array { $columns['nexo_mode'] = 'Formato'; $columns['nexo_product'] = 'Produto'; return $columns; }
    public static function course_column(string $column, int $id): void {
        if ($column === 'nexo_mode') { echo get_post_meta($id, '_nexo_mode', true) === 'erp' ? 'Gestão / presencial' : 'EAD'; }
        if ($column === 'nexo_product') { $product = absint(get_post_meta($id, '_nexo_product_id', true)); echo $product ? '<a href="' . esc_url(get_edit_post_link($product)) . '">#' . $product . '</a>' : '—'; }
    }
    public static function brand_page(): void {
        if (!current_user_can('manage_options')) { wp_die(esc_html__('Acesso restrito.', 'nexo-cursos')); }
        $settings = \nexo_settings(); self::shell_start('brand');
        echo '<div class="nexo-topline"><span>Seu espaço de gestão <b>/ Identidade visual</b></span></div><header class="nexo-page-header"><div><span class="nexo-eyebrow">DO SEU JEITO</span><h1>Sua marca.<br><span>Uma experiência única.</span></h1><p>Personalize a plataforma e a entrada dos seus alunos.</p></div></header>'; self::notice();
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="nexo-panel nexo-brand-form"><input type="hidden" name="action" value="nexo_save_brand">'; wp_nonce_field('nexo_save_brand');
        echo '<div class="nexo-meta-grid"><label class="nexo-field-wide">Nome da marca<input name="brand_name" maxlength="80" required value="' . esc_attr($settings['brand_name']) . '"></label><label>Cor principal<input type="color" name="primary_color" value="' . esc_attr($settings['primary_color']) . '"></label><label>Cor de destaque<input type="color" name="accent_color" value="' . esc_attr($settings['accent_color']) . '"></label><div class="nexo-field-wide"><label>Logo da plataforma</label><div id="nexo-logo-preview">' . (!empty($settings['logo_id']) ? wp_get_attachment_image((int)$settings['logo_id'], 'medium') : '<span>Nenhum logo selecionado</span>') . '</div><input id="nexo-logo-id" name="logo_id" type="hidden" value="' . absint($settings['logo_id']) . '"><button type="button" class="button" id="nexo-select-logo">Selecionar logo</button> <button type="button" class="button" id="nexo-remove-logo">Remover</button><p class="nexo-footnote">Prefira PNG transparente ou WebP. A imagem é carregada pela biblioteca do WordPress.</p></div><label class="nexo-field-wide">Título da tela de login<input name="login_title" maxlength="120" value="' . esc_attr($settings['login_title']) . '"></label><label class="nexo-field-wide">Mensagem de boas-vindas<textarea name="login_message" rows="3" maxlength="400">' . esc_textarea($settings['login_message']) . '</textarea></label></div><p>As cores e o logo se aplicam ao painel, catálogo, área do aluno e tela de login. O tema e os meios de pagamento continuam usando suas configurações próprias.</p><button class="nexo-button">Salvar identidade</button></form>';
        echo '<section class="nexo-panel nexo-brand-form" style="margin-top:20px"><h2>Páginas da plataforma</h2><p>Crie novamente páginas ausentes e confira os links da loja.</p><ul>';
        foreach (['products' => 'Produtos da loja', 'catalog' => 'Catálogo de cursos', 'cart' => 'Carrinho', 'checkout' => 'Checkout', 'account' => 'Meus cursos', 'login' => 'Login', 'dashboard' => 'Painel de gestão'] as $key => $label) { echo '<li><a href="' . esc_url(\nexo_page_url($key)) . '">' . esc_html($label) . ' ↗</a></li>'; }
        echo '</ul><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="nexo_repair_pages">'; wp_nonce_field('nexo_repair_pages'); echo '<button class="nexo-button nexo-button-secondary">Verificar e criar páginas ausentes</button></form></section>'; self::shell_end();
    }
    public static function save_brand(): void {
        if (!current_user_can('manage_options')) { wp_die(esc_html__('Acesso restrito.', 'nexo-cursos'), '', ['response' => 403]); } check_admin_referer('nexo_save_brand');
        $old = \nexo_settings(); $logo = absint($_POST['logo_id'] ?? 0); if ($logo && !wp_attachment_is_image($logo)) { $logo = 0; }
        $settings = [
            'brand_name' => wp_html_excerpt(sanitize_text_field(wp_unslash($_POST['brand_name'] ?? '')), 80, '') ?: $old['brand_name'],
            'primary_color' => sanitize_hex_color(wp_unslash($_POST['primary_color'] ?? '')) ?: $old['primary_color'],
            'accent_color' => sanitize_hex_color(wp_unslash($_POST['accent_color'] ?? '')) ?: $old['accent_color'],
            'logo_id' => $logo,
            'login_title' => wp_html_excerpt(sanitize_text_field(wp_unslash($_POST['login_title'] ?? '')), 120, ''),
            'login_message' => wp_html_excerpt(sanitize_textarea_field(wp_unslash($_POST['login_message'] ?? '')), 400, ''),
        ];
        update_option('nexo_settings', $settings, false); wp_safe_redirect(add_query_arg(['page' => 'nexo-identidade', 'nexo_notice' => 'brand'], admin_url('admin.php'))); exit;
    }
}
