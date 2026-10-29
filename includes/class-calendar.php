<?php
/** Recurring classroom sessions and per-session attendance. */
namespace NexoCursos;
defined('ABSPATH') || exit;
final class Calendar {
    public const LIMIT=500;
    public static function boot(): void {
        add_action('init',[self::class,'register_type']);
        add_action('save_post',[self::class,'save_fields'],20,2);
        add_action('save_post',[self::class,'generate_on_save'],30,2);
        add_action('add_meta_boxes',[self::class,'meta_boxes']);
        add_action('admin_notices',[self::class,'notices']);
        add_action('delete_user',[self::class,'delete_user_data']);
        add_filter('wp_privacy_personal_data_exporters',[self::class,'exporters']);
        add_filter('wp_privacy_personal_data_erasers',[self::class,'erasers']);
        if(get_option('nexo_session_db_version')!=='1'){self::install();}
    }
    public static function install(): void {
        global $wpdb;require_once ABSPATH.'wp-admin/includes/upgrade.php';$table=self::table();$collate=$wpdb->get_charset_collate();
        dbDelta("CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            session_id bigint(20) unsigned NOT NULL,
            course_id bigint(20) unsigned NOT NULL,
            user_id bigint(20) unsigned NOT NULL,
            status varchar(16) NOT NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY session_student (session_id,user_id),
            KEY course_student (course_id,user_id),
            KEY student_id (user_id)
        ) {$collate};");update_option('nexo_session_db_version','1',false);
    }
    public static function table(): string {global $wpdb;return $wpdb->prefix.'nexo_session_attendance';}
    public static function register_type(): void {
        $caps=array_fill_keys(['edit_post','read_post','delete_post','edit_posts','edit_others_posts','delete_posts','publish_posts','read_private_posts','create_posts'],'manage_options');
        register_post_type('nexo_session',['labels'=>['name'=>'Aulas presenciais','singular_name'=>'Aula presencial'],'public'=>false,'publicly_queryable'=>false,'show_ui'=>false,'show_in_menu'=>false,'show_in_rest'=>false,'exclude_from_search'=>true,'supports'=>['title','editor'],'capabilities'=>$caps,'map_meta_cap'=>false,'rewrite'=>false]);
    }
    public static function date(string $value) {
        if(!preg_match('/^\d{4}-\d{2}-\d{2}$/D',$value)){return false;}
        $date=\DateTimeImmutable::createFromFormat('!Y-m-d',$value,wp_timezone());
        return $date && $date->format('Y-m-d')===$value && (int)$date->format('Y')>0?$date:false;
    }
    public static function config(int $course): array {
        return ['start'=>(string)get_post_meta($course,'_nexo_start_date',true),'end'=>(string)get_post_meta($course,'_nexo_end_date',true),'frequency'=>(string)(get_post_meta($course,'_nexo_frequency',true)?:'none'),'interval'=>(int)(get_post_meta($course,'_nexo_interval',true)?:1),'unit'=>(string)(get_post_meta($course,'_nexo_interval_unit',true)?:'week'),'weekdays'=>(array)get_post_meta($course,'_nexo_weekdays',true),'time'=>(string)get_post_meta($course,'_nexo_session_time',true),'duration'=>(int)(get_post_meta($course,'_nexo_session_duration',true)?:90),'automatic'=>(bool)get_post_meta($course,'_nexo_generate_sessions',true)];
    }
    public static function validate(array $raw) {
        $c=['start'=>(string)($raw['start']??''),'end'=>(string)($raw['end']??''),'frequency'=>sanitize_key($raw['frequency']??'none'),'interval'=>max(1,min(52,absint($raw['interval']??1))),'unit'=>sanitize_key($raw['unit']??'week'),'weekdays'=>array_values(array_unique(array_filter(array_map('absint',(array)($raw['weekdays']??[])),static fn($d)=>$d<=6))),'time'=>(string)($raw['time']??''),'duration'=>max(15,min(720,absint($raw['duration']??90))),'automatic'=>!empty($raw['automatic'])];
        if(!in_array($c['frequency'],['none','once','daily','weekdays','weekly','biweekly','monthly','custom'],true)||!in_array($c['unit'],['day','week','month'],true)){return new \WP_Error('schedule_frequency','Escolha uma frequência válida.');}
        if(($c['start']&&!self::date($c['start']))||($c['end']&&!self::date($c['end']))){return new \WP_Error('schedule_date','Informe datas válidas.');}
        if($c['end']&&(!$c['start']||$c['end']<$c['start'])){return new \WP_Error('schedule_range','A data final deve ser igual ou posterior à data inicial.');}
        if($c['time']&&!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/D',$c['time'])){return new \WP_Error('schedule_time','Informe um horário válido.');}
        if($c['automatic']&&$c['frequency']!=='none'&&(!$c['start']||(!$c['end']&&$c['frequency']!=='once'))){return new \WP_Error('schedule_required','Preencha data inicial e final para gerar as aulas.');}
        if($c['start']&&$c['end']&&(int)self::date($c['start'])->diff(self::date($c['end']))->days>3660){return new \WP_Error('schedule_long','Use um período de no máximo dez anos e até 500 encontros.');}
        return $c;
    }
    public static function dates(array $raw) {
        $c=self::validate($raw);if(is_wp_error($c)){return $c;}
        if(!$c['start']||$c['frequency']==='none'){return [];}
        $start=self::date($c['start']);$end=self::date($c['end']?:$c['start']);$frequency=$c['frequency'];$dates=[];
        if($frequency==='once'){return [$c['start']];}
        $unit=$frequency==='monthly'?'month':($frequency==='custom'?$c['unit']:($frequency==='weekly'||$frequency==='biweekly'?'week':'day'));
        $interval=$frequency==='biweekly'?2:($frequency==='custom'?$c['interval']:1);
        if($unit==='month'){
            $anchor=$start->modify('first day of this month');$day=(int)$start->format('j');
            for($i=0;$i<=120;$i+=$interval){$base=$anchor->modify('+'.$i.' months');$date=$base->setDate((int)$base->format('Y'),(int)$base->format('n'),min($day,(int)$base->format('t')));if($date>$end){break;}$dates[]=$date->format('Y-m-d');}
        }else{
            $weekdays=$c['weekdays']?:[(int)$start->format('w')];$offset=(int)$start->format('N')-1;
            for($date=$start,$i=0;$date<=$end;$date=$date->modify('+1 day'),$i++){
                $take=$unit==='week'?(intdiv($i+$offset,7)%$interval===0&&in_array((int)$date->format('w'),$weekdays,true)):($i%$interval===0);
                if($frequency==='weekdays'){$take=(int)$date->format('N')<=5;}
                if($take){$dates[]=$date->format('Y-m-d');if(count($dates)>self::LIMIT){return new \WP_Error('schedule_limit','A programação excede 500 aulas. Reduza o período ou ajuste a frequência.');}}
            }
        }
        return $dates;
    }
    public static function save_fields(int $id,\WP_Post $post): void {
        if($post->post_type!=='nexo_course'||!current_user_can('manage_options')||!current_user_can('edit_post',$id)||wp_is_post_revision($id)||wp_is_post_autosave($id)||empty($_POST['nexo_schedule_nonce'])||!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nexo_schedule_nonce'])),'nexo_schedule')){return;}
        $raw=['start'=>sanitize_text_field(wp_unslash($_POST['nexo_start_date']??'')),'end'=>sanitize_text_field(wp_unslash($_POST['nexo_end_date']??'')),'frequency'=>sanitize_key($_POST['nexo_frequency']??'none'),'interval'=>absint($_POST['nexo_interval']??1),'unit'=>sanitize_key($_POST['nexo_interval_unit']??'week'),'weekdays'=>$_POST['nexo_weekdays']??[],'time'=>sanitize_text_field(wp_unslash($_POST['nexo_session_time']??'')),'duration'=>absint($_POST['nexo_session_duration']??90),'automatic'=>!empty($_POST['nexo_generate_sessions'])];
        if(get_post_meta($id,'_nexo_mode',true)!=='erp'){$raw['automatic']=false;}
        $c=self::validate($raw);$dates=is_wp_error($c)?$c:self::dates($c);
        if(is_wp_error($dates)){update_post_meta($id,'_nexo_schedule_error',$dates->get_error_message());return;}
        foreach(['start'=>'start_date','end'=>'end_date','frequency'=>'frequency','interval'=>'interval','unit'=>'interval_unit','weekdays'=>'weekdays','time'=>'session_time','duration'=>'session_duration','automatic'=>'generate_sessions'] as $key=>$meta){update_post_meta($id,'_nexo_'.$meta,$c[$key]);}
        update_post_meta($id,'_nexo_schedule_config',$c);delete_post_meta($id,'_nexo_schedule_error');
    }
    public static function generate_on_save(int $id,\WP_Post $post): void {
        if($post->post_type!=='nexo_course'||wp_is_post_revision($id)||wp_is_post_autosave($id)||in_array($post->post_status,['trash','auto-draft'],true)||get_post_meta($id,'_nexo_schedule_error',true)){return;}
        $c=self::config($id);if(get_post_meta($id,'_nexo_mode',true)!=='erp'||!$c['automatic']||$c['frequency']==='none'){return;}
        $fingerprint=hash('sha256',wp_json_encode($c));if(get_post_meta($id,'_nexo_schedule_fingerprint',true)===$fingerprint){return;}
        $result=self::synchronize($id,$c);
        if(is_wp_error($result)){update_post_meta($id,'_nexo_schedule_error',$result->get_error_message());}
        else{update_post_meta($id,'_nexo_schedule_fingerprint',$fingerprint);update_post_meta($id,'_nexo_schedule_result',$result);}
    }
    public static function sessions(int $course,bool $cancelled=true): array {
        $posts=get_posts(['post_type'=>'nexo_session','post_status'=>['publish','private','draft'],'numberposts'=>-1,'meta_key'=>'_nexo_course_id','meta_value'=>$course,'orderby'=>'ID','order'=>'ASC']);
        $out=[];foreach($posts as $p){$date=(string)get_post_meta($p->ID,'_nexo_session_date',true);if(!self::date($date)){continue;}$status=get_post_meta($p->ID,'_nexo_session_status',true)?:'scheduled';if(!$cancelled&&$status==='cancelled'){continue;}$out[]=['id'=>(int)$p->ID,'title'=>$p->post_title,'date'=>$date,'time'=>(string)get_post_meta($p->ID,'_nexo_session_time',true),'duration'=>(int)get_post_meta($p->ID,'_nexo_session_duration',true),'status'=>$status,'generated'=>(bool)get_post_meta($p->ID,'_nexo_generated',true)];}
        usort($out,static fn($a,$b)=>strcmp($a['date'].' '.$a['time'],$b['date'].' '.$b['time'])?:($a['id']<=>$b['id']));return $out;
    }
    public static function session(int $id) {
        if(get_post_type($id)!=='nexo_session'||!in_array(get_post_status($id),['publish','private','draft'],true)){return false;}
        $course=(int)get_post_meta($id,'_nexo_course_id',true);foreach(self::sessions($course) as $s){if($s['id']===$id){$s['course']=$course;return $s;}}return false;
    }
    public static function marks(int $course): array {global $wpdb;return (array)$wpdb->get_results($wpdb->prepare('SELECT * FROM '.self::table().' WHERE course_id=%d ORDER BY session_id,user_id',$course));}
    public static function has_marks(int $session): bool {global $wpdb;return (bool)$wpdb->get_var($wpdb->prepare('SELECT id FROM '.self::table().' WHERE session_id=%d LIMIT 1',$session));}
    /** Reconcile generated dates; historical or already-called sessions never disappear. */
    public static function synchronize(int $course,?array $config=null) {
        if(get_post_type($course)!=='nexo_course'||get_post_meta($course,'_nexo_mode',true)!=='erp'){return new \WP_Error('schedule_course','A geração de aulas é exclusiva do formato gestão/presencial.');}
        $c=self::validate($config??self::config($course));if(is_wp_error($c)){return $c;}$dates=self::dates($c);if(is_wp_error($dates)){return $dates;}
        $lock='nexo_session_sync_lock_'.$course;
        if(!add_option($lock,time(),'','no')){if((int)get_option($lock)>time()-90){return new \WP_Error('schedule_busy','A programação já está sendo atualizada. Aguarde e tente novamente.');}delete_option($lock);if(!add_option($lock,time(),'','no')){return new \WP_Error('schedule_busy','Aguarde e tente novamente.');}}
        $result=['created'=>0,'retained'=>0,'cancelled'=>0,'protected'=>0];
        try{
            $existing=self::sessions($course);$map=[];
            foreach($existing as $s){if($s['generated']&&!isset($map[$s['date']])){$map[$s['date']]=$s;}}
            foreach($dates as $index=>$date){
                if(isset($map[$date])){$s=$map[$date];$result['retained']++;if($s['status']==='cancelled'&&get_post_meta($s['id'],'_nexo_cancel_reason',true)==='schedule'){update_post_meta($s['id'],'_nexo_session_status','scheduled');delete_post_meta($s['id'],'_nexo_cancel_reason');}if($date>=wp_date('Y-m-d')&&!self::has_marks($s['id'])){update_post_meta($s['id'],'_nexo_session_time',$c['time']);update_post_meta($s['id'],'_nexo_session_duration',$c['duration']);}continue;}
                $title='Aula '.str_pad((string)($index+1),2,'0',STR_PAD_LEFT).' — '.self::date($date)->format('d/m/Y');
                $id=wp_insert_post(['post_type'=>'nexo_session','post_status'=>'publish','post_title'=>$title,'meta_input'=>['_nexo_course_id'=>$course,'_nexo_session_date'=>$date,'_nexo_session_time'=>$c['time'],'_nexo_session_duration'=>$c['duration'],'_nexo_session_status'=>'scheduled','_nexo_generated'=>1]],true);
                if(is_wp_error($id)){return $id;}$result['created']++;
            }
            foreach($existing as $s){if(!$s['generated']||in_array($s['date'],$dates,true)||$s['status']==='cancelled'){continue;}
                if($s['date']<wp_date('Y-m-d')||$s['status']==='completed'||self::has_marks($s['id'])){$result['protected']++;continue;}
                update_post_meta($s['id'],'_nexo_session_status','cancelled');update_post_meta($s['id'],'_nexo_cancel_reason','schedule');$result['cancelled']++;
            }
            return $result;
        }finally{delete_option($lock);}
    }
    public static function enrolled(int $course): array {
        $users=[];foreach(Core::enrollments($course) as $r){if($r->status!=='active'){continue;}$id=(int)$r->user_id;if(!isset($users[$id])||$r->created_at<$users[$id]){$users[$id]=$r->created_at;}}return $users;
    }
    public static function eligible(array $session,string $enrolled): bool {
        try{$joined=new \DateTimeImmutable($enrolled,new \DateTimeZone('UTC'));$cutoff=self::date($session['date']);if(!$cutoff){return false;}if($session['time']){$parts=explode(':',$session['time']);$cutoff=$cutoff->setTime((int)$parts[0],(int)$parts[1])->modify('+'.max(15,(int)$session['duration']).' minutes');}else{$cutoff=$cutoff->setTime(23,59,59);}return $joined<=$cutoff;}catch(\Throwable $e){return false;}
    }
    public static function save_mark(int $session_id,int $user_id,string $status) {
        global $wpdb;$s=self::session($session_id);
        if(!$s||!Core::can_manage($s['course'])||$s['status']==='cancelled'){return new \WP_Error('attendance_forbidden','Você não pode registrar presença nesta aula.');}
        $users=self::enrolled($s['course']);if(!isset($users[$user_id])||!get_userdata($user_id)||!self::eligible($s,$users[$user_id])){return new \WP_Error('attendance_student','O aluno não estava matriculado para esta aula.');}
        if($s['date']>wp_date('Y-m-d')){return new \WP_Error('attendance_future','A chamada é liberada na data da aula.');}
        if(!in_array($status,['present','absent','excused','pending'],true)){return new \WP_Error('attendance_status','Situação de presença inválida.');}
        if($status==='pending'){return false!==$wpdb->delete(self::table(),['session_id'=>$session_id,'user_id'=>$user_id],['%d','%d']);}
        $id=(int)$wpdb->get_var($wpdb->prepare('SELECT id FROM '.self::table().' WHERE session_id=%d AND user_id=%d',$session_id,$user_id));
        $data=['session_id'=>$session_id,'course_id'=>$s['course'],'user_id'=>$user_id,'status'=>$status,'updated_at'=>current_time('mysql',true)];
        if($id){$ok=$wpdb->update(self::table(),$data,['id'=>$id],['%d','%d','%d','%s','%s'],['%d']);}
        else{$data['created_at']=$data['updated_at'];$ok=$wpdb->insert(self::table(),$data,['%d','%d','%d','%s','%s','%s']);}
        return $ok===false?new \WP_Error('attendance_database','Não foi possível salvar a presença.'):true;
    }
    public static function summary(int $course): array {
        $users=self::enrolled($course);$marks=[];foreach(self::marks($course) as $r){$marks[(int)$r->session_id][(int)$r->user_id]=$r->status;}
        $totals=['present'=>0,'absent'=>0,'excused'=>0,'pending'=>0];$student=[];$series=[];
        foreach(array_keys($users) as $uid){$student[$uid]=$totals;}
        foreach(self::sessions($course,false) as $s){
            if($s['date']>wp_date('Y-m-d')){continue;}
            $end=self::date($s['date'])->setTime(23,59,59);if($s['time']){$time=explode(':',$s['time']);$end=self::date($s['date'])->setTime((int)$time[0],(int)$time[1])->modify('+'.max(15,$s['duration']).' minutes');}
            if($s['status']!=='completed'&&(!isset($marks[$s['id']])||$end>new \DateTimeImmutable('now',wp_timezone()))){continue;}
            $counts=['present'=>0,'absent'=>0,'excused'=>0,'pending'=>0];
            foreach($users as $uid=>$joined){if(!self::eligible($s,$joined)){continue;}$status=$marks[$s['id']][$uid]??'pending';if(!isset($counts[$status])){$status='pending';}$counts[$status]++;$totals[$status]++;$student[$uid][$status]++;}
            $s['counts']=$counts;$s['percent']=self::percentage($counts);$s['coverage']=self::coverage($counts);$series[]=$s;
        }
        return ['totals'=>$totals,'percent'=>self::percentage($totals),'coverage'=>self::coverage($totals),'students'=>$student,'sessions'=>$series];
    }
    public static function percentage(array $c): ?int {$base=$c['present']+$c['absent'];return $base?(int)round(100*$c['present']/$base):null;}
    public static function coverage(array $c): ?int {$base=array_sum($c);return $base?(int)round(100*($c['present']+$c['absent']+$c['excused'])/$base):null;}
    public static function meta_boxes(): void {add_meta_box('nexo-calendar','Programação presencial e recorrência',[self::class,'fields'],'nexo_course','normal','high');}
    public static function fields(\WP_Post $post): void {
        wp_nonce_field('nexo_schedule','nexo_schedule_nonce');$c=self::config($post->ID);$new=$post->post_status==='auto-draft';
        echo '<p>Para cursos de gestão/presenciais, gere as aulas entre o início e o fim. Aulas passadas ou com chamada são preservadas ao alterar a programação.</p><div class="nexo-meta-grid"><label>Data inicial<input type="date" name="nexo_start_date" value="'.esc_attr($c['start']).'"></label><label>Data final (inclusive)<input type="date" name="nexo_end_date" value="'.esc_attr($c['end']).'"></label><label>Frequência<select name="nexo_frequency">';
        foreach(['none'=>'Sem geração automática','once'=>'Encontro único','daily'=>'Diário (inclui finais de semana)','weekdays'=>'Dias úteis (segunda a sexta)','weekly'=>'Semanal','biweekly'=>'Quinzenal','monthly'=>'Mensal','custom'=>'Intervalo personalizado'] as $v=>$label){echo '<option value="'.$v.'" '.selected($new?'weekly':$c['frequency'],$v,false).'>'.esc_html($label).'</option>';}
        echo '</select></label><label>Horário de início<input type="time" name="nexo_session_time" value="'.esc_attr($new?'20:00':$c['time']).'"></label><label>Duração por aula (minutos)<input type="number" min="15" max="720" name="nexo_session_duration" value="'.absint($c['duration']).'"></label><label>Intervalo personalizado<input type="number" min="1" max="52" name="nexo_interval" value="'.absint($c['interval']).'"><select name="nexo_interval_unit">';
        foreach(['day'=>'dia(s)','week'=>'semana(s)','month'=>'mês(es)'] as $v=>$label){echo '<option value="'.$v.'" '.selected($c['unit'],$v,false).'>'.$label.'</option>';}
        echo '</select></label><fieldset class="nexo-field-wide"><legend>Dias da semana (semanal / quinzenal / intervalo em semanas)</legend>';
        foreach(['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'] as $i=>$label){echo '<label style="display:inline-flex;gap:5px;margin:5px 14px 5px 0"><input type="checkbox" name="nexo_weekdays[]" value="'.$i.'" '.checked(in_array($i,array_map('intval',$c['weekdays']),true),true,false).'>'.$label.'</label>';}
        echo '<p>Sem seleção, usa o dia da semana da data inicial. Mensal: mantém o dia de início, ajustando para o último dia nos meses mais curtos.</p></fieldset><label class="nexo-field-wide"><span><input type="checkbox" name="nexo_generate_sessions" value="1" '.checked($new||$c['automatic'],true,false).'> Gerar e atualizar aulas ao salvar (somente gestão/presencial)</span></label></div>';
        $dates=self::dates($c);if(!is_wp_error($dates)&&$dates){echo '<p><strong>Programação: '.count($dates).' aula(s).</strong> Primeira: '.esc_html(self::date($dates[0])->format('d/m/Y')).' · Última: '.esc_html(self::date(end($dates))->format('d/m/Y')).'.</p>';}
    }
    public static function notices(): void {
        $id=absint($_GET['post']??0);if(!$id||!current_user_can('edit_post',$id)){return;}$error=get_post_meta($id,'_nexo_schedule_error',true);
        if($error){echo '<div class="notice notice-warning"><p>'.esc_html($error).' A programação anterior foi preservada.</p></div>';}
    }
    public static function student_panel(int $course): string {
        if(!Core::has_access($course)){return '';}$uid=get_current_user_id();$marks=[];foreach(self::marks($course) as $r){if((int)$r->user_id===$uid){$marks[(int)$r->session_id]=$r->status;}}
        $joined=self::enrolled($course)[$uid]??null;$sessions=self::sessions($course);if(!$sessions){return '';}$labels=['present'=>'Presente','absent'=>'Ausente','excused'=>'Justificada'];
        $out='<h2 class="nexo-space-top">Minhas aulas e presenças</h2><div class="nexo-table-wrap"><table class="nexo-table"><thead><tr><th>Aula</th><th>Data</th><th>Horário</th><th>Presença</th></tr></thead><tbody>';
        foreach($sessions as $s){$label=$s['status']==='cancelled'?'Aula cancelada':($marks[$s['id']]??null);$label=($joined&&!self::eligible($s,$joined)&&$s['status']!=='cancelled')?'Anterior à matrícula':($labels[$label]??($label?:($s['date']>wp_date('Y-m-d')?'A realizar':'Chamada pendente')));$out.='<tr><td>'.esc_html($s['title']).'</td><td>'.esc_html(self::date($s['date'])->format('d/m/Y')).'</td><td>'.esc_html($s['time']?:'—').'</td><td>'.esc_html($label).'</td></tr>';}
        return $out.'</tbody></table></div>';
    }
    public static function delete_user_data(int $uid): void {global $wpdb;$wpdb->delete(self::table(),['user_id'=>$uid],['%d']);}
    public static function exporters(array $items): array {$items['nexo-session-attendance']=['exporter_friendly_name'=>'Presenças por aula','callback'=>[self::class,'export_data']];return $items;}
    public static function erasers(array $items): array {$items['nexo-session-attendance']=['eraser_friendly_name'=>'Presenças por aula','callback'=>[self::class,'erase_data']];return $items;}
    public static function export_data(string $email,int $page=1): array {global $wpdb;$user=get_user_by('email',$email);$data=[];if($user){$rows=$wpdb->get_results($wpdb->prepare('SELECT * FROM '.self::table().' WHERE user_id=%d ORDER BY id LIMIT 100 OFFSET %d',$user->ID,(max(1,$page)-1)*100));foreach($rows as $r){$data[]=['group_id'=>'nexo-session-attendance','group_label'=>'Presença por aula','item_id'=>'nexo-session-'.$r->id,'data'=>[['name'=>'Curso','value'=>get_the_title($r->course_id)],['name'=>'Aula','value'=>get_the_title($r->session_id)],['name'=>'Data','value'=>get_post_meta($r->session_id,'_nexo_session_date',true)],['name'=>'Presença','value'=>$r->status]]];}}return ['data'=>$data,'done'=>count($data)<100];}
    public static function erase_data(string $email,int $page=1): array {$user=get_user_by('email',$email);if($user){self::delete_user_data($user->ID);}return ['items_removed'=>(bool)$user,'items_retained'=>false,'messages'=>[],'done'=>true];}
}
