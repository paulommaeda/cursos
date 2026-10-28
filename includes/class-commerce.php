<?php
/** Course pricing, automatic products and student authentication. */
namespace NexoCursos;
defined('ABSPATH') || exit;
final class Commerce {
    private static array $syncing = [];
    public static function boot(): void {
        add_action('save_post', [self::class,'save_course'], 40, 2);
        add_action('trashed_post', [self::class,'hide_product']);
        foreach (['nexo_student_login'=>'student_login','nexo_student_register'=>'student_register'] as $action=>$method) {
            add_action('admin_post_'.$action,[self::class,$method]);
            add_action('admin_post_nopriv_'.$action,[self::class,$method]);
        }
        add_action('admin_post_nexo_join_free',[self::class,'join_free']);
        add_action('admin_post_nopriv_nexo_join_free',[self::class,'join_free']);
        add_action('admin_notices',[self::class,'product_notice']);
        add_shortcode('nexo_navigation',[self::class,'navigation']);
    }
    public static function pricing_mode(int $id): string {
        $mode=get_post_meta($id,'_nexo_enrollment_mode',true);
        if(in_array($mode,['free','paid','manual'],true)){return $mode;}
        $p=function_exists('wc_get_product')?wc_get_product(absint(get_post_meta($id,'_nexo_product_id',true))):false;
        if($p && $p->get_price()!==''){return (float)$p->get_price()===0.0?'free':'paid';}
        return 'manual';
    }
    public static function is_free(int $id): bool {
        return $id>0 && get_post_type($id)==='nexo_course' && get_post_status($id)==='publish' && !post_password_required($id) && self::pricing_mode($id)==='free';
    }
    public static function entry_url(int $id): string {
        return add_query_arg(['nexo_free_course'=>$id],nexo_page_url('login'));
    }
    public static function enroll_free(int $id,int $user_id) {
        if(!self::is_free($id) || !$user_id || !get_userdata($user_id)){return new \WP_Error('nexo_not_free','Este curso não está disponível para matrícula gratuita.');}
        return Core::enroll($user_id,$id);
    }
    public static function save_course(int $id,\WP_Post $post): void {
        if($post->post_type!=='nexo_course'||wp_is_post_revision($id)||wp_is_post_autosave($id)||$post->post_status==='auto-draft'){return;}
        self::sync_product($id);
    }
    /** Only products created by this module are automatically overwritten. */
    public static function sync_product(int $id) {
        if(isset(self::$syncing[$id]) || get_post_type($id)!=='nexo_course'){return false;}
        $post=get_post($id);
        if(!$post || !class_exists('WC_Product_Simple')){return false;}
        $lock='nexo_product_sync_lock_'.$id;
        if(!add_option($lock,time(),'','no')){
            if((int)get_option($lock)>time()-60){return false;}
            delete_option($lock);
            if(!add_option($lock,time(),'','no')){return false;}
        }
        self::$syncing[$id]=true;
        try {
            $linked=absint(get_post_meta($id,'_nexo_product_id',true));
            $p=$linked?wc_get_product($linked):false;
            $owned=$p && (int)$p->get_meta('_nexo_managed_course_id',true)===$id;
            if(self::pricing_mode($id)!=='paid') {
                if($owned && $p->get_status()==='publish'){$p->set_status('draft');$p->save();}
                delete_post_meta($id,'_nexo_product_sync_error');
                return $linked;
            }
            // Preserve externally linked products, variations and bundles from older installs.
            if($p && !$owned){return $linked;}
            $price=get_post_meta($id,'_nexo_price',true);
            $valid_price=is_numeric($price)&&(float)$price>0;
            if(!$p){$p=new \WC_Product_Simple();$p->update_meta_data('_nexo_managed_course_id',$id);}
            $p->set_name($post->post_title ?: 'Curso');
            $p->set_description(wp_kses_post($post->post_content));
            $p->set_short_description(wp_kses_post($post->post_excerpt));
            $p->set_virtual(true);$p->set_sold_individually(true);
            $p->set_catalog_visibility('visible');$p->set_reviews_allowed(false);
            $p->set_image_id((int)get_post_thumbnail_id($id));
            $p->set_regular_price($valid_price?(string)$price:'');$p->set_sale_price('');
            $p->set_status($post->post_status==='publish' && $valid_price && !$post->post_password?'publish':'draft');
            $product_id=$p->save();
            if(!$product_id){throw new \RuntimeException('Product was not saved');}
            update_post_meta($id,'_nexo_product_id',$product_id);
            if($valid_price){delete_post_meta($id,'_nexo_product_sync_error');}
            else{update_post_meta($id,'_nexo_product_sync_error','Informe um preço maior que zero para disponibilizar a venda. O produto foi salvo como rascunho.');}
            return $product_id;
        }catch(\Throwable $e){
            update_post_meta($id,'_nexo_product_sync_error','Não foi possível sincronizar o produto. Confira o WooCommerce e salve o curso novamente.');
            return new \WP_Error('nexo_product_sync','Não foi possível sincronizar o produto.');
        }finally{unset(self::$syncing[$id]);delete_option($lock);}
    }
    public static function hide_product(int $id): void {
        if(get_post_type($id)==='nexo_course'){self::sync_product($id);}
    }
    public static function product_notice(): void {
        if(!current_user_can('manage_options')){return;}
        $id=absint($_GET['post']??0);
        if(!$id || get_post_type($id)!=='nexo_course'){return;}
        $message=get_post_meta($id,'_nexo_product_sync_error',true);
        if(self::pricing_mode($id)==='paid'&&!class_exists('WooCommerce')){$message='Ative o WooCommerce para criar o produto e vender este curso.';}
        if($message){echo '<div class="notice notice-warning"><p>'.esc_html($message).'</p></div>';}
    }
    public static function navigation($atts=[]): string {
        $atts=shortcode_atts(['brand'=>'yes'],$atts,'nexo_navigation');
        $links=['Início'=>home_url('/'),'Cursos'=>nexo_page_url('catalog'),'Nosso propósito'=>home_url('/#proposito')];
        if(is_user_logged_in()){$links['Meus cursos']=nexo_page_url('account');}
        if(current_user_can('manage_options')||current_user_can('nexo_manage_assigned')){$links['Painel do gerente']=nexo_page_url('dashboard');}
        $out='<nav class="nexo-site-navigation" aria-label="Navegação principal">';
        if($atts['brand']==='yes'){$out.='<a class="nexo-navigation-brand" href="'.esc_url(home_url('/')).'">'.esc_html(nexo_settings()['brand_name']).'</a>';}
        $out.='<details class="nexo-navigation-menu" open><summary>Menu <span aria-hidden="true">☰</span></summary><div class="nexo-navigation-links">';
        foreach($links as $label=>$url){$out.='<a href="'.esc_url($url).'">'.esc_html($label).'</a>';}
        if(function_exists('wc_get_cart_url')){$out.='<a class="nexo-navigation-cart" href="'.esc_url(wc_get_cart_url()).'">Carrinho</a>';}
        if(is_user_logged_in()){
            if(function_exists('wc_get_page_permalink')){$out.='<a href="'.esc_url(wc_get_page_permalink('myaccount')).'">Minha conta</a>';}
            $out.='<a class="nexo-navigation-account" href="'.esc_url(wp_logout_url(home_url('/'))).'">Sair</a>';
        }else{$out.='<a class="nexo-navigation-account" href="'.esc_url(nexo_page_url('login')).'">Entrar / cadastrar <span aria-hidden="true">↗</span></a>';}
        return $out.'</div></details></nav>';
    }
    private static function intent(): int {
        return is_scalar($_REQUEST['nexo_free_course']??null)?absint($_REQUEST['nexo_free_course']):0;
    }
    private static function auth_url(int $id=0,string $view='login'): string {
        return add_query_arg(array_filter(['nexo_free_course'=>$id,'nexo_auth'=>$view==='register'?'register':null]),nexo_page_url('login'));
    }
    public static function auth_screen(): string {
        $id=self::intent();$valid=$id && self::is_free($id);$register=($_GET['nexo_auth']??'')==='register';$settings=nexo_settings();
        if(is_user_logged_in()){
            if($valid && !Core::has_access($id)){
                return '<div class="nexo-app nexo-auth-confirm"><span class="nexo-eyebrow">MATRÍCULA GRATUITA</span><h1>'.esc_html(get_the_title($id)).'</h1><p>Confirme sua participação para começar.</p>'.self::join_form($id).'</div>';
            }
            $target=$valid?get_permalink($id):nexo_page_url('account');
            return '<div class="nexo-app nexo-auth-confirm"><h1>Que bom ter você aqui.</h1><p>Sua conta está conectada. Continue sua jornada de aprendizado.</p><a class="nexo-button" href="'.esc_url($target).'">'.esc_html($valid?'Acessar curso':'Acessar meus cursos').'</a></div>';
        }
        $errors=['credentials'=>'Confira seu e-mail e senha e tente novamente.','registration'=>'Não foi possível criar a conta. Confira os dados. Se já possui conta com este e-mail, entre na sua conta.','password'=>'Use uma senha de pelo menos 8 caracteres e confirme a mesma senha.','email'=>'Informe seu nome e um endereço de e-mail válido.','limit'=>'Aguarde alguns minutos antes de tentar novamente.','course'=>'Este curso não está disponível para matrícula gratuita.'];
        $code=sanitize_key($_GET['nexo_auth_error']??'');
        $out='<div class="nexo-app nexo-login-page" style="'.esc_attr(nexo_brand_styles()).'"><section class="nexo-login-story"><a class="nexo-brand" href="'.esc_url(home_url('/')).'">'.esc_html($settings['brand_name']).'</a><div><span class="nexo-eyebrow">UMA JORNADA COM PROPÓSITO</span><h1>Discipular.<br>Aperfeiçoar.<br>Enviar<span>.</span></h1><p>Cresça no conhecimento da Palavra.<br>Prepare-se para servir e abençoar vidas.</p></div><span>Fé que aprende. Amor que serve.</span></section><section class="nexo-login-form-side"><div class="nexo-login-form-wrap"><span class="nexo-eyebrow">'.($register?'COMECE SUA JORNADA':'BEM-VINDO DE VOLTA').'</span><h2>'.esc_html($register?'Crie sua conta':$settings['login_title']).'</h2>';
        if($valid){$out.='<p class="nexo-free-intent"><strong>Curso gratuito</strong><br>'.esc_html(get_the_title($id)).'<br><small>Sua matrícula será confirmada ao '.($register?'criar a conta':'entrar').'.</small></p>';}
        elseif($id){$out.='<p role="alert" class="nexo-notice">Este curso não está disponível para inscrição gratuita. Você pode acessar sua conta normalmente.</p>';}
        else{$out.='<p class="nexo-muted">'.esc_html($settings['login_message']).'</p>';}
        if(isset($errors[$code])){$out.='<p class="nexo-notice nexo-notice-error" role="alert">'.esc_html($errors[$code]).'</p>';}
        $out.='<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" class="nexo-student-auth"><input type="hidden" name="action" value="'.($register?'nexo_student_register':'nexo_student_login').'">'.wp_nonce_field($register?'nexo_student_register':'nexo_student_login','_nexo_auth_nonce',false,false).'<input type="hidden" name="nexo_free_course" value="'.($valid?$id:0).'">';
        $redirect=isset($_GET['redirect_to'])&&is_string($_GET['redirect_to'])?wp_validate_redirect(esc_url_raw(wp_unslash($_GET['redirect_to'])),nexo_page_url('account')):nexo_page_url('account');
        $out.='<input type="hidden" name="redirect_to" value="'.esc_attr($redirect).'">';
        if($register){$out.='<p><label for="nexo-full-name">Nome completo</label><input id="nexo-full-name" name="full_name" autocomplete="name" required maxlength="120"></p>';}
        $out.='<p><label for="nexo-user-email">'.($register?'E-mail':'E-mail ou nome de usuário').'</label><input id="nexo-user-email" name="user_email" type="'.($register?'email':'text').'" autocomplete="username" required maxlength="254"></p><p><label for="nexo-user-password">Senha</label><input id="nexo-user-password" name="user_password" type="password" autocomplete="'.($register?'new-password':'current-password').'" required '.($register?'minlength="8"':'').' maxlength="128"></p>';
        if($register){$out.='<p><label for="nexo-password-confirm">Confirmar senha</label><input id="nexo-password-confirm" name="password_confirm" type="password" autocomplete="new-password" minlength="8" maxlength="128" required></p><div class="nexo-auth-trap" aria-hidden="true"><label>Deixe vazio<input name="company_website" tabindex="-1" autocomplete="off"></label></div>';}
        else{$out.='<p class="nexo-auth-remember"><label><input type="checkbox" name="rememberme" value="1"> Manter conectado</label></p>';}
        $out.='<button class="nexo-button nexo-button-wide" type="submit">'.($register?($valid?'Criar conta e me inscrever':'Criar minha conta'):($valid?'Entrar e me inscrever':'Entrar na minha conta')).'</button></form><p class="nexo-auth-switch">'.($register?'Já tem uma conta? ':'É sua primeira vez? ').'<a href="'.esc_url(self::auth_url($valid?$id:0,$register?'login':'register')).'">'.($register?'Entrar':'Criar conta gratuita').'</a></p>';
        if(!$register){$out.='<a class="nexo-forgot-password" href="'.esc_url(wp_lostpassword_url(nexo_page_url('login'))).'">Esqueci minha senha</a>';}
        return $out.'<a class="nexo-back nexo-login-back" href="'.esc_url(nexo_page_url('catalog')).'">← Conhecer os cursos</a></div></section></div>';
    }
    public static function join_form(int $id): string {
        return '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="nexo_join_free"><input type="hidden" name="nexo_free_course" value="'.$id.'">'.wp_nonce_field('nexo_join_free_'.$id,'_nexo_auth_nonce',false,false).'<button class="nexo-button nexo-button-wide">Confirmar matrícula gratuita</button></form>';
    }
    private static function check_request(string $action): void {
        if(($_SERVER['REQUEST_METHOD']??'')!=='POST'){wp_die('Ação não permitida.','',['response'=>405]);}
        check_admin_referer($action,'_nexo_auth_nonce');
    }
    private static function rate_limit(string $action,int $max): bool {
        $ip=(string)($_SERVER['REMOTE_ADDR']??'local');
        $key='nexo_auth_'.$action.'_'.hash_hmac('sha256',$ip,wp_salt('nonce'));
        $count=(int)get_transient($key);set_transient($key,$count+1,15*MINUTE_IN_SECONDS);
        return $count >= $max;
    }
    private static function auth_error(string $code,int $id,bool $register=false): void {
        wp_safe_redirect(add_query_arg('nexo_auth_error',$code,self::auth_url($id,$register?'register':'login')));exit;
    }
    private static function destination(int $id): string {
        if($id && self::is_free($id)){return get_permalink($id);}
        $raw=$_POST['redirect_to']??'';
        return is_string($raw)?wp_validate_redirect(esc_url_raw(wp_unslash($raw)),nexo_page_url('account')):nexo_page_url('account');
    }
    public static function student_login(): void {
        self::check_request('nexo_student_login');$id=self::intent();
        if(self::rate_limit('login',20)){self::auth_error('limit',$id);}
        if(!is_string($_POST['user_email']??null)||!is_string($_POST['user_password']??null)){self::auth_error('credentials',$id);}
        $user=wp_signon(['user_login'=>sanitize_text_field(wp_unslash($_POST['user_email']??'')),'user_password'=>(string)wp_unslash($_POST['user_password']??''),'remember'=>!empty($_POST['rememberme'])],is_ssl());
        if(is_wp_error($user)){self::auth_error('credentials',$id);}
        wp_set_current_user($user->ID);
        if($id && is_wp_error(self::enroll_free($id,$user->ID))){self::auth_error('course',$id);}
        wp_safe_redirect(self::destination($id));exit;
    }
    /** Creates only a low-privilege student account, never an email-linked existing account. */
    public static function create_student(array $data) {
        foreach(['full_name','user_email','user_password','password_confirm'] as $field){if(!isset($data[$field])||!is_string($data[$field])){return new \WP_Error('registration','Confira os dados do cadastro.');}}
        $name=sanitize_text_field($data['full_name']??'');$email=sanitize_email($data['user_email']??'');
        $password=$data['user_password']??'';$confirm=$data['password_confirm']??'';
        if(!$name || !is_email($email)){return new \WP_Error('email','Informe nome e e-mail válido.');}
        if(!is_string($password)||strlen($password)<8||strlen($password)>128||$password!==$confirm){return new \WP_Error('password','Confira sua senha.');}
        if(!empty($data['company_website'])||email_exists($email)){return new \WP_Error('registration','Não foi possível criar esta conta.');}
        $login='aluno_'.strtolower(wp_generate_password(16,false,false));
        $errors=apply_filters('registration_errors',new \WP_Error(),$login,$email);
        if($errors instanceof \WP_Error && $errors->has_errors()){return new \WP_Error('registration','Não foi possível criar esta conta.');}
        $id=wp_insert_user(['user_login'=>$login,'user_pass'=>$password,'user_email'=>$email,'display_name'=>wp_html_excerpt($name,120,''),'first_name'=>wp_html_excerpt($name,120,''),'role'=>'subscriber']);
        return is_wp_error($id)?new \WP_Error('registration','Não foi possível criar esta conta.'):get_user_by('id',$id);
    }
    public static function student_register(): void {
        self::check_request('nexo_student_register');$id=self::intent();
        if(is_user_logged_in()){wp_safe_redirect(self::auth_url($id));exit;}
        if(self::rate_limit('register',8)){self::auth_error('limit',$id,true);}
        if($id && !self::is_free($id)){self::auth_error('course',$id,true);}
        $user=self::create_student(wp_unslash($_POST));
        if(is_wp_error($user)){self::auth_error($user->get_error_code(),$id,true);}
        wp_set_current_user($user->ID);wp_set_auth_cookie($user->ID,false,is_ssl());
        wp_new_user_notification($user->ID,null,'user');
        if($id && is_wp_error(self::enroll_free($id,$user->ID))){self::auth_error('course',$id);}
        wp_safe_redirect(self::destination($id));exit;
    }
    public static function join_free(): void {
        $id=self::intent();
        if(!is_user_logged_in()){wp_safe_redirect(self::auth_url($id));exit;}
        self::check_request('nexo_join_free_'.$id);
        $result=self::enroll_free($id,get_current_user_id());
        if(is_wp_error($result)){wp_die(esc_html($result->get_error_message()),'',['response'=>403]);}
        wp_safe_redirect(get_permalink($id));exit;
    }
}
