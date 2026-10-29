<?php
/** Front-office workspace. Every mutation is authorized again on the server. */
namespace NexoCursos;
defined('ABSPATH') || exit;
final class Portal {
    private static bool $context=false;
    public static function boot(): void {
        add_action('init',[self::class,'roles']);
        add_action('template_redirect',[self::class,'route'],5);
        add_action('admin_init',[self::class,'protect_admin'],0);
        add_action('init',static function(){if(is_admin()){self::protect_admin();}},1);
        add_filter('show_admin_bar',static fn($show)=>self::is_page()?false:(current_user_can('manage_options')?$show:false),99);
        add_filter('login_redirect',static fn($to,$requested,$user)=>$user instanceof \WP_User&&self::allowed((int)$user->ID)?self::url():$to,99,3);
        add_action('wp_enqueue_scripts',[self::class,'readability'],45);
        add_action('wp_enqueue_scripts',[self::class,'assets'],50);
        add_filter('body_class',static fn($classes)=>self::is_page()?array_merge($classes,['nexo-portal-page']):$classes);
    }
    public static function roles(): void {
        if(get_option('nexo_portal_roles')==='1'){return;}
        add_role('nexo_coordinator','Coordenador da plataforma',['read'=>true,'nexo_manage_all_courses'=>true,'nexo_manage_sales'=>true,'nexo_manage_brand'=>true]);
        $role=get_role('nexo_coordinator');if($role){foreach(['read','nexo_manage_all_courses','nexo_manage_sales','nexo_manage_brand'] as $cap){$role->add_cap($cap);}}
        update_option('nexo_portal_roles','1',false);
    }
    public static function allowed(int $uid=0): bool {$uid=$uid?:get_current_user_id();return $uid>0&&(user_can($uid,'manage_options')||user_can($uid,'nexo_manage_assigned')||user_can($uid,'nexo_manage_all_courses')||user_can($uid,'manage_woocommerce'));}
    public static function all_courses(): bool {return current_user_can('manage_options')||current_user_can('nexo_manage_all_courses');}
    public static function finance(): bool {return current_user_can('manage_options')||current_user_can('nexo_manage_sales')||current_user_can('manage_woocommerce');}
    public static function brand(): bool {return current_user_can('manage_options')||current_user_can('nexo_manage_brand');}
    public static function is_page(): bool {$p=get_post();return $p instanceof \WP_Post&&has_shortcode($p->post_content,'nexo_manager_dashboard');}
    public static function in_context(): bool {return self::$context||self::is_page();}
    public static function url(string $view='overview',array $args=[]): string {return add_query_arg(array_merge(['nexo_view'=>$view],$args),nexo_page_url('dashboard'));}
    public static function protect_admin(): void {
        if(current_user_can('manage_options')||!is_user_logged_in()||wp_doing_ajax()){return;}
        $script=basename((string)($_SERVER['SCRIPT_NAME']??''));if(in_array($script,['admin-post.php','admin-ajax.php'],true)){return;}
        wp_safe_redirect(self::allowed()?self::url():nexo_page_url('account'));exit;
    }
    public static function readability(): void {if(wp_style_is('nexo-frontend','enqueued')){$deps=['nexo-frontend'];if(wp_style_is('shalom-formacao','enqueued')){$deps[]='shalom-formacao';}wp_enqueue_style('nexo-readability',NEXO_URL.'assets/readability.css',$deps,NEXO_VERSION);}}
    public static function assets(): void {
        if(!self::is_page()){return;}
        wp_enqueue_style('nexo-portal',NEXO_URL.'assets/portal.css',['nexo-frontend','nexo-readability'],NEXO_VERSION);
        wp_enqueue_script('nexo-portal',NEXO_URL.'assets/portal.js',[],NEXO_VERSION,true);
        wp_add_inline_style('nexo-portal',':root{'.nexo_brand_styles().'}');
    }
    public static function text(array $data,string $key): string {return isset($data[$key])&&is_scalar($data[$key])?sanitize_text_field(wp_unslash((string)$data[$key])):'';}
    public static function id(array $data,string $key): int {return absint(self::text($data,$key));}
    public static function courses(): array {$ids=Core::course_ids_for_manager();return $ids?get_posts(['post_type'=>'nexo_course','post_status'=>['publish','draft','private','pending','future'],'post__in'=>$ids,'numberposts'=>-1,'orderby'=>'title','order'=>'ASC']):[];}
    public static function course(int $id) {$p=get_post($id);return $p&&$p->post_type==='nexo_course'&&$p->post_status!=='trash'&&Core::can_manage($id)?$p:new \WP_Error('forbidden','Você não tem acesso a este curso.');}
    public static function get_fields(string $view): string {
        $out='<input type="hidden" name="nexo_view" value="'.esc_attr($view).'">';parse_str((string)wp_parse_url(nexo_page_url('dashboard'),PHP_URL_QUERY),$query);
        foreach(['page_id','pagename'] as $key){if(isset($query[$key])&&is_scalar($query[$key])){$out.='<input type="hidden" name="'.$key.'" value="'.esc_attr($query[$key]).'">';}}return $out;
    }
    public static function form(string $action,string $view,array $fields=[]): string {
        $out='<form method="post" enctype="multipart/form-data" action="'.esc_url(self::url($view)).'" class="np-form"><input type="hidden" name="nexo_portal_action" value="'.esc_attr($action).'">'.wp_nonce_field('nexo_portal_'.$action,'_nexo_portal_nonce',false,false).'<input type="hidden" name="nexo_return_view" value="'.esc_attr($view).'">';
        foreach($fields as $key=>$value){$out.='<input type="hidden" name="'.esc_attr($key).'" value="'.esc_attr($value).'">';}return $out;
    }
    public static function flash(string $message,bool $error=false): void {set_transient('nexo_portal_flash_'.get_current_user_id(),['message'=>$message,'error'=>$error],120);}
    public static function redirect(string $view,array $args=[]): void {wp_safe_redirect(self::url($view,$args));exit;}
    public static function route(): void {
        if(($_SERVER['REQUEST_METHOD']??'')!=='POST'||empty($_POST['nexo_portal_action'])){return;}
        self::$context=true;nocache_headers();if(!defined('DONOTCACHEPAGE')){define('DONOTCACHEPAGE',true);}
        if(!self::allowed()){wp_die('Acesso restrito à equipe autorizada.','',['response'=>403]);}
        $action=sanitize_key(self::text($_POST,'nexo_portal_action'));
        if(!wp_verify_nonce(self::text($_POST,'_nexo_portal_nonce'),'nexo_portal_'.$action)){wp_die('Sua sessão de formulário expirou. Atualize a página e tente novamente.','',['response'=>403]);}
        if($action==='classroom'){Classroom::handle();return;}
        if($action==='sales_export'){PortalSales::export($_POST);return;}
        $result=self::mutate($action,$_POST);$view=sanitize_key(self::text($_POST,'nexo_return_view'))?:'overview';$args=[];
        if(is_wp_error($result)){self::flash($result->get_error_message(),true);foreach(['course_id','item_id','order_id','product_id'] as $key){if(self::id($_POST,$key)){$args[$key]=self::id($_POST,$key);}}if(isset($_POST['editor'])){$args['editor']=sanitize_key(self::text($_POST,'editor'));}}
        else{self::flash($result['message']??'Alterações salvas.');$view=$result['view']??$view;$args=$result['args']??[];}
        self::redirect($view,$args);
    }
    /** Internal entry point also exercised by integration tests; authorization is not delegated to the browser. */
    public static function mutate(string $action,array $data) {
        if(!self::allowed()){return new \WP_Error('forbidden','Acesso restrito.');}
        return match($action){
            'course_save','course_archive','content_save','content_archive'=>PortalCourses::mutate($action,$data),
            'student_create','enrollment','student_profile','legacy_attendance','team_save'=>PortalPeople::mutate($action,$data),
            'order_create','order_status','order_note','order_refund','order_customer','orders_sync','product_save'=>PortalSales::mutate($action,$data),
            'brand_save','home_save'=>PortalBrand::mutate($action,$data),
            default=>new \WP_Error('invalid','Operação não reconhecida.')
        };
    }
    public static function input(string $label,string $name,$value='',string $type='text',string $extra=''): string {return '<label><span>'.esc_html($label).'</span><input type="'.esc_attr($type).'" name="'.esc_attr($name).'" value="'.esc_attr($value).'" '.$extra.'></label>';}
    public static function textarea(string $label,string $name,string $value='',int $rows=5,string $extra=''): string {return '<label><span>'.esc_html($label).'</span><textarea name="'.esc_attr($name).'" rows="'.$rows.'" '.$extra.'>'.esc_textarea($value).'</textarea></label>';}
    public static function select(string $label,string $name,array $options,$value='',string $extra=''): string {$out='<label><span>'.esc_html($label).'</span><select name="'.esc_attr($name).'" '.$extra.'>';foreach($options as $key=>$text){$out.='<option value="'.esc_attr($key).'" '.selected((string)$value,(string)$key,false).'>'.esc_html($text).'</option>';}return $out.'</select></label>';}
    public static function button(string $text,string $class=''): string {return '<button class="np-button '.esc_attr($class).'" type="submit">'.esc_html($text).'</button>';}
    public static function image_field(string $label,string $name,int $id=0): string {return '<div class="np-image-field">'.($id?wp_get_attachment_image($id,'medium',false,['loading'=>'lazy']):'').'<label><span>'.esc_html($label).'</span><input type="file" name="'.esc_attr($name).'" accept="image/jpeg,image/png,image/webp"></label><small>JPG, PNG ou WebP, até '.esc_html(size_format(min(8*MB_IN_BYTES,wp_max_upload_size()))).'.</small>'.($id?'<label class="np-check"><input type="checkbox" name="remove_'.esc_attr($name).'" value="1"> Remover imagem atual</label>':'').'</div>';}
    public static function upload(string $key,int $post=0) {
        if(empty($_FILES[$key]['name'])){return 0;}$f=$_FILES[$key];if(!is_array($f)||!is_string($f['name']??null)||!is_string($f['tmp_name']??null)||(int)($f['error']??1)!==UPLOAD_ERR_OK||(int)($f['size']??0)>min(8*MB_IN_BYTES,wp_max_upload_size())){return new \WP_Error('image','Envie uma imagem válida de até 8 MB.');}
        require_once ABSPATH.'wp-admin/includes/file.php';require_once ABSPATH.'wp-admin/includes/media.php';require_once ABSPATH.'wp-admin/includes/image.php';
        $mimes=['jpg|jpeg|jpe'=>'image/jpeg','png'=>'image/png','webp'=>'image/webp'];$type=wp_check_filetype_and_ext($f['tmp_name'],$f['name'],$mimes);
        if(empty($type['type'])||!in_array($type['type'],$mimes,true)||!wp_getimagesize($f['tmp_name'])){return new \WP_Error('image','Use uma imagem JPG, PNG ou WebP.');}
        return media_handle_upload($key,$post,[],['test_form'=>false,'mimes'=>$mimes]);
    }
    public static function label_tables(string $html): string {
        return preg_replace_callback('/<table\b[^>]*>.*?<\/table>/s',static function($match){$table=$match[0];preg_match_all('/<th\b[^>]*>(.*?)<\/th>/s',$table,$heads);$labels=array_map('wp_strip_all_tags',$heads[1]);return preg_replace_callback('/<tr\b[^>]*>.*?<\/tr>/s',static function($row)use($labels){if(str_contains($row[0],'<th')){return $row[0];}$index=0;return preg_replace_callback('/<td\b([^>]*)>/',static function($cell)use(&$index,$labels){$label=$labels[$index++]??'';return '<td'.$cell[1].' data-label="'.esc_attr($label).'">';},$row[0]);},$table);},$html)??$html;
    }
    public static function render(): string {
        self::$context=true;
        if(!is_user_logged_in()){return '<section class="nexo-portal np-access"><h1>Área da equipe</h1><p>Entre com sua conta para gerenciar a plataforma.</p><a class="np-button" href="'.esc_url(add_query_arg('redirect_to',self::url(),nexo_page_url('login'))).'">Entrar na gestão</a></section>';}
        if(!self::allowed()){return '<section class="nexo-portal np-access"><h1>Seu espaço de aprendizagem</h1><p>Esta área é reservada à equipe. Seus cursos estão na área do aluno.</p><a class="np-button" href="'.esc_url(nexo_page_url('account')).'">Meus cursos</a></section>';}
        $view=sanitize_key(self::text($_GET,'nexo_view'))?:'overview';$nav=['overview'=>'Visão geral','courses'=>'Cursos','content'=>'Conteúdos e testes','students'=>'Alunos e matrículas','classroom'=>'Aulas e chamadas','sales'=>'Vendas','orders'=>'Pedidos','products'=>'Produtos'];
        if(self::brand()){$nav['brand']='Identidade visual';$nav['home']='Página inicial';}if(current_user_can('manage_options')){$nav['team']='Equipe e acessos';}
        if(!isset($nav[$view])){$view='overview';}
        $settings=nexo_settings();$logo=$settings['logo_id']?wp_get_attachment_image($settings['logo_id'],'medium',false,['alt'=>$settings['brand_name']]):'<span class="np-logo-mark" aria-hidden="true">✧</span>';
        $out='<div class="nexo-portal" style="'.esc_attr(nexo_brand_styles()).'"><header class="np-top"><a class="np-brand" href="'.esc_url(self::url()).'">'.$logo.'<span>'.esc_html($settings['brand_name']).'<small>Área de gestão</small></span></a><div class="np-user"><span>'.esc_html(wp_get_current_user()->display_name).'</span><a href="'.esc_url(wp_logout_url(nexo_page_url('login'))).'">Sair</a></div></header><div class="np-layout"><details class="np-navigation" open><summary>Menu da gestão <span aria-hidden="true">☰</span></summary><nav aria-label="Gestão da plataforma">';
        foreach($nav as $key=>$label){$out.='<a href="'.esc_url(self::url($key)).'" '.($key===$view?'aria-current="page"':'').'>'.esc_html($label).'</a>';}
        $out.='<a class="np-visit" href="'.esc_url(nexo_page_url('catalog')).'">Ver catálogo ↗</a></nav></details><section class="np-workspace" aria-labelledby="np-page-title"><header class="np-page-heading"><span class="np-eyebrow">SEU ESPAÇO DE GESTÃO</span><h1 id="np-page-title">'.esc_html($nav[$view]).'</h1></header>';
        $notice=get_transient('nexo_portal_flash_'.get_current_user_id());if(is_array($notice)){delete_transient('nexo_portal_flash_'.get_current_user_id());$out.='<div class="np-notice '.(!empty($notice['error'])?'np-error':'').'" role="status">'.esc_html($notice['message']).'</div>';}
        ob_start();switch($view){case 'courses':PortalCourses::render();break;case 'content':PortalCourses::content();break;case 'students':PortalPeople::render();break;case 'team':PortalPeople::team();break;case 'classroom':Classroom::render(self::id($_GET,'course_id'),self::courses());break;case 'sales':case 'orders':case 'products':PortalSales::render($view);break;case 'brand':case 'home':PortalBrand::render($view);break;default:self::overview();}
        $out.=self::label_tables(ob_get_clean());return $out.'</section></div></div>';
    }
    private static function overview(): void {
        $courses=self::courses();$users=[];$enrollments=0;$sessions=0;foreach($courses as $course){$active=Calendar::enrolled($course->ID);$users+=$active;$enrollments+=count($active);$sessions+=count(Calendar::sessions($course->ID,false));}
        echo '<p class="np-lead">Cuide dos cursos, das pessoas e das inscrições em um só lugar.</p><div class="np-kpis">';foreach(['Cursos'=>count($courses),'Alunos ativos'=>count($users),'Matrículas ativas'=>$enrollments,'Encontros presenciais'=>$sessions] as $label=>$value){echo '<article class="np-kpi"><span>'.esc_html($label).'</span><strong>'.absint($value).'</strong></article>';}echo '</div><div class="np-actions"><a class="np-button" href="'.esc_url(self::url('courses',self::all_courses()?['editor'=>'new']:[])).'">'.(self::all_courses()?'Criar curso':'Gerenciar cursos').'</a><a class="np-button np-secondary" href="'.esc_url(self::url('orders')).'">Acompanhar pedidos</a></div>';
        foreach($courses as $course){if(get_post_meta($course->ID,'_nexo_mode',true)!=='erp'){continue;}echo '<section class="np-card"><h2>'.esc_html($course->post_title).'</h2>';Classroom::engagement(Calendar::summary($course->ID));echo '<a class="np-button np-secondary" href="'.esc_url(self::url('classroom',['course_id'=>$course->ID])).'">Abrir aulas e chamada</a></section>';}
        if(!$courses){echo '<div class="np-card"><h2>Vamos começar</h2><p>'.(self::all_courses()?'Crie seu primeiro curso e prepare as inscrições.':'Quando um curso for atribuído à sua conta, ele aparecerá aqui.').'</p></div>';}
    }
}
