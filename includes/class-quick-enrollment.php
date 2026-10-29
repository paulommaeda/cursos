<?php
/** Context-bound free-course registration and login, directly on the course page. */
namespace NexoCursos;
defined('ABSPATH') || exit;
final class QuickEnrollment {
    private const COOKIE='nexo_quick_guest';
    private static ?\WP_Error $error=null;
    private static string $view='';
    private static array $values=[];
    public static function boot(): void {add_action('template_redirect',[self::class,'dispatch'],4);}
    private static function token(): string {$v=$_COOKIE[self::COOKIE]??'';return is_string($v)&&preg_match('/^[A-Za-z0-9]{48}$/D',$v)?$v:'';}
    private static function prepare_guest(): void {
        if(is_user_logged_in()||self::token()||headers_sent()){return;}$token=wp_generate_password(48,false,false);
        setcookie(self::COOKIE,$token,['expires'=>time()+HOUR_IN_SECONDS,'path'=>defined('COOKIEPATH')&&COOKIEPATH?COOKIEPATH:'/','secure'=>is_ssl(),'httponly'=>true,'samesite'=>'Lax']);$_COOKIE[self::COOKIE]=$token;
    }
    private static function nonce_action(string $action,int $course): string {
        $guest=is_user_logged_in()?'':':'.hash_hmac('sha256',self::token(),wp_salt('nonce'));
        return 'nexo_quick_'.$action.'_'.$course.$guest;
    }
    public static function url(int $course,string $view='register'): string {
        $url=get_permalink($course);if($view==='login'){$url=add_query_arg('nexo_quick_view','login',$url);}return $url.'#nexo-inscricao';
    }
    private static function text(array $data,string $key): string {return isset($data[$key])&&is_string($data[$key])?sanitize_text_field(wp_unslash($data[$key])):'';}
    private static function fail(int $status,string $message,int $course=0): void {
        $back=$course&&get_post_type($course)==='nexo_course'?get_permalink($course):nexo_page_url('catalog');
        wp_die(esc_html($message).'<p><a href="'.esc_url($back).'">Voltar ao curso</a></p>','Inscrição gratuita',['response'=>$status]);
    }
    public static function dispatch(): void {
        $post=($_SERVER['REQUEST_METHOD']??'GET')==='POST';$requested=$post&&!empty($_POST['nexo_quick_action']);
        if(!$requested){$p=get_post();$embedded=$p instanceof \WP_Post&&has_shortcode($p->post_content,'nexo_course');if((is_singular('nexo_course')&&Commerce::is_free((int)get_queried_object_id()))||$embedded){if(!defined('DONOTCACHEPAGE')){define('DONOTCACHEPAGE',true);}nocache_headers();self::prepare_guest();}return;}
        if(!defined('DONOTCACHEPAGE')){define('DONOTCACHEPAGE',true);}nocache_headers();
        $course=absint(self::text($_POST,'nexo_quick_course'));$action=self::text($_POST,'nexo_quick_action');
        if(!is_singular('nexo_course')||(int)get_queried_object_id()!==$course||!in_array($action,['register','login','join'],true)){self::fail(403,'A inscrição precisa ser enviada pela página do curso escolhido.',$course);}
        if((!is_user_logged_in()&&!self::token())||!wp_verify_nonce(self::text($_POST,'_nexo_quick_nonce'),self::nonce_action($action,$course))){self::fail(403,'Sua sessão de inscrição expirou. Atualize a página do curso e tente novamente.',$course);}
        if(!Commerce::is_free($course)){self::fail(403,'Este curso não está disponível para matrícula gratuita no momento.',$course);}
        self::$view=$action==='login'?'login':'register';self::$values=['full_name'=>self::text($_POST,'full_name'),'user_email'=>self::text($_POST,'user_email')];
        $created=false;$lock='';$user=null;
        if(is_user_logged_in()){$user=wp_get_current_user();}
        elseif($action==='join'){self::fail(403,'Entre ou crie sua conta para confirmar a matrícula.',$course);}
        elseif($action==='login'){
            if(Commerce::auth_rate_limit('login',20)){self::$error=new \WP_Error('limit','Aguarde alguns minutos antes de tentar novamente.');}
            elseif(!is_string($_POST['user_email']??null)||!is_string($_POST['user_password']??null)){self::$error=new \WP_Error('credentials','Confira seu e-mail e senha e tente novamente.');}
            else{$result=wp_signon(['user_login'=>self::$values['user_email'],'user_password'=>wp_unslash($_POST['user_password']),'remember'=>!empty($_POST['rememberme'])],is_ssl());if(is_wp_error($result)){self::$error=new \WP_Error('credentials','Confira seu e-mail e senha e tente novamente.');}else{$user=$result;wp_set_current_user($user->ID);}}
        }else{
            if(Commerce::auth_rate_limit('register',8)){self::$error=new \WP_Error('limit','Aguarde alguns minutos antes de tentar novamente.');}
            elseif(!is_string($_POST['user_password']??null)){self::$error=new \WP_Error('password','Use uma senha de pelo menos 8 caracteres.');}
            else{
                $lock='nexo_quick_signup_'.hash_hmac('sha256',strtolower(sanitize_email(self::$values['user_email'])),wp_salt('nonce'));
                if(!add_option($lock,time(),'','no')){if((int)get_option($lock)<time()-90){delete_option($lock);}self::$error=new \WP_Error('registration','Este cadastro está sendo processado. Aguarde e tente entrar na sua conta.');$lock='';}
                else{$password=wp_unslash($_POST['user_password']);$result=Commerce::create_student(['full_name'=>self::$values['full_name'],'user_email'=>self::$values['user_email'],'user_password'=>$password,'password_confirm'=>$password,'company_website'=>self::text($_POST,'company_website')]);
                    if(is_wp_error($result)){$messages=['email'=>'Informe seu nome e um e-mail válido.','password'=>'Use uma senha de 8 a 128 caracteres.','registration'=>'Não foi possível criar a conta. Confira os dados. Se já usa este e-mail, escolha “Já tenho conta” para entrar e se matricular.'];self::$error=new \WP_Error($result->get_error_code(),$messages[$result->get_error_code()]??$messages['registration']);}
                    else{$user=$result;$created=true;wp_set_current_user($user->ID);wp_set_auth_cookie($user->ID,false,is_ssl());}
                }
            }
        }
        if($lock){delete_option($lock);}
        if($user){$result=Commerce::enroll_free($course,$user->ID);if(is_wp_error($result)){self::$error=new \WP_Error('enrollment',$created?'Sua conta foi criada, mas não conseguimos concluir a matrícula. Confirme sua participação para tentar de novo.':'Não conseguimos concluir a matrícula. Confirme sua participação para tentar novamente.');}
            else{if($created){wp_new_user_notification($user->ID,null,'user');}set_transient('nexo_front_feedback_'.$user->ID,['message'=>'Matrícula confirmada! Você já está inscrito em “'.get_the_title($course).'”.','error'=>false],90);wp_safe_redirect(add_query_arg('nexo_feedback',1,get_permalink($course)));exit;}}
        status_header(422);
    }
    public static function panel(int $course): string {
        if(!Commerce::is_free($course)){return '';}
        $title=get_the_title($course);$out='<section id="nexo-inscricao" class="nexo-quick-enrollment" aria-labelledby="nexo-quick-title-'.$course.'"><span class="nexo-quick-badge">Curso gratuito</span>';
        if(self::$error){$out.='<div role="alert" class="nexo-notice nexo-notice-error">'.esc_html(self::$error->get_error_message()).'</div>';}
        if(is_user_logged_in()){
            if(Core::has_access($course)){$out.='<h2 id="nexo-quick-title-'.$course.'">Você já tem acesso</h2><a class="nexo-button nexo-button-wide" href="'.esc_url(get_permalink($course)).'">Acessar curso</a>';}
            else{$out.='<h2 id="nexo-quick-title-'.$course.'">Confirme sua matrícula</h2><p>Participar de <strong>'.esc_html($title).'</strong> é gratuito.</p><p class="nexo-quick-account">Sua conta: '.esc_html(wp_get_current_user()->user_email).'</p>'.self::form_start($course,'join').'<button class="nexo-button nexo-button-wide" type="submit">Confirmar matrícula gratuita</button></form>';}
            return $out.'</section>';
        }
        $view=self::$view?:((isset($_GET['nexo_quick_view'])&&$_GET['nexo_quick_view']==='login')?'login':'register');$register=$view==='register';
        $out.='<h2 id="nexo-quick-title-'.$course.'">'.($register?'Cadastro rápido':'Entre para se matricular').'</h2><p class="nexo-quick-intent">Sua matrícula será em <strong>'.esc_html($title).'</strong>.</p><nav class="nexo-quick-switch" aria-label="Forma de inscrição"><a href="'.esc_url(self::url($course)).'" '.($register?'aria-current="page"':'').'>Criar conta</a><a href="'.esc_url(self::url($course,'login')).'" '.(!$register?'aria-current="page"':'').'>Já tenho conta</a></nav>'.self::form_start($course,$view);
        if($register){$out.='<p><label for="nexo-quick-name-'.$course.'">Nome completo</label><input id="nexo-quick-name-'.$course.'" name="full_name" autocomplete="name" maxlength="120" value="'.esc_attr(self::$values['full_name']??'').'" required></p>';}
        $out.='<p><label for="nexo-quick-email-'.$course.'">'.($register?'E-mail':'E-mail ou nome de usuário').'</label><input id="nexo-quick-email-'.$course.'" name="user_email" type="'.($register?'email':'text').'" autocomplete="username" maxlength="254" value="'.esc_attr(self::$values['user_email']??'').'" required></p><p><label for="nexo-quick-password-'.$course.'">Senha</label><span class="nexo-quick-password"><input id="nexo-quick-password-'.$course.'" name="user_password" type="password" autocomplete="'.($register?'new-password':'current-password').'" '.($register?'minlength="8"':'').' maxlength="128" required><button type="button" class="nexo-quick-reveal" data-password="nexo-quick-password-'.$course.'" aria-controls="nexo-quick-password-'.$course.'" aria-pressed="false" hidden>Mostrar</button></span></p>';
        if($register){$out.='<div class="nexo-auth-trap" aria-hidden="true"><label>Deixe vazio<input name="company_website" tabindex="-1" autocomplete="off"></label></div>';}
        else{$out.='<p class="nexo-auth-remember"><label><input type="checkbox" name="rememberme" value="1"> Manter conectado</label></p>';}
        $out.='<button class="nexo-button nexo-button-wide" type="submit">'.($register?'Cadastrar e me matricular':'Entrar e me matricular').'</button><p class="nexo-quick-help">'.($register?'Ao concluir, sua conta será criada e a matrícula neste curso será confirmada automaticamente.':'Depois de entrar, sua matrícula neste curso será confirmada automaticamente.').'</p></form>';
        if(!$register){$out.='<a class="nexo-quick-forgot" href="'.esc_url(wp_lostpassword_url(self::url($course,'login'))).'">Esqueci minha senha</a>';}
        return $out.'</section>';
    }
    private static function form_start(int $course,string $action): string {
        return '<form method="post" action="'.esc_url(self::url($course,$action==='login'?'login':'register')).'" class="nexo-student-auth nexo-quick-form"><input type="hidden" name="nexo_quick_action" value="'.esc_attr($action).'"><input type="hidden" name="nexo_quick_course" value="'.$course.'">'.wp_nonce_field(self::nonce_action($action,$course),'_nexo_quick_nonce',false,false);
    }
}
