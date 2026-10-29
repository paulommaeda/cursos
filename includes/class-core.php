<?php
/** Course storage, authorization, learning progress and WooCommerce lifecycle. */
namespace NexoCursos;
defined( 'ABSPATH' ) || exit;

final class Core {
    public static function boot(): void {
        add_action( 'init', array( __CLASS__, 'register_types' ) );
        add_action( 'woocommerce_payment_complete', array( __CLASS__, 'sync_order' ) );
        add_action( 'woocommerce_order_status_changed', array( __CLASS__, 'sync_order' ), 10, 1 );
        add_action( 'woocommerce_order_refunded', array( __CLASS__, 'sync_order' ), 10, 1 );
        add_action( 'woocommerce_checkout_create_order_line_item', array( __CLASS__, 'snapshot_courses' ), 10, 4 );
        add_action( 'woocommerce_store_api_checkout_order_processed', array( __CLASS__, 'validate_store_order' ) );
        add_filter( 'woocommerce_checkout_registration_enabled', array( __CLASS__, 'require_registration' ) );
        add_filter( 'woocommerce_checkout_registration_required', array( __CLASS__, 'require_registration' ) );
        add_filter( 'woocommerce_order_item_get_formatted_meta_data', array( __CLASS__, 'hide_item_meta' ) );
        add_action( 'delete_user', array( __CLASS__, 'delete_user_data' ) );
        add_filter( 'wp_privacy_personal_data_exporters', array( __CLASS__, 'exporter_registration' ) );
        add_filter( 'wp_privacy_personal_data_erasers', array( __CLASS__, 'eraser_registration' ) );
    }
    public static function activate(): void {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $table = self::table();
        $charset = $wpdb->get_charset_collate();
        dbDelta( "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            course_id bigint(20) unsigned NOT NULL,
            order_id bigint(20) unsigned NOT NULL DEFAULT 0,
            status varchar(20) NOT NULL DEFAULT 'active',
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY user_course_order (user_id,course_id,order_id),
            KEY course_status (course_id,status),
            KEY user_status (user_id,status),
            KEY order_id (order_id)
        ) {$charset};" );
        add_role( 'nexo_manager', 'Gerente de cursos', array( 'read' => true, 'nexo_manage_assigned' => true ) );
        $role = get_role( 'nexo_manager' );
        if ( $role ) { $role->add_cap( 'nexo_manage_assigned' ); }
        self::register_types();
        update_option( 'nexo_db_version', '1', false );
    }
    private static function table(): string {
        global $wpdb;
        return $wpdb->prefix . 'nexo_enrollments';
    }
    public static function register_types(): void {
        $caps = array_fill_keys( array( 'edit_post', 'read_post', 'delete_post', 'edit_posts', 'edit_others_posts', 'delete_posts', 'publish_posts', 'read_private_posts', 'delete_private_posts', 'delete_published_posts', 'delete_others_posts', 'edit_private_posts', 'edit_published_posts', 'create_posts' ), 'manage_options' );
        foreach ( array( 'nexo_course' => array( 'Cursos', 'Curso' ), 'nexo_topic' => array( 'Tópicos', 'Tópico' ), 'nexo_lesson' => array( 'Aulas', 'Aula' ), 'nexo_quiz' => array( 'Testes', 'Teste' ) ) as $type => $labels ) {
            $public = 'nexo_course' === $type;
            register_post_type( $type, array(
                'labels' => array( 'name' => $labels[0], 'singular_name' => $labels[1], 'add_new_item' => 'Adicionar ' . $labels[1], 'edit_item' => 'Editar ' . $labels[1] ),
                'public' => $public, 'publicly_queryable' => $public, 'show_ui' => true,
                'show_in_menu' => 'nexo-cursos', 'show_in_rest' => false, 'exclude_from_search' => ! $public,
                'has_archive' => false, 'rewrite' => $public ? array( 'slug' => 'curso', 'with_front' => false ) : false,
                'query_var' => $public, 'supports' => array( 'title', 'editor', 'thumbnail', 'page-attributes', 'excerpt' ),
                'capabilities' => $caps, 'map_meta_cap' => false,
            ) );
        }
    }
    private static function valid_course( int $course_id ): bool {
        return $course_id > 0 && 'nexo_course' === get_post_type( $course_id ) && 'trash' !== get_post_status( $course_id );
    }
    public static function can_manage( int $course_id, int $user_id = 0 ): bool {
        $user_id = $user_id ?: get_current_user_id();
        if ( ! $user_id || ! self::valid_course( $course_id ) ) { return false; }
        if ( user_can( $user_id, 'manage_options' ) || user_can( $user_id, 'nexo_manage_all_courses' ) ) { return true; }
        $managers = array_map( 'absint', (array) get_post_meta( $course_id, '_nexo_manager_ids', true ) );
        return user_can( $user_id, 'nexo_manage_assigned' ) && in_array( $user_id, $managers, true );
    }
    public static function course_ids_for_manager( int $user_id = 0 ): array {
        $user_id = $user_id ?: get_current_user_id();
        if ( ! $user_id || ( ! user_can( $user_id, 'manage_options' ) && ! user_can( $user_id, 'nexo_manage_assigned' ) && ! user_can( $user_id, 'nexo_manage_all_courses' ) ) ) { return array(); }
        $ids = get_posts( array( 'post_type' => 'nexo_course', 'post_status' => array( 'publish', 'draft', 'pending', 'private', 'future' ), 'numberposts' => -1, 'fields' => 'ids', 'orderby' => 'title', 'order' => 'ASC' ) );
        return array_values( array_filter( array_map( 'absint', $ids ), static fn( $id ) => self::can_manage( $id, $user_id ) ) );
    }
    public static function has_access( int $course_id, int $user_id = 0 ): bool {
        global $wpdb;
        $user_id = $user_id ?: get_current_user_id();
        if ( ! $user_id || ! self::valid_course( $course_id ) ) { return false; }
        if ( self::can_manage( $course_id, $user_id ) ) { return true; }
        if ( 'publish' !== get_post_status( $course_id ) ) { return false; }
        return (bool) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . self::table() . " WHERE user_id=%d AND course_id=%d AND status='active' LIMIT 1", $user_id, $course_id ) );
    }
    public static function curriculum( int $course_id ): array {
        if ( ! self::valid_course( $course_id ) ) { return array(); }
        return get_posts( array( 'post_type' => array( 'nexo_topic', 'nexo_lesson', 'nexo_quiz' ), 'post_status' => 'publish', 'numberposts' => -1, 'meta_key' => '_nexo_course_id', 'meta_value' => $course_id, 'orderby' => array( 'menu_order' => 'ASC', 'ID' => 'ASC' ) ) );
    }
    /** Internal storage operation. UI callers must check their nonce and management permission. */
    public static function enroll( int $user_id, int $course_id, int $order_id = 0 ) {
        global $wpdb;
        if ( ! get_userdata( $user_id ) || ! self::valid_course( $course_id ) || $order_id < 0 ) { return new \WP_Error( 'nexo_invalid_enrollment', 'Aluno ou curso inválido.' ); }
        $id = $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . self::table() . ' WHERE user_id=%d AND course_id=%d AND order_id=%d', $user_id, $course_id, $order_id ) );
        if ( $id ) {
            $result = $wpdb->update( self::table(), array( 'status' => 'active' ), array( 'id' => (int) $id ), array( '%s' ), array( '%d' ) );
        } else {
            $result = $wpdb->insert( self::table(), array( 'user_id' => $user_id, 'course_id' => $course_id, 'order_id' => $order_id, 'status' => 'active', 'created_at' => current_time( 'mysql', true ) ), array( '%d', '%d', '%d', '%s', '%s' ) );
        }
        return false === $result ? new \WP_Error( 'nexo_db_error', 'Não foi possível salvar a matrícula.' ) : true;
    }
    public static function revoke( int $user_id, int $course_id, int $order_id = 0 ): bool {
        global $wpdb;
        return false !== $wpdb->update( self::table(), array( 'status' => 'revoked' ), array( 'user_id' => $user_id, 'course_id' => $course_id, 'order_id' => $order_id ), array( '%s' ), array( '%d', '%d', '%d' ) );
    }
    public static function enrollments( int $course_id = 0 ): array {
        global $wpdb;
        $sql = 'SELECT * FROM ' . self::table();
        if ( $course_id ) { $sql .= $wpdb->prepare( ' WHERE course_id=%d', $course_id ); }
        return (array) $wpdb->get_results( $sql . ' ORDER BY created_at DESC,id DESC' );
    }
    public static function progress( int $course_id, int $user_id = 0 ): array {
        $user_id = $user_id ?: get_current_user_id();
        $raw = get_user_meta( $user_id, '_nexo_progress_' . $course_id, true );
        $raw = is_array( $raw ) ? $raw : array();
        $completed = array_map( 'absint', (array) ( $raw['completed'] ?? array() ) );
        $quizzes = is_array( $raw['quizzes'] ?? null ) ? $raw['quizzes'] : array();
        $items = array_filter( self::curriculum( $course_id ), static fn( $item ) => in_array( $item->post_type, array( 'nexo_lesson', 'nexo_quiz' ), true ) );
        $ids = array_map( static fn( $item ) => (int) $item->ID, $items );
        $completed = array_values( array_unique( array_intersect( $completed, $ids ) ) );
        $quizzes = array_intersect_key( $quizzes, array_flip( $ids ) );
        return array( 'completed' => $completed, 'quizzes' => $quizzes, 'percent' => $ids ? (int) round( 100 * count( $completed ) / count( $ids ) ) : 0 );
    }
    private static function check_learning( int $course_id, int $item_id, int $user_id, string $type ) {
        if ( ! self::has_access( $course_id, $user_id ) ) { return new \WP_Error( 'nexo_forbidden', 'Você não tem acesso a este curso.' ); }
        if ( 'erp' === get_post_meta( $course_id, '_nexo_mode', true ) ) { return new \WP_Error( 'nexo_erp', 'Este curso usa somente a gestão administrativa.' ); }
        if ( $type !== get_post_type( $item_id ) || 'publish' !== get_post_status( $item_id ) || $course_id !== (int) get_post_meta( $item_id, '_nexo_course_id', true ) ) { return new \WP_Error( 'nexo_invalid_item', 'Conteúdo inválido para este curso.' ); }
        return true;
    }
    public static function complete_lesson( int $course_id, int $lesson_id, int $user_id = 0 ) {
        $user_id = $user_id ?: get_current_user_id();
        $valid = self::check_learning( $course_id, $lesson_id, $user_id, 'nexo_lesson' );
        if ( is_wp_error( $valid ) ) { return $valid; }
        $progress = self::progress( $course_id, $user_id );
        $progress['completed'][] = $lesson_id;
        $progress['completed'] = array_values( array_unique( $progress['completed'] ) );
        unset( $progress['percent'] );
        update_user_meta( $user_id, '_nexo_progress_' . $course_id, $progress );
        return true;
    }
    public static function submit_quiz( int $course_id, int $quiz_id, array $answers, int $user_id = 0 ) {
        $user_id = $user_id ?: get_current_user_id();
        $valid = self::check_learning( $course_id, $quiz_id, $user_id, 'nexo_quiz' );
        if ( is_wp_error( $valid ) ) { return $valid; }
        $questions = get_post_meta( $quiz_id, '_nexo_questions', true );
        if ( ! is_array( $questions ) || ! $questions ) { return new \WP_Error( 'nexo_empty_quiz', 'Este teste ainda não possui questões.' ); }
        $correct = 0;
        foreach ( $questions as $index => $question ) {
            if ( ! is_array( $question ) || ! isset( $question['correct'], $question['options'] ) || ! is_array( $question['options'] ) ) { return new \WP_Error( 'nexo_invalid_quiz', 'Este teste precisa ser revisado pelo responsável.' ); }
            $answer = $answers[ $index ] ?? null;
            if ( ! is_scalar( $answer ) || ! preg_match( '/^\d+$/', (string) $answer ) || ! array_key_exists( (int) $answer, $question['options'] ) ) { return new \WP_Error( 'nexo_missing_answer', 'Responda todas as questões antes de enviar.' ); }
            if ( (int) $answer === (int) $question['correct'] ) { ++$correct; }
        }
        $score = (int) round( 100 * $correct / count( $questions ) );
        $threshold = get_post_meta( $quiz_id, '_nexo_pass_score', true );
        $threshold = '' === $threshold ? 70 : max( 0, min( 100, (int) $threshold ) );
        $result = array( 'score' => $score, 'passed' => $score >= $threshold );
        $progress = self::progress( $course_id, $user_id );
        $previous = $progress['quizzes'][ $quiz_id ] ?? array( 'score' => -1, 'passed' => false );
        // Retakes preserve the best score and never undo an earned completion.
        if ( $score >= (int) $previous['score'] ) { $progress['quizzes'][ $quiz_id ] = $result; }
        if ( $result['passed'] ) {
            $progress['completed'][] = $quiz_id;
            $progress['completed'] = array_values( array_unique( $progress['completed'] ) );
        }
        unset( $progress['percent'] );
        update_user_meta( $user_id, '_nexo_progress_' . $course_id, $progress );
        return $result;
    }
    public static function attendance( int $course_id, int $user_id ): array {
        $value = get_user_meta( $user_id, '_nexo_attendance_' . $course_id, true );
        return is_array( $value ) ? $value : array();
    }
    public static function set_attendance( int $course_id, int $user_id, string $date, string $status ) {
        if ( ! self::can_manage( $course_id ) || ! get_userdata( $user_id ) || ! self::has_access( $course_id, $user_id ) ) { return new \WP_Error( 'nexo_forbidden', 'Não foi possível registrar a presença deste aluno.' ); }
        $parts = explode( '-', $date );
        if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) || 3 !== count( $parts ) || ! checkdate( (int) $parts[1], (int) $parts[2], (int) $parts[0] ) || ! in_array( $status, array( 'present', 'absent', 'excused' ), true ) ) { return new \WP_Error( 'nexo_invalid_attendance', 'Data ou situação de presença inválida.' ); }
        $attendance = self::attendance( $course_id, $user_id );
        $attendance[ $date ] = $status;
        ksort( $attendance );
        update_user_meta( $user_id, '_nexo_attendance_' . $course_id, $attendance );
        return true;
    }
    public static function product_courses( int $product_id, int $variation_id = 0 ): array {
        $ids = array_filter( array_unique( array( $product_id, $variation_id ) ) );
        if ( ! $ids ) { return array(); }
        return array_map( 'absint', get_posts( array( 'post_type' => 'nexo_course', 'post_status' => array( 'publish', 'private', 'draft', 'pending', 'future' ), 'numberposts' => -1, 'fields' => 'ids', 'meta_query' => array( array( 'key' => '_nexo_product_id', 'value' => $ids, 'compare' => 'IN', 'type' => 'NUMERIC' ) ) ) ) );
    }
    public static function require_registration( $required ) {
        if ( is_user_logged_in() || ! function_exists( 'WC' ) || ! WC()->cart ) { return $required; }
        foreach ( WC()->cart->get_cart() as $item ) {
            if ( self::product_courses( (int) $item['product_id'], (int) ( $item['variation_id'] ?? 0 ) ) ) { return true; }
        }
        return $required;
    }
    public static function snapshot_courses( $item, $cart_item_key, $values, $order ): void {
        $ids = self::product_courses( (int) $item->get_product_id(), (int) $item->get_variation_id() );
        if ( $ids ) { $item->add_meta_data( '_nexo_course_ids', $ids, true ); }
    }
    public static function hide_item_meta( array $meta ): array {
        foreach ( $meta as $key => $row ) { if ( '_nexo_course_ids' === $row->key ) { unset( $meta[ $key ] ); } }
        return $meta;
    }
    public static function validate_store_order( $order ): void {
        $has_courses = false;
        foreach ( $order->get_items() as $item ) {
            $saved_ids = $item->get_meta( '_nexo_course_ids', true );
            $ids = is_array( $saved_ids ) ? array_map( 'absint', $saved_ids ) : self::product_courses( (int) $item->get_product_id(), (int) $item->get_variation_id() );
            if ( $ids ) {
                $has_courses = true;
                if ( ! is_array( $saved_ids ) ) { $item->update_meta_data( '_nexo_course_ids', $ids ); $item->save(); }
            }
        }
        if ( $has_courses && ! $order->get_customer_id() ) {
            $message = 'Entre na sua conta ou crie uma conta para comprar e acessar seus cursos.';
            if ( class_exists( '\Automattic\WooCommerce\StoreApi\Exceptions\RouteException' ) ) { throw new \Automattic\WooCommerce\StoreApi\Exceptions\RouteException( 'nexo_account_required', $message, 400 ); }
            throw new \Exception( $message );
        }
    }
    /** WooCommerce CRUD supports both HPOS and the legacy datastore. */
    public static function sync_order( $order_id ): void {
        global $wpdb;
        if ( ! function_exists( 'wc_get_order' ) ) { return; }
        $order = wc_get_order( absint( $order_id ) );
        if ( ! $order || ! is_a( $order, 'WC_Order' ) ) { return; }
        $user_id = (int) $order->get_customer_id();
        $active = array();
        $is_paid = $order->has_status( array( 'processing', 'completed' ) );
        if ( (float) $order->get_total() > 0 && (float) $order->get_total_refunded() >= (float) $order->get_total() ) { $is_paid = false; }
        foreach ( $order->get_items() as $item_id => $item ) {
            $ids = $item->get_meta( '_nexo_course_ids', true );
            if ( ! is_array( $ids ) ) {
                $ids = self::product_courses( (int) $item->get_product_id(), (int) $item->get_variation_id() );
                if ( $ids ) { $item->update_meta_data( '_nexo_course_ids', $ids ); $item->save(); }
            }
            $remaining = (float) $item->get_quantity() + (float) $order->get_qty_refunded_for_item( $item_id );
            if ( $is_paid && $remaining > 0 ) { $active = array_merge( $active, array_map( 'absint', $ids ) ); }
        }
        $active = array_values( array_unique( $active ) );
        // Reconcile previous sources: changing or reassigning an order must not leave stale access.
        $existing = $wpdb->get_results( $wpdb->prepare( 'SELECT user_id,course_id FROM ' . self::table() . ' WHERE order_id=%d', $order->get_id() ) );
        foreach ( $existing as $row ) {
            if ( (int) $row->user_id !== $user_id || ! in_array( (int) $row->course_id, $active, true ) ) { self::revoke( (int) $row->user_id, (int) $row->course_id, (int) $order->get_id() ); }
        }
        if ( ! $user_id || ! get_userdata( $user_id ) ) {
            if ( $active && ! $order->get_meta( '_nexo_missing_account_noted', true ) ) {
                $order->add_order_note( 'Nexo Cursos: matrícula não criada porque o pedido não possui conta de aluno. Vincule uma conta ao pedido e altere novamente para um status pago.' );
                $order->update_meta_data( '_nexo_missing_account_noted', 'yes' ); $order->save();
            }
            return;
        }
        foreach ( $active as $course_id ) { self::enroll( $user_id, $course_id, (int) $order->get_id() ); }
    }
    public static function delete_user_data( int $user_id ): void {
        global $wpdb;
        $wpdb->delete( self::table(), array( 'user_id' => $user_id ), array( '%d' ) );
    }
    public static function exporter_registration( array $exporters ): array {
        $exporters['nexo-cursos'] = array( 'exporter_friendly_name' => 'Nexo Cursos', 'callback' => array( __CLASS__, 'export_data' ) );
        return $exporters;
    }
    public static function eraser_registration( array $erasers ): array {
        $erasers['nexo-cursos'] = array( 'eraser_friendly_name' => 'Nexo Cursos', 'callback' => array( __CLASS__, 'erase_data' ) );
        return $erasers;
    }
    public static function export_data( string $email, int $page = 1 ): array {
        global $wpdb;
        $user = get_user_by( 'email', $email ); $data = array();
        if ( ! $user ) { return array( 'data' => $data, 'done' => true ); }
        $rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE user_id=%d ORDER BY id LIMIT 100 OFFSET %d', $user->ID, ( max( 1, $page ) - 1 ) * 100 ) );
        foreach ( $rows as $row ) {
            $data[] = array( 'group_id' => 'nexo-cursos', 'group_label' => 'Cursos e aprendizagem', 'item_id' => 'nexo-enrollment-' . $row->id, 'data' => array(
                array( 'name' => 'Curso', 'value' => get_the_title( $row->course_id ) ),
                array( 'name' => 'Pedido', 'value' => (string) $row->order_id ),
                array( 'name' => 'Situação', 'value' => $row->status ),
                array( 'name' => 'Criada em (UTC)', 'value' => $row->created_at ),
                array( 'name' => 'Progresso', 'value' => wp_json_encode( self::progress( (int) $row->course_id, (int) $user->ID ) ) ),
                array( 'name' => 'Presenças', 'value' => wp_json_encode( self::attendance( (int) $row->course_id, (int) $user->ID ) ) ),
            ) );
        }
        return array( 'data' => $data, 'done' => count( $rows ) < 100 );
    }
    public static function erase_data( string $email, int $page = 1 ): array {
        $user = get_user_by( 'email', $email );
        if ( ! $user ) { return array( 'items_removed' => false, 'items_retained' => false, 'messages' => array(), 'done' => true ); }
        self::delete_user_data( (int) $user->ID );
        foreach ( array_keys( get_user_meta( $user->ID ) ) as $key ) {
            if ( str_starts_with( $key, '_nexo_progress_' ) || str_starts_with( $key, '_nexo_attendance_' ) ) { delete_user_meta( $user->ID, $key ); }
        }
        return array( 'items_removed' => true, 'items_retained' => false, 'messages' => array( 'Matrículas, progresso e presenças do Nexo Cursos apagados. Os pedidos seguem a política de retenção do WooCommerce.' ), 'done' => true );
    }
}



