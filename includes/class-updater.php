<?php
/** Stable GitHub releases integrated with the native WordPress updater. */
namespace NexoCursos;

defined( 'ABSPATH' ) || exit;

final class Updater {
    public const CACHE_KEY = 'nexo_github_release_v1';
    public const GOOD_KEY = 'nexo_github_release_good_v1';
    public const REPOSITORY = 'https://github.com/paulommaeda/cursos';
    private const API_URL = 'https://api.github.com/repos/paulommaeda/cursos/releases/latest';
    private const SLUG = 'nexo-cursos';
    private const ASSET = 'nexo-cursos.zip';

    public static function boot(): void {
        add_filter( 'update_themes_github.com', array(__CLASS__, 'theme_update'), 10, 4 );
        add_filter( 'update_plugins_github.com', array( __CLASS__, 'filter_update' ), 10, 4 );
        add_filter( 'plugins_api', array( __CLASS__, 'plugin_info' ), 20, 3 );
        add_filter( 'plugin_row_meta', array( __CLASS__, 'row_meta' ), 10, 2 );
        add_action( 'admin_post_nexo_check_updates', array( __CLASS__, 'manual_check' ) );
        add_action( 'admin_notices', array( __CLASS__, 'notices' ) );
        add_action( 'network_admin_notices', array( __CLASS__, 'notices' ) );
    }

    /** Forget the request cache, retaining the last known valid release on outages. */
    public static function clear_cache(): void {
        delete_site_transient( self::CACHE_KEY );
    }

    /**
     * Fetch release metadata without sending the website URL or user information.
     * HTTP errors are returned to the caller and briefly cached to respect API limits.
     *
     * @return array|\WP_Error
     */
    public static function get_release( bool $force = false ): array|\WP_Error {
        if ( $force ) { self::clear_cache(); }
        $cached = get_site_transient( self::CACHE_KEY );
        if ( is_array( $cached ) ) {
            if ( isset( $cached['release'] ) && is_array( $cached['release'] ) ) { return $cached['release']; }
            if ( isset( $cached['error_code'], $cached['error_message'] ) ) {
                return new \WP_Error( (string) $cached['error_code'], (string) $cached['error_message'] );
            }
        }
        $response = wp_safe_remote_get( self::API_URL, array(
            'timeout' => 10,
            'redirection' => 0,
            'limit_response_size' => 524288,
            'user-agent' => 'Nexo-Cursos/' . NEXO_VERSION,
            'headers' => array( 'Accept' => 'application/vnd.github+json', 'X-GitHub-Api-Version' => '2022-11-28' ),
        ) );
        if ( is_wp_error( $response ) ) {
            return self::cache_error( new \WP_Error( 'nexo_github_unavailable', 'Não foi possível consultar o GitHub. Tente novamente mais tarde.' ) );
        }
        $status = (int) wp_remote_retrieve_response_code( $response );
        if ( 404 === $status ) {
            return self::cache_error( new \WP_Error( 'nexo_release_missing', 'Ainda não existe uma versão estável publicada com o pacote de instalação.' ) );
        }
        if ( 200 !== $status ) {
            return self::cache_error( new \WP_Error( 'nexo_github_http', 'O GitHub não respondeu à consulta de atualizações. Tente novamente mais tarde.' ) );
        }
        $decoded = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( ! is_array( $decoded ) || JSON_ERROR_NONE !== json_last_error() ) {
            return self::cache_error( new \WP_Error( 'nexo_release_invalid', 'O GitHub retornou dados de versão inválidos.' ) );
        }
        $release = self::parse_release( $decoded );
        if ( is_wp_error( $release ) ) { return self::cache_error( $release ); }
        set_site_transient( self::CACHE_KEY, array( 'release' => $release ), 6 * HOUR_IN_SECONDS );
        set_site_transient( self::GOOD_KEY, $release, 7 * DAY_IN_SECONDS );
        return $release;
    }

    private static function cache_error( \WP_Error $error ): \WP_Error {
        set_site_transient( self::CACHE_KEY, array( 'error_code' => $error->get_error_code(), 'error_message' => $error->get_error_message() ), 30 * MINUTE_IN_SECONDS );
        return $error;
    }

    /** Accept only stable releases and a ZIP asset from this repository and tag. */
    public static function parse_release( array $data ): array|\WP_Error {
        $tag = $data['tag_name'] ?? null;
        if ( ! is_string( $tag ) || ! preg_match( '/^v?(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)$/D', $tag, $matches )
            || ! array_key_exists( 'draft', $data ) || false !== $data['draft']
            || ! array_key_exists( 'prerelease', $data ) || false !== $data['prerelease']
            || ! isset( $data['assets'] ) || ! is_array( $data['assets'] ) ) {
            return new \WP_Error( 'nexo_release_invalid', 'A versão retornada não é uma versão estável válida.' );
        }
        $package = '';
        $expected = self::REPOSITORY . '/releases/download/' . rawurlencode( $tag ) . '/' . self::ASSET;
        foreach ( $data['assets'] as $asset ) {
            if ( ! is_array( $asset ) || ( $asset['name'] ?? '' ) !== self::ASSET || ( $asset['state'] ?? '' ) !== 'uploaded'
                || ! isset( $asset['size'] ) || ! is_numeric( $asset['size'] ) || (float) $asset['size'] <= 0
                || ! is_string( $asset['browser_download_url'] ?? null ) ) { continue; }
            $url = $asset['browser_download_url'];
            // Exact equality rejects alternate hosts, paths, credentials, ports and queries.
            if ( $expected === $url ) { $package = $url; break; }
        }
        if ( '' === $package ) {
            return new \WP_Error( 'nexo_release_asset_missing', 'A versão publicada ainda não contém um pacote nexo-cursos.zip válido.' );
        }
        $name = isset( $data['name'] ) && is_string( $data['name'] ) ? sanitize_text_field( $data['name'] ) : $tag;
        $body = isset( $data['body'] ) && is_string( $data['body'] ) ? substr( $data['body'], 0, 200000 ) : '';
        $published = isset( $data['published_at'] ) && is_string( $data['published_at'] ) ? $data['published_at'] : '';
        $theme_package = '';
        $theme_expected = self::REPOSITORY.'/releases/download/'.rawurlencode($tag).'/shalom-formacao.zip';
        foreach ($data['assets'] as $asset) {
            if (is_array($asset) && ($asset['name']??'')==='shalom-formacao.zip' && ($asset['state']??'')==='uploaded' && is_numeric($asset['size']??null) && (float)$asset['size']>0 && ($asset['browser_download_url']??'')===$theme_expected) { $theme_package=$theme_expected; break; }
        }
        return array(
            'theme_package' => $theme_package,
            'version' => $matches[1] . '.' . $matches[2] . '.' . $matches[3],
            'tag' => $tag,
            'name' => $name ?: $tag,
            'url' => self::REPOSITORY . '/releases/tag/' . rawurlencode( $tag ),
            'package' => $package,
            'body' => $body,
            'published_at' => $published,
        );
    }

    private static function plugin_file(): string {
        return plugin_basename( NEXO_FILE );
    }

    private static function update_data( array $release ): array {
        return array(
            'id' => self::REPOSITORY,
            'slug' => self::SLUG,
            'plugin' => self::plugin_file(),
            'version' => $release['version'],
            'new_version' => $release['version'],
            'url' => $release['url'],
            'package' => $release['package'],
            'requires' => '6.4',
            'requires_php' => '8.1',
            'tested' => '7.1.3',
        );
    }

    /** Native WordPress update discovery; all other GitHub-hosted plugins pass through. */
    public static function filter_update( $update, array $plugin_data, string $plugin_file, array $locales ) {
        if ( self::plugin_file() !== $plugin_file || ( $plugin_data['UpdateURI'] ?? '' ) !== self::REPOSITORY ) { return $update; }
        $release = self::get_release();
        if ( is_wp_error( $release ) ) { $release = get_site_transient( self::GOOD_KEY ); }
        return is_array( $release ) && isset( $release['version'], $release['package'], $release['url'] ) ? self::update_data( $release ) : $update;
    }

    /** Companion themes use their own ZIP and the shared stable release version. */
    public static function theme_update($update, array $theme_data, string $stylesheet, array $locales) {
        if ($stylesheet!=='shalom-formacao' || ($theme_data['UpdateURI']??'')!==self::REPOSITORY) { return $update; }
        $release=self::get_release();
        if (is_wp_error($release)) { $release=get_site_transient(self::GOOD_KEY); }
        if (!is_array($release) || empty($release['theme_package'])) { return $update; }
        return ['theme'=>'shalom-formacao','version'=>$release['version'],'new_version'=>$release['version'],'url'=>$release['url'],'package'=>$release['theme_package'],'requires'=>'6.4','requires_php'=>'8.1'];
    }

    /** Plugin details modal, also used by the native update screen. */
    public static function plugin_info( $result, string $action, $args ) {
        if ( 'plugin_information' !== $action || ! is_object( $args ) || ( $args->slug ?? '' ) !== self::SLUG ) { return $result; }
        $release = self::get_release();
        if ( is_wp_error( $release ) ) {
            $known = get_site_transient( self::GOOD_KEY );
            if ( ! is_array( $known ) || ! isset( $known['version'], $known['package'], $known['url'] ) ) { return $release; }
            $release = $known;
        }
        $published = strtotime( (string) $release['published_at'] );
        return (object) array(
            'name' => 'Nexo Cursos',
            'slug' => self::SLUG,
            'version' => $release['version'],
            'author' => '<a href="' . esc_url( self::REPOSITORY ) . '">Nexo Cursos</a>',
            'homepage' => self::REPOSITORY,
            'requires' => '6.4',
            'requires_php' => '8.1',
            'tested' => '7.1.3',
            'last_updated' => false !== $published ? gmdate( 'Y-m-d H:i:s', $published ) : '',
            'download_link' => $release['package'],
            'external' => true,
            'sections' => array(
                'description' => '<p>Gestão de cursos EAD ou presenciais, matrículas, avaliações, presenças, gerentes e vendas com WooCommerce. Tópicos, aulas e testes são opcionais.</p><p>As atualizações são distribuídas pelas versões estáveis publicadas no GitHub. A instalação e as atualizações usam os controles do WordPress.</p>',
                'changelog' => '<h4>' . esc_html( $release['name'] ) . '</h4><pre style="white-space:pre-wrap;word-wrap:break-word">' . esc_html( $release['body'] ) . '</pre>',
            ),
        );
    }

    public static function row_meta( array $links, string $file ): array {
        if ( self::plugin_file() !== $file ) { return $links; }
        $links[] = '<a href="' . esc_url( self::REPOSITORY . '/releases' ) . '" target="_blank" rel="noopener noreferrer">Versões no GitHub</a>';
        if ( current_user_can( 'update_plugins' ) ) {
            $url = wp_nonce_url( admin_url( 'admin-post.php?action=nexo_check_updates' ), 'nexo_check_updates' );
            $links[] = '<a href="' . esc_url( $url ) . '">Verificar atualizações</a>';
        }
        return $links;
    }

    /** A manual check only replaces our entry, preserving every other plugin offer. */
    public static function manual_check(): void {
        if ( ! current_user_can( 'update_plugins' ) ) {
            wp_die( esc_html__( 'Você não tem permissão para verificar atualizações.', 'nexo-cursos' ), '', array( 'response' => 403 ) );
        }
        check_admin_referer( 'nexo_check_updates' );
        $release = self::get_release( true );
        $notice = array( 'type' => 'error', 'message' => '' );
        if ( is_wp_error( $release ) ) {
            $notice['message'] = $release->get_error_message() . ' As informações de atualização já disponíveis foram preservadas.';
        } else {
            $transient = get_site_transient( 'update_plugins' );
            if ( ! is_object( $transient ) ) { $transient = (object) array( 'last_checked' => time() ); }
            foreach ( array( 'response', 'no_update', 'checked' ) as $key ) {
                if ( ! isset( $transient->$key ) || ! is_array( $transient->$key ) ) { $transient->$key = array(); }
            }
            $file = self::plugin_file();
            $payload = (object) self::update_data( $release );
            $transient->checked[ $file ] = NEXO_VERSION;
            $newer = version_compare( $release['version'], NEXO_VERSION, '>' );
            if ( $newer ) { $transient->response[ $file ] = $payload; unset( $transient->no_update[ $file ] ); }
            else { $transient->no_update[ $file ] = $payload; unset( $transient->response[ $file ] ); }
            set_site_transient( 'update_plugins', $transient );
            $notice = array( 'type' => 'success', 'message' => $newer
                ? 'A versão ' . $release['version'] . ' está disponível. Use Atualizar agora na lista de plugins.'
                : 'O Nexo Cursos está atualizado. Versão instalada: ' . NEXO_VERSION . '.' );
        }
        set_transient( 'nexo_update_notice_' . get_current_user_id(), $notice, MINUTE_IN_SECONDS );
        wp_safe_redirect( network_admin_url( 'plugins.php' ) );
        exit;
    }

    public static function notices(): void {
        if ( ! current_user_can( 'update_plugins' ) ) { return; }
        $key = 'nexo_update_notice_' . get_current_user_id();
        $notice = get_transient( $key );
        if ( ! is_array( $notice ) || ! isset( $notice['message'] ) ) { return; }
        delete_transient( $key );
        $type = ( $notice['type'] ?? '' ) === 'success' ? 'success' : 'error';
        echo '<div class="notice notice-' . esc_attr( $type ) . ' is-dismissible"><p>' . esc_html( $notice['message'] ) . '</p></div>';
    }
}

