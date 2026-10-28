<?php
/** Public course screens and protected learning actions. */
namespace NexoCursos;
defined( 'ABSPATH' ) || exit;

final class Frontend {
    private static bool $rendering = false;

    public static function boot(): void {
        add_action( 'wp_enqueue_scripts', array( self::class, 'assets' ) );
        add_action( 'template_redirect', array( self::class, 'privacy_headers' ) );
        add_filter( 'the_content', array( self::class, 'single_content' ), 20 );
        add_action( 'admin_post_nexo_complete_lesson', array( self::class, 'complete_lesson' ) );
        add_action( 'admin_post_nexo_submit_quiz', array( self::class, 'submit_quiz' ) );
        add_action( 'admin_post_nopriv_nexo_complete_lesson', array( self::class, 'require_login' ) );
        add_action( 'admin_post_nopriv_nexo_submit_quiz', array( self::class, 'require_login' ) );
        add_action( 'login_enqueue_scripts', array( self::class, 'login_assets' ) );
        add_filter( 'login_headerurl', static fn() => home_url( '/' ) );
        add_filter( 'login_headertext', static fn() => nexo_settings()['brand_name'] );
        add_filter( 'login_message', array( self::class, 'login_message' ) );
        foreach ( array( 'nexo_available_courses' => 'available_courses', 'nexo_catalog' => 'catalog', 'nexo_my_courses' => 'my_courses', 'nexo_course' => 'course', 'nexo_login' => 'login', 'nexo_manager_dashboard' => 'dashboard' ) as $tag => $method ) { add_shortcode( $tag, array( self::class, $method ) ); }
    }

    public static function privacy_headers(): void {
        $post = get_post();
        $private = is_singular( 'nexo_course' );
        if (is_user_logged_in() && current_theme_supports('nexo-navigation')) { $private = true; }
        if ( $post instanceof \WP_Post ) {
            foreach ( array( 'nexo_available_courses', 'nexo_navigation', 'nexo_my_courses', 'nexo_login', 'nexo_manager_dashboard', 'nexo_course' ) as $tag ) { $private = $private || has_shortcode( $post->post_content, $tag ); }
        }
        if ( $private ) {
            if ( ! defined( 'DONOTCACHEPAGE' ) ) { define( 'DONOTCACHEPAGE', true ); }
            nocache_headers();
        }
    }

    private static function ordered_curriculum( array $items ): array {
        $topics = array(); $children = array(); $standalone = array();
        foreach ( $items as $item ) { if ( $item->post_type === 'nexo_topic' ) { $topics[ $item->ID ] = $item; } }
        foreach ( $items as $item ) {
            if ( $item->post_type === 'nexo_topic' ) { $standalone[] = $item; continue; }
            $topic = (int) get_post_meta( $item->ID, '_nexo_topic_id', true );
            if ( $topic && isset( $topics[ $topic ] ) ) { $children[ $topic ][] = $item; }
            else { $standalone[] = $item; }
        }
        $ordered = array();
        foreach ( $standalone as $item ) {
            $ordered[] = $item;
            if ( $item->post_type === 'nexo_topic' && isset( $children[ $item->ID ] ) ) { foreach ( $children[ $item->ID ] as $child ) { $ordered[] = $child; } }
        }
        return $ordered;
    }

    public static function assets(): void {
        $post = get_post(); $needed = is_singular( 'nexo_course' ) || current_theme_supports('nexo-navigation');
        if ( $post instanceof \WP_Post ) {
            foreach ( array( 'nexo_available_courses', 'nexo_navigation', 'nexo_catalog', 'nexo_my_courses', 'nexo_course', 'nexo_login', 'nexo_manager_dashboard' ) as $tag ) { $needed = $needed || has_shortcode( $post->post_content, $tag ); }
        }
        $needed = $needed || ( function_exists( 'is_woocommerce' ) && ( is_woocommerce() || is_cart() || is_checkout() || is_account_page() ) );
        if ( ! $needed ) { return; }
        wp_enqueue_style( 'nexo-frontend', NEXO_URL . 'assets/frontend.css', array(), NEXO_VERSION );
        wp_add_inline_style( 'nexo-frontend', ':root{' . nexo_brand_styles() . '}' );
        wp_enqueue_script( 'nexo-frontend', NEXO_URL . 'assets/frontend.js', array(), NEXO_VERSION, true );
    }

    public static function single_content( string $content ): string {
        if ( ! self::$rendering && is_singular( 'nexo_course' ) && in_the_loop() && is_main_query() ) { return self::course( array( 'id' => get_the_ID() ) ); }
        return $content;
    }

    private static function icon( string $name ): string {
        $paths = array(
            'book' => '<path d="M4 5h6a3 3 0 0 1 3 3v12a4 4 0 0 0-4-2H4z"/><path d="M20 5h-4a3 3 0 0 0-3 3v12a4 4 0 0 1 4-2h3z"/>',
            'arrow' => '<path d="M5 12h14m-6-6 6 6-6 6"/>',
            'play' => '<path d="m9 5 11 7-11 7z"/>',
            'check' => '<path d="m5 12 4 4L19 6"/>',
            'grid' => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>',
            'cart' => '<path d="M2 3h3l3 12h11l3-8H6"/><circle cx="10" cy="20" r="1"/><circle cx="18" cy="20" r="1"/>',
            'search' => '<circle cx="10" cy="10" r="6"/><path d="m15 15 6 6"/>',
            'users' => '<circle cx="9" cy="7" r="4"/><path d="M2 22v-3a7 7 0 0 1 14 0v3M17 4a4 4 0 0 1 0 8m3 10v-3a7 7 0 0 0-3-6"/>',
            'calendar' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M7 2v6m10-6v6M3 11h18"/>',
            'test' => '<path d="M8 4H5v18h14V4h-3M9 2h6v5H9zM8 12h8M8 16h5"/>',
            'location' => '<path d="M20 10c0 6-8 12-8 12S4 16 4 10a8 8 0 1 1 16 0z"/><circle cx="12" cy="10" r="3"/>',
        );
        return '<svg class="nexo-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ( $paths[ $name ] ?? $paths['book'] ) . '</svg>';
    }

    private static function shell_open( string $active = 'catalog' ): string {
        if (current_theme_supports('nexo-navigation')) { return '<div class="nexo-app" style="'.esc_attr(nexo_brand_styles()).'"><div class="nexo-main">'; }
        $settings = nexo_settings();
        $logo = ! empty( $settings['logo_id'] ) ? wp_get_attachment_image( absint( $settings['logo_id'] ), 'medium', false, array( 'class' => 'nexo-brand-logo', 'alt' => $settings['brand_name'] ) ) : '';
        $out = '<div class="nexo-app" style="' . esc_attr( nexo_brand_styles() ) . '"><header class="nexo-header"><a class="nexo-brand" href="' . esc_url( nexo_page_url( 'catalog' ) ) . '">' . ( $logo ?: '<span class="nexo-brand-mark">' . self::icon( 'book' ) . '</span><span>' . esc_html( $settings['brand_name'] ) . '</span>' ) . '</a><nav aria-label="Navegação dos cursos" class="nexo-nav">';
        $links = array( 'catalog' => array( 'Explorar cursos', nexo_page_url( 'catalog' ) ), 'account' => array( 'Meus cursos', nexo_page_url( 'account' ) ) );
        if ( is_user_logged_in() && ( current_user_can( 'manage_options' ) || Core::course_ids_for_manager() ) ) { $links['dashboard'] = array( 'Painel do gerente', nexo_page_url( 'dashboard' ) ); }
        foreach ( $links as $key => $link ) { $out .= '<a' . ( $active === $key ? ' class="is-active" aria-current="page"' : '' ) . ' href="' . esc_url( $link[1] ) . '">' . esc_html( $link[0] ) . '</a>'; }
        $out .= '</nav><div class="nexo-header-actions">';
        if ( function_exists( 'wc_get_cart_url' ) ) { $out .= '<a class="nexo-cart-link" href="' . esc_url( wc_get_cart_url() ) . '" aria-label="Ver carrinho">' . self::icon( 'cart' ) . '</a>'; }
        if ( is_user_logged_in() ) {
            $user = wp_get_current_user();
            $out .= '<a class="nexo-avatar" href="' . esc_url( nexo_page_url( 'account' ) ) . '" title="' . esc_attr( $user->display_name ) . '">' . esc_html( function_exists( 'mb_substr' ) ? mb_substr( $user->display_name, 0, 1 ) : substr( $user->display_name, 0, 1 ) ) . '</a>';
        } else { $out .= '<a class="nexo-button nexo-button-small" href="' . esc_url( nexo_page_url( 'login' ) ) . '">Entrar ' . self::icon( 'arrow' ) . '</a>'; }
        return $out . '</div></header><main class="nexo-main">';
    }

    private static function shell_close(): string {
        if (current_theme_supports('nexo-navigation')) { return '</div></div>'; }
        return '</main><footer class="nexo-footer"><span>' . esc_html( nexo_settings()['brand_name'] ) . ' · Aprender abre caminhos.</span><a href="' . esc_url( nexo_page_url( 'catalog' ) ) . '">Explorar cursos ' . self::icon( 'arrow' ) . '</a></footer></div>';
    }

    private static function empty_state( string $title, string $text, string $link = '', string $label = '' ): string {
        return '<div class="nexo-empty"><span class="nexo-empty-icon">' . self::icon( 'book' ) . '</span><h2>' . esc_html( $title ) . '</h2><p>' . esc_html( $text ) . '</p>' . ( $link ? '<a class="nexo-button" href="' . esc_url( $link ) . '">' . esc_html( $label ) . ' ' . self::icon( 'arrow' ) . '</a>' : '' ) . '</div>';
    }

    public static function available_courses($atts = []): string {
        $atts = shortcode_atts(['limit'=>3],$atts,'nexo_available_courses');
        $courses = get_posts(['post_type'=>'nexo_course','post_status'=>'publish','numberposts'=>min(12,max(1,absint($atts['limit']))),'orderby'=>'date','order'=>'DESC','has_password'=>false]);
        $out = '<div class="nexo-app nexo-available-courses" style="'.esc_attr(nexo_brand_styles()).'">';
        if (!$courses) { return $out.self::empty_state('Novas jornadas estão sendo preparadas','Em breve, você encontrará aqui os cursos disponíveis para sua formação.').'</div>'; }
        $out .= '<div class="nexo-course-grid">';
        foreach ($courses as $course) { $out .= self::course_card($course, is_user_logged_in() && Core::has_access((int)$course->ID)); }
        return $out.'</div></div>';
    }

    public static function catalog( $atts = array() ): string {
        $search = isset( $_GET['nexo_search'] ) && is_string( $_GET['nexo_search'] ) ? sanitize_text_field( wp_unslash( $_GET['nexo_search'] ) ) : '';
        $mode = isset( $_GET['nexo_mode'] ) && is_string( $_GET['nexo_mode'] ) ? sanitize_key( $_GET['nexo_mode'] ) : '';
        $page = max( 1, absint( $_GET['nexo_paged'] ?? 1 ) );
        $args = array( 'post_type' => 'nexo_course', 'post_status' => 'publish', 'posts_per_page' => 9, 'has_password' => false, 'paged' => $page, 's' => $search, 'orderby' => 'date', 'order' => 'DESC' );
        if ( in_array( $mode, array( 'online', 'erp' ), true ) ) { $args['meta_query'] = array( array( 'key' => '_nexo_mode', 'value' => $mode ) ); }
        $query = new \WP_Query( $args );
        $out = self::shell_open() . '<section class="nexo-catalog-hero"><div><span class="nexo-eyebrow">CONHECIMENTO QUE TRANSFORMA</span><h1>Seu próximo passo<br>começa aqui<span>.</span></h1><p>Encontre seu curso. Desenvolva novas habilidades.<br>Construa o caminho que você quer seguir.</p><a class="nexo-text-link" href="#nexo-catalog-list">Conheça os cursos ' . self::icon( 'arrow' ) . '</a></div><div class="nexo-hero-art" aria-hidden="true"><span class="nexo-orbit nexo-orbit-one"></span><span class="nexo-orbit nexo-orbit-two"></span><div class="nexo-hero-book">' . self::icon( 'book' ) . '</div><span class="nexo-art-label">Novos caminhos,<br><strong>novas possibilidades.</strong></span><span class="nexo-art-spark">✦</span></div></section>';
        $out .= '<section id="nexo-catalog-list" class="nexo-section"><div class="nexo-section-heading"><div><span class="nexo-eyebrow">EXPLORE POSSIBILIDADES</span><h2>Encontre o seu curso</h2></div><span class="nexo-result-count">' . esc_html( sprintf( _n( '%d curso disponível', '%d cursos disponíveis', (int) $query->found_posts, 'nexo-cursos' ), $query->found_posts ) ) . '</span></div>';
        $out .= '<form class="nexo-filter-bar" method="get" action="' . esc_url( nexo_page_url( 'catalog' ) ) . '"><label class="nexo-search">' . self::icon( 'search' ) . '<span class="nexo-sr-only">Buscar cursos</span><input type="search" name="nexo_search" placeholder="O que você quer aprender?" value="' . esc_attr( $search ) . '"></label><label class="nexo-select-label"><span class="nexo-sr-only">Formato do curso</span><select name="nexo_mode"><option value="">Todos os formatos</option><option value="online"' . selected( $mode, 'online', false ) . '>Cursos on-line</option><option value="erp"' . selected( $mode, 'erp', false ) . '>Cursos presenciais / gestão</option></select></label><button type="submit" class="nexo-button">Buscar ' . self::icon( 'arrow' ) . '</button></form>';
        if ( $query->posts ) {
            $out .= '<div class="nexo-course-grid">';
            foreach ( $query->posts as $course ) { $out .= self::course_card( $course ); }
            $out .= '</div>';
            if ( $query->max_num_pages > 1 ) {
                $base = add_query_arg( array( 'nexo_search' => $search, 'nexo_mode' => $mode, 'nexo_paged' => '%#%' ), nexo_page_url( 'catalog' ) );
                $out .= '<nav class="nexo-pagination" aria-label="Páginas do catálogo">' . wp_kses_post( paginate_links( array( 'base' => str_replace( '%25%23%25', '%#%', $base ), 'format' => '', 'current' => $page, 'total' => $query->max_num_pages, 'prev_text' => '← Anterior', 'next_text' => 'Próxima →' ) ) ) . '</nav>';
            }
        } else { $out .= self::empty_state( 'Nenhum curso encontrado', $search || $mode ? 'Tente outra busca ou escolha um formato diferente.' : 'Os cursos publicados aparecerão aqui assim que estiverem disponíveis.' ); }
        return $out . '</section>' . self::shell_close();
    }

    private static function course_card( \WP_Post $course, bool $enrolled = false ): string {
        $cover_target = !$enrolled && Commerce::is_free((int)$course->ID) ? Commerce::entry_url((int)$course->ID) : get_permalink($course);
        $mode = get_post_meta( $course->ID, '_nexo_mode', true ) === 'erp' ? 'erp' : 'online';
        $product = function_exists( 'wc_get_product' ) ? wc_get_product( absint( get_post_meta( $course->ID, '_nexo_product_id', true ) ) ) : false;
        $duration = get_post_meta( $course->ID, '_nexo_workload', true );
        $description = $course->post_excerpt ?: wp_trim_words( wp_strip_all_tags( strip_shortcodes( $course->post_content ) ), 19 );
        $image = get_the_post_thumbnail( $course->ID, 'medium_large', array( 'loading' => 'lazy', 'class' => 'nexo-course-image', 'alt' => '' ) );
        $art = $image ?: '<div class="nexo-course-art nexo-course-art-' . ( ( $course->ID % 4 ) + 1 ) . '" aria-hidden="true"><span class="nexo-course-art-orbit"></span>' . self::icon( $mode === 'erp' ? 'users' : 'book' ) . '<span class="nexo-course-art-caption">' . esc_html(current_theme_supports('nexo-navigation') ? 'Crescer para servir.' : ($mode === 'erp' ? 'Conecte-se. Evolua.' : 'Conhecimento em movimento.')) . '</span></div>';
        $out = '<article class="nexo-course-card"><a class="nexo-course-cover" href="' . esc_url( $cover_target ) . '" aria-label="' . esc_attr( 'Ver curso: ' . $course->post_title ) . '">' . $art . '<span class="nexo-cover-badge">' . esc_html( $mode === 'erp' ? 'Presencial / gestão' : 'On-line' ) . '</span></a><div class="nexo-card-body"><div class="nexo-card-meta"><span>' . self::icon( $mode === 'erp' ? 'users' : 'play' ) . esc_html( $mode === 'erp' ? 'Experiência presencial' : 'Aprenda no seu ritmo' ) . '</span>' . ( $duration ? '<span>' . esc_html( $duration ) . '</span>' : '' ) . '</div><h3><a href="' . esc_url( $cover_target ) . '">' . esc_html( $course->post_title ) . '</a></h3><p>' . esc_html( $description ) . '</p>';
        if ( $enrolled && $mode === 'online' ) {
            $progress = Core::progress( $course->ID ); $percent = min( 100, max( 0, (int) ( $progress['percent'] ?? 0 ) ) );
            $out .= '<div class="nexo-progress-label"><span>Seu progresso</span><strong>' . $percent . '%</strong></div><progress class="nexo-progress" max="100" value="' . $percent . '">' . $percent . '%</progress>';
        }
        $out .= '<div class="nexo-card-bottom">';
        if ( $enrolled ) { $out .= '<span class="nexo-status nexo-status-active">' . self::icon( 'check' ) . ' Matriculado</span><a class="nexo-card-cta" href="' . esc_url( $cover_target ) . '">' . esc_html( $mode === 'erp' ? 'Ver detalhes' : 'Acessar curso' ) . ' ' . self::icon( 'arrow' ) . '</a>'; }
        else {
            $free = Commerce::is_free((int)$course->ID);
            $label = $free ? 'Gratuito' : ($product && Commerce::pricing_mode((int)$course->ID)==='paid' ? wp_kses_post($product->get_price_html()) : '<small>Consulte disponibilidade</small>');
            $target = $free ? Commerce::entry_url((int)$course->ID) : get_permalink($course);
            $out .= '<span class="nexo-price">'.$label.'</span><a class="nexo-card-cta" href="'.esc_url($target).'">'.($free?'Inscreva-se grátis':'Conhecer curso').' '.self::icon('arrow').'</a>';
        }
        return $out . '</div></div></article>';
    }

    public static function my_courses( $atts = array() ): string {
        $out = self::shell_open( 'account' );
        if ( ! is_user_logged_in() ) { return $out . self::empty_state( 'Seu aprendizado tem um lugar', 'Entre na sua conta para acessar suas matrículas e acompanhar seu progresso.', nexo_page_url( 'login' ), 'Entrar na minha conta' ) . self::shell_close(); }
        $user = wp_get_current_user();
        $out .= '<section class="nexo-page-heading"><span class="nexo-eyebrow">SEU ESPAÇO DE APRENDIZADO</span><h1>Olá, ' . esc_html( $user->first_name ?: $user->display_name ) . '<span>.</span></h1><p>Continue de onde parou. Cada passo faz a diferença.</p><a class="nexo-subtle-link" href="' . esc_url( wp_logout_url( nexo_page_url( 'login' ) ) ) . '">Sair da conta</a></section>';
        $ids = array();
        foreach ( Core::enrollments() as $row ) { if ( (int) $row->user_id === get_current_user_id() && $row->status === 'active' ) { $ids[] = (int) $row->course_id; } }
        $ids = array_values( array_unique( $ids ) );
        $courses = $ids ? get_posts( array( 'post_type' => 'nexo_course', 'post_status' => 'publish', 'post__in' => $ids, 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC' ) ) : array();
        if ( $courses ) {
            $out .= '<section class="nexo-section"><div class="nexo-section-heading"><h2>Meus cursos</h2><span class="nexo-result-count">' . count( $courses ) . ' matrícula(s)</span></div><div class="nexo-course-grid">';
            foreach ( $courses as $course ) { if ( Core::has_access( $course->ID ) ) { $out .= self::course_card( $course, true ); } }
            $out .= '</div></section>';
        } else { $out .= self::empty_state( 'Seu próximo curso está esperando', 'Seus cursos gratuitos e pagos ficarão disponíveis aqui após a matrícula. Matrículas presenciais também aparecem neste espaço.', nexo_page_url( 'catalog' ), 'Explorar cursos' ); }
        return $out . self::shell_close();
    }

    private static function render_content( string $content ): string {
        $previous = self::$rendering; self::$rendering = true;
        $html = apply_filters( 'the_content', $content ); self::$rendering = $previous;
        return wp_kses_post( $html );
    }

    public static function course( $atts = array() ): string {
        $atts = shortcode_atts( array( 'id' => 0 ), $atts, 'nexo_course' );
        $id = absint( $atts['id'] ?: ( $_GET['nexo_course'] ?? ( is_singular( 'nexo_course' ) ? get_the_ID() : 0 ) ) );
        $course = get_post( $id ); $out = self::shell_open();
        if ( ! $course || $course->post_type !== 'nexo_course' || ( $course->post_status !== 'publish' && ! Core::can_manage( $id ) ) ) { return $out . self::empty_state( 'Curso não encontrado', 'Este curso não está disponível no momento.', nexo_page_url( 'catalog' ), 'Voltar ao catálogo' ) . self::shell_close(); }
        if (post_password_required($course) && !Core::can_manage($id)) { return $out.get_the_password_form($course).self::shell_close(); }
        $access = is_user_logged_in() && Core::has_access( $id ); $mode = get_post_meta( $id, '_nexo_mode', true ) === 'erp' ? 'erp' : 'online'; $items = self::ordered_curriculum( Core::curriculum( $id ) );
        $out .= '<a class="nexo-back" href="' . esc_url( nexo_page_url( $access ? 'account' : 'catalog' ) ) . '">← ' . esc_html( $access ? 'Meus cursos' : 'Todos os cursos' ) . '</a><section class="nexo-course-heading"><div><span class="nexo-eyebrow">' . esc_html( $mode === 'erp' ? 'CURSO PRESENCIAL / GESTÃO' : 'SEU PRÓXIMO PASSO' ) . '</span><h1>' . esc_html( $course->post_title ) . '</h1>' . ( $course->post_excerpt ? '<p>' . esc_html( $course->post_excerpt ) . '</p>' : '' ) . '</div>' . ( $access ? '<span class="nexo-status nexo-status-active">' . self::icon( 'check' ) . ' Matrícula ativa</span>' : '' ) . '</section>' . self::feedback();
        if ( ! $access || $mode === 'erp' ) {
            $out .= '<div class="nexo-overview-layout"><article class="nexo-panel nexo-course-description"><h2>Sobre o curso</h2><div class="nexo-prose">' . self::render_content( $course->post_content ) . '</div>';
            if ( ! $course->post_content ) { $out .= '<p class="nexo-muted">As informações deste curso serão publicadas em breve.</p>'; }
            if ( $mode === 'online' && $items ) { $out .= '<h2 class="nexo-space-top">O que você vai encontrar</h2>' . self::public_curriculum( $items ); }
            if ( $access && $mode === 'erp' ) { $out .= self::attendance_panel( $id ); }
            $out .= '</article>' . self::enrollment_panel( $course, $access, $mode ) . '</div>';
        } else { $out .= self::learning( $course, $items ); }
        return $out . self::shell_close();
    }

    private static function public_curriculum( array $items ): string {
        $out = '<ul class="nexo-public-curriculum">';
        foreach ( $items as $item ) {
            $type = $item->post_type === 'nexo_topic' ? 'Tópico' : ( $item->post_type === 'nexo_quiz' ? 'Teste' : 'Aula' );
            $out .= '<li class="' . ( $item->post_type === 'nexo_topic' ? 'nexo-public-topic' : '' ) . '">' . self::icon( $item->post_type === 'nexo_quiz' ? 'test' : ( $item->post_type === 'nexo_topic' ? 'book' : 'play' ) ) . '<span>' . esc_html( $item->post_title ) . '</span><small>' . esc_html( $type ) . '</small></li>';
        }
        return $out . '</ul>';
    }

    private static function enrollment_panel( \WP_Post $course, bool $access, string $mode ): string {
        $id = $course->ID;
        $out = '<aside class="nexo-panel nexo-enrollment-panel">' . get_the_post_thumbnail( $id, 'medium_large', array( 'class' => 'nexo-overview-image', 'alt' => '' ) );
        $out .= '<span class="nexo-eyebrow">' . esc_html( $access ? 'INFORMAÇÕES DA MATRÍCULA' : 'INVISTA NO SEU PRÓXIMO PASSO' ) . '</span>';
        $product = function_exists( 'wc_get_product' ) ? wc_get_product( absint( get_post_meta( $id, '_nexo_product_id', true ) ) ) : false;
        if (!$access && Commerce::is_free((int)$id)) {
            $out .= '<div class="nexo-detail-price">Gratuito</div><p class="nexo-muted">Uma oportunidade para aprender, crescer e servir.</p>';
            $out .= is_user_logged_in() ? Commerce::join_form((int)$id) : '<a class="nexo-button nexo-button-wide" href="'.esc_url(Commerce::entry_url((int)$id)).'">Entrar ou cadastrar para participar →</a>';
        }
        elseif ( ! $access && $product && $product->get_status() === 'publish' && Commerce::pricing_mode((int)$id)==='paid' ) { $out .= '<div class="nexo-detail-price">' . wp_kses_post( $product->get_price_html() ) . '</div><a class="nexo-button nexo-button-wide" href="' . esc_url( $product->get_permalink() ) . '">Ver produto e comprar ' . self::icon( 'arrow' ) . '</a><p class="nexo-small nexo-muted">A matrícula é liberada após a confirmação do pagamento. Use sua conta na compra para acessar o curso.</p>'; }
        elseif ( ! $access ) { $out .= '<h3>Inscrições e informações</h3><p class="nexo-muted">Consulte a equipe responsável para saber como participar deste curso.</p>'; }
        else { $out .= '<h3>Você já faz parte.</h3><p class="nexo-muted">Acompanhe aqui as informações do seu curso. Para alterações na matrícula, procure o gerente responsável.</p>'; }
        $out .= '<dl class="nexo-course-facts">';
        $facts = array( array( 'book', 'Formato', $mode === 'erp' ? 'Presencial / gestão' : 'On-line' ) );
        $duration = get_post_meta( $id, '_nexo_workload', true ); $date = get_post_meta( $id, '_nexo_start_date', true ); $location = get_post_meta( $id, '_nexo_location', true );
        if ( $duration ) { $facts[] = array( 'calendar', 'Carga horária', $duration ); }
        if ( $date ) { $facts[] = array( 'calendar', 'Início', self::date_label( $date ) ); }
        if ( $location ) { $facts[] = array( 'location', 'Local', $location ); }
        foreach ( $facts as $fact ) { $out .= '<div><dt>' . self::icon( $fact[0] ) . esc_html( $fact[1] ) . '</dt><dd>' . esc_html( $fact[2] ) . '</dd></div>'; }
        $out .= '</dl>';
        if ( ! is_user_logged_in() ) { $out .= '<p class="nexo-small">Já é aluno? <a href="' . esc_url( add_query_arg( 'redirect_to', get_permalink( $id ), nexo_page_url( 'login' ) ) ) . '">Entre na sua conta</a>.</p>'; }
        return $out . '</aside>';
    }

    private static function date_label( string $value ): string {
        $date = \DateTimeImmutable::createFromFormat( '!Y-m-d', $value, wp_timezone() );
        return $date ? wp_date( 'd/m/Y', $date->getTimestamp(), wp_timezone() ) : $value;
    }

    private static function attendance_panel( int $id ): string {
        $attendance = Core::attendance( $id, get_current_user_id() ); $out = '<h2 class="nexo-space-top">Minha presença</h2>';
        if ( ! $attendance ) { return $out . '<p class="nexo-muted">As presenças registradas pelo gerente aparecerão aqui.</p>'; }
        krsort( $attendance ); $labels = array( 'present' => 'Presente', 'absent' => 'Ausente', 'excused' => 'Falta justificada' );
        $out .= '<div class="nexo-table-wrap"><table class="nexo-table"><thead><tr><th scope="col">Data</th><th scope="col">Registro</th></tr></thead><tbody>';
        foreach ( $attendance as $date => $status ) { $out .= '<tr><td>' . esc_html( self::date_label( (string) $date ) ) . '</td><td><span class="nexo-status ' . ( $status === 'present' ? 'nexo-status-active' : '' ) . '">' . esc_html( $labels[ $status ] ?? $status ) . '</span></td></tr>'; }
        return $out . '</tbody></table></div>';
    }

    private static function learning( \WP_Post $course, array $items ): string {
        $progress = Core::progress( $course->ID ); $completed = array_map( 'intval', (array) ( $progress['completed'] ?? array() ) );
        $requested = absint( $_GET['nexo_item'] ?? 0 ); $selected = null;
        foreach ( $items as $item ) { if ( $item->ID === $requested ) { $selected = $item; break; } }
        $out = '<div class="nexo-learning-layout" id="nexo-learning"><aside class="nexo-panel nexo-curriculum"><div class="nexo-curriculum-header"><h2>Seu caminho</h2><span>' . esc_html( (int) ( $progress['percent'] ?? 0 ) ) . '% concluído</span><progress class="nexo-progress" max="100" value="' . esc_attr( min( 100, max( 0, (int) ( $progress['percent'] ?? 0 ) ) ) ) . '">' . esc_html( (int) ( $progress['percent'] ?? 0 ) ) . '%</progress></div><nav aria-label="Conteúdo do curso"><a class="nexo-curriculum-item' . ( ! $selected ? ' is-selected' : '' ) . '" href="' . esc_url( get_permalink( $course ) ) . '#nexo-learning">' . self::icon( 'book' ) . '<span>Boas-vindas ao curso</span></a>';
        foreach ( $items as $item ) {
            $is_done = in_array( $item->ID, $completed, true ) || ! empty( $progress['quizzes'][ $item->ID ]['passed'] );
            $icon = $is_done ? 'check' : ( $item->post_type === 'nexo_quiz' ? 'test' : ( $item->post_type === 'nexo_topic' ? 'book' : 'play' ) );
            $out .= '<a class="nexo-curriculum-item' . ( $selected && $selected->ID === $item->ID ? ' is-selected' : '' ) . ( $item->post_type === 'nexo_topic' ? ' nexo-topic-item' : '' ) . ( $is_done ? ' is-completed' : '' ) . '" href="' . esc_url( add_query_arg( 'nexo_item', $item->ID, get_permalink( $course ) ) ) . '#nexo-learning">' . self::icon( $icon ) . '<span>' . esc_html( $item->post_title ) . '</span>' . ( $is_done ? '<span class="nexo-sr-only">Concluído</span>' : '' ) . '</a>';
        }
        $out .= '</nav></aside><article class="nexo-panel nexo-learning-content">';
        if ( ! $selected ) {
            $out .= '<span class="nexo-eyebrow">BEM-VINDO AO SEU CURSO</span><h2>Vamos começar?</h2><div class="nexo-prose">' . self::render_content( $course->post_content ) . '</div>';
            if ( $items ) { $out .= '<p class="nexo-muted">Escolha um conteúdo no menu para continuar seu aprendizado.</p><a class="nexo-button" href="' . esc_url( add_query_arg( 'nexo_item', $items[0]->ID, get_permalink( $course ) ) ) . '#nexo-learning">Acessar primeiro conteúdo ' . self::icon( 'arrow' ) . '</a>'; }
            else { $out .= '<p class="nexo-muted">Sua matrícula está ativa. Novos conteúdos aparecerão aqui quando forem publicados.</p>'; }
        } else {
            $label = $selected->post_type === 'nexo_quiz' ? 'TESTE SEU CONHECIMENTO' : ( $selected->post_type === 'nexo_topic' ? 'TÓPICO DO CURSO' : 'CONTINUE APRENDENDO' );
            $out .= '<span class="nexo-eyebrow">' . esc_html( $label ) . '</span><h2>' . esc_html( $selected->post_title ) . '</h2>';
            if ( $selected->post_type === 'nexo_lesson' ) { $out .= self::video( (string) get_post_meta( $selected->ID, '_nexo_video_url', true ) ); }
            $out .= '<div class="nexo-prose">' . self::render_content( $selected->post_content ) . '</div>';
            if ( $selected->post_type === 'nexo_quiz' ) { $out .= self::quiz_form( $course->ID, $selected->ID, $progress ); }
            elseif ( $selected->post_type === 'nexo_lesson' ) {
                if ( in_array( $selected->ID, $completed, true ) ) { $out .= '<div class="nexo-notice nexo-notice-success">' . self::icon( 'check' ) . ' Aula concluída. Você pode revisitar este conteúdo quando quiser.</div>'; }
                else { $out .= '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="nexo-action-form"><input type="hidden" name="action" value="nexo_complete_lesson"><input type="hidden" name="course_id" value="' . $course->ID . '"><input type="hidden" name="item_id" value="' . $selected->ID . '">' . wp_nonce_field( 'nexo_learn_' . $course->ID . '_' . $selected->ID, '_nexo_nonce', true, false ) . '<button class="nexo-button" type="submit">Marcar aula como concluída ' . self::icon( 'check' ) . '</button></form>'; }
            }
            $current_index = array_search( $selected->ID, array_map( static fn( $entry ) => $entry->ID, $items ), true );
            if ( $current_index !== false && isset( $items[ $current_index + 1 ] ) ) { $out .= '<a class="nexo-next-item" href="' . esc_url( add_query_arg( 'nexo_item', $items[ $current_index + 1 ]->ID, get_permalink( $course ) ) ) . '#nexo-learning">Próximo conteúdo ' . self::icon( 'arrow' ) . '</a>'; }
        }
        return $out . '</article></div>';
    }

    private static function video( string $url ): string {
        if ( ! $url || ! wp_http_validate_url( $url ) ) { return ''; }
        $host = preg_replace( '/^www\./', '', strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) ) ); $embed = '';
        if ( in_array( $host, array( 'youtube.com', 'youtu.be', 'vimeo.com', 'player.vimeo.com', 'm.youtube.com' ), true ) ) { $embed = wp_oembed_get( $url, array( 'width' => 960 ) ); }
        elseif ( preg_match( '/\.(mp4|webm|ogv)(\?.*)?$/i', $url ) ) { $embed = wp_video_shortcode( array( 'src' => esc_url_raw( $url ), 'preload' => 'metadata' ) ); }
        // Only trusted WordPress-generated video/oEmbed markup reaches this output.
        return $embed ? '<div class="nexo-video">' . $embed . '</div>' : '<p><a href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer">Abrir vídeo da aula ↗</a></p>';
    }

    private static function quiz_form( int $course_id, int $quiz_id, array $progress ): string {
        $questions = get_post_meta( $quiz_id, '_nexo_questions', true );
        if ( ! is_array( $questions ) || ! $questions ) { return '<p class="nexo-muted">Este teste ainda não tem perguntas publicadas.</p>'; }
        $out = ''; $result = $progress['quizzes'][ $quiz_id ] ?? null;
        if ( is_array( $result ) ) { $out .= '<div class="nexo-notice ' . ( ! empty( $result['passed'] ) ? 'nexo-notice-success' : '' ) . '"><strong>Seu resultado: ' . esc_html( (int) ( $result['score'] ?? 0 ) ) . '%.</strong> ' . esc_html( ! empty( $result['passed'] ) ? 'Teste aprovado. Você pode revisar e tentar novamente.' : 'A nota mínima ainda não foi atingida. Revise o conteúdo e tente novamente.' ) . '</div>'; }
        $passing = get_post_meta( $quiz_id, '_nexo_pass_score', true );
        $passing = '' === $passing ? 70 : min( 100, max( 0, (int) $passing ) );
        $out .= '<p class="nexo-small nexo-muted">Escolha uma resposta por pergunta. Nota mínima para aprovação: ' . $passing . '%.</p><form class="nexo-quiz" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="nexo_submit_quiz"><input type="hidden" name="course_id" value="' . $course_id . '"><input type="hidden" name="item_id" value="' . $quiz_id . '">' . wp_nonce_field( 'nexo_learn_' . $course_id . '_' . $quiz_id, '_nexo_nonce', true, false );
        foreach ( $questions as $index => $question ) {
            if ( ! is_array( $question ) || empty( $question['options'] ) || ! is_array( $question['options'] ) ) { continue; }
            $out .= '<fieldset class="nexo-question"><legend><span>' . ( (int) $index + 1 ) . '.</span> ' . esc_html( $question['question'] ?? '' ) . '</legend>';
            foreach ( $question['options'] as $option_index => $option ) { $out .= '<label class="nexo-answer"><input type="radio" name="answers[' . esc_attr( $index ) . ']" value="' . esc_attr( $option_index ) . '" required><span>' . esc_html( $option ) . '</span></label>'; }
            $out .= '</fieldset>';
        }
        return $out . '<button class="nexo-button" type="submit">Enviar respostas ' . self::icon( 'arrow' ) . '</button></form>';
    }

    public static function dashboard( $atts = array() ): string {
        $out = self::shell_open( 'dashboard' );
        if ( ! is_user_logged_in() ) { return $out . self::empty_state( 'Painel dos gerentes', 'Entre com sua conta de gerente para acompanhar os cursos atribuídos a você.', add_query_arg( 'redirect_to', nexo_page_url( 'dashboard' ), nexo_page_url( 'login' ) ), 'Acessar painel' ) . self::shell_close(); }
        $ids = Core::course_ids_for_manager();
        if ( ! current_user_can( 'manage_options' ) && ! $ids ) { return $out . self::empty_state( 'Nenhum curso atribuído', 'O administrador pode atribuir a você a gestão de um curso nas configurações do curso.' ) . self::shell_close(); }
        $courses = $ids ? get_posts( array( 'post_type' => 'nexo_course', 'post_status' => array( 'publish', 'draft', 'pending', 'private' ), 'post__in' => $ids, 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC' ) ) : array();
        $students = array(); $total_enrollments = 0; $course_totals = array();
        foreach ( $courses as $course ) {
            if ( ! Core::can_manage( $course->ID ) ) { continue; }
            $course_users = array();
            foreach ( Core::enrollments( $course->ID ) as $row ) { if ( $row->status === 'active' ) { $course_users[ (int) $row->user_id ] = true; $students[ (int) $row->user_id ] = true; } }
            $course_totals[ $course->ID ] = count( $course_users ); $total_enrollments += count( $course_users );
        }
        $out .= '<section class="nexo-page-heading nexo-dashboard-heading"><div><span class="nexo-eyebrow">VISÃO GERAL DOS SEUS CURSOS</span><h1>Gestão que faz<br>o aprendizado acontecer<span>.</span></h1><p>Acompanhe suas turmas e mantenha tudo em movimento.</p></div><a class="nexo-button" href="' . esc_url( admin_url( 'admin.php?page=nexo-cursos' ) ) . '">Gerenciar matrículas ' . self::icon( 'arrow' ) . '</a></section><div class="nexo-stat-grid"><div class="nexo-stat"><span>' . self::icon( 'book' ) . ' Cursos sob sua gestão</span><strong>' . count( $courses ) . '</strong><small>Todos os formatos</small></div><div class="nexo-stat"><span>' . self::icon( 'users' ) . ' Alunos ativos</span><strong>' . count( $students ) . '</strong><small>Pessoas com matrícula ativa</small></div><div class="nexo-stat nexo-stat-highlight"><span>' . self::icon( 'check' ) . ' Matrículas ativas</span><strong>' . $total_enrollments . '</strong><small>Uma pessoa pode ter vários cursos</small></div></div><section class="nexo-panel"><div class="nexo-section-heading"><h2>Seus cursos</h2><a class="nexo-text-link" href="' . esc_url( admin_url( 'admin.php?page=nexo-cursos' ) ) . '">Abrir gestão ' . self::icon( 'arrow' ) . '</a></div>';
        if ( $courses ) {
            $out .= '<div class="nexo-table-wrap"><table class="nexo-table"><thead><tr><th scope="col">Curso</th><th scope="col">Formato</th><th scope="col">Alunos ativos</th><th scope="col">Situação</th><th scope="col"><span class="nexo-sr-only">Ações</span></th></tr></thead><tbody>';
            foreach ( $courses as $course ) {
                if ( ! Core::can_manage( $course->ID ) ) { continue; }
                $status_object = get_post_status_object( $course->post_status );
                $out .= '<tr><td><strong>' . esc_html( $course->post_title ) . '</strong></td><td>' . esc_html( get_post_meta( $course->ID, '_nexo_mode', true ) === 'erp' ? 'Presencial / gestão' : 'On-line' ) . '</td><td>' . (int) ( $course_totals[ $course->ID ] ?? 0 ) . '</td><td><span class="nexo-status ' . ( $course->post_status === 'publish' ? 'nexo-status-active' : '' ) . '">' . esc_html( $status_object ? $status_object->label : $course->post_status ) . '</span></td><td><a class="nexo-text-link" href="' . esc_url( add_query_arg( 'course_id', $course->ID, admin_url( 'admin.php?page=nexo-cursos' ) ) ) . '">Gerenciar ' . self::icon( 'arrow' ) . '</a></td></tr>';
            }
            $out .= '</tbody></table></div>';
        } else { $out .= '<p class="nexo-muted">Os cursos criados e atribuídos a você aparecerão aqui.</p>'; }
        return $out . '</section>' . self::shell_close();
    }

    public static function login( $atts = array() ): string {
        return Commerce::auth_screen();
    }

    public static function login_assets(): void {
        $settings = nexo_settings();
        wp_enqueue_style( 'nexo-login', NEXO_URL . 'assets/frontend.css', array(), NEXO_VERSION );
        $css = 'body.login{' . nexo_brand_styles() . '}';
        if ( ! empty( $settings['logo_id'] ) ) {
            $logo = wp_get_attachment_image_url( absint( $settings['logo_id'] ), 'medium' );
            if ( $logo ) { $css .= 'body.login h1 a{background-image:url("' . esc_url_raw( $logo ) . '");background-size:contain;width:240px;height:76px;}'; }
        } else { $css .= 'body.login h1 a{background-image:none;width:auto;height:auto;text-indent:0;font-size:27px;font-weight:700;color:var(--nexo-primary);line-height:1.3;}'; }
        wp_add_inline_style( 'nexo-login', $css );
    }

    public static function login_message( string $message ): string {
        $settings = nexo_settings();
        return '<div class="nexo-native-login-intro"><h2>' . esc_html( $settings['login_title'] ?: 'Acesse sua conta' ) . '</h2><p>' . esc_html( $settings['login_message'] ?: 'Seu próximo passo começa aqui.' ) . '</p></div>' . $message;
    }

    public static function require_login(): void { auth_redirect(); exit; }

    private static function action_ids(): array {
        if ( strtoupper( $_SERVER['REQUEST_METHOD'] ?? '' ) !== 'POST' || ! is_user_logged_in() ) { wp_die( esc_html__( 'Ação não permitida.', 'nexo-cursos' ), '', array( 'response' => 403 ) ); }
        $course_id = absint( $_POST['course_id'] ?? 0 ); $item_id = absint( $_POST['item_id'] ?? 0 );
        check_admin_referer( 'nexo_learn_' . $course_id . '_' . $item_id, '_nexo_nonce' );
        if ( ! Core::has_access( $course_id ) ) { wp_die( esc_html__( 'Você não tem acesso a este curso.', 'nexo-cursos' ), '', array( 'response' => 403 ) ); }
        $item = get_post( $item_id );
        if ( ! $item || $item->post_status !== 'publish' || (int) get_post_meta( $item_id, '_nexo_course_id', true ) !== $course_id ) { wp_die( esc_html__( 'Conteúdo não encontrado neste curso.', 'nexo-cursos' ), '', array( 'response' => 404 ) ); }
        return array( $course_id, $item_id );
    }

    public static function complete_lesson(): void {
        list( $course_id, $item_id ) = self::action_ids();
        $result = Core::complete_lesson( $course_id, $item_id );
        self::finish_action( $course_id, $item_id, $result, 'Aula concluída. Mais um passo na sua evolução!' );
    }

    public static function submit_quiz(): void {
        list( $course_id, $item_id ) = self::action_ids();
        $raw = isset( $_POST['answers'] ) && is_array( $_POST['answers'] ) ? wp_unslash( $_POST['answers'] ) : array(); $answers = array();
        foreach ( $raw as $index => $answer ) { if ( is_scalar( $answer ) && ctype_digit( (string) $index ) && ctype_digit( (string) $answer ) ) { $answers[ (int) $index ] = (int) $answer; } }
        $result = Core::submit_quiz( $course_id, $item_id, $answers );
        self::finish_action( $course_id, $item_id, $result, 'Respostas enviadas. Confira seu resultado abaixo.' );
    }

    private static function finish_action( int $course_id, int $item_id, $result, string $message ): void {
        $error = is_wp_error( $result );
        set_transient( 'nexo_front_feedback_' . get_current_user_id(), array( 'message' => $error ? $result->get_error_message() : $message, 'error' => $error ), 90 );
        wp_safe_redirect( add_query_arg( array( 'nexo_item' => $item_id, 'nexo_feedback' => 1 ), get_permalink( $course_id ) ) . '#nexo-learning' ); exit;
    }

    private static function feedback(): string {
        if ( ! is_user_logged_in() || empty( $_GET['nexo_feedback'] ) ) { return ''; }
        $key = 'nexo_front_feedback_' . get_current_user_id(); $value = get_transient( $key );
        if ( ! is_array( $value ) ) { return ''; }
        delete_transient( $key );
        return '<div role="status" class="nexo-notice ' . ( empty( $value['error'] ) ? 'nexo-notice-success' : 'nexo-notice-error' ) . '">' . esc_html( $value['message'] ?? '' ) . '</div>';
    }
}


