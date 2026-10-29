<?php
/** Manager classroom calendar, attendance sheet and engagement chart. */
namespace NexoCursos;
defined('ABSPATH') || exit;
final class Classroom {
    public static function boot(): void {add_action('admin_post_nexo_classroom',[self::class,'handle']);add_action('admin_enqueue_scripts',[self::class,'assets']);add_action('wp_enqueue_scripts',[self::class,'assets']);}
    public static function assets(): void {if(Portal::allowed()){wp_enqueue_style('nexo-classroom',NEXO_URL.'assets/classroom.css',[],NEXO_VERSION);}}
    private static function url(int $course,array $args=[]): string {return (Portal::in_context()?Portal::url('classroom',array_merge(['course_id'=>$course],$args)):add_query_arg(array_merge(['page'=>'nexo-cursos','course_id'=>$course],$args),admin_url('admin.php'))).(isset($args['nexo_session'])?'#nexo-attendance-sheet':'#nexo-classroom');}
    private static function nonce(int $course): string {return wp_nonce_field('nexo_classroom_'.$course,'_nexo_classroom_nonce',false,false);}
    private static function form_start(int $course,string $operation,int $session=0): string {if(Portal::in_context()){return Portal::form('classroom','classroom',['action'=>'nexo_classroom','course_id'=>$course,'operation'=>$operation,'session_id'=>$session]).self::nonce($course);}return '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="nexo_classroom"><input type="hidden" name="course_id" value="'.$course.'"><input type="hidden" name="operation" value="'.esc_attr($operation).'"><input type="hidden" name="session_id" value="'.$session.'">'.self::nonce($course);}
    public static function attendance_cell(int $course,int $uid,string $date): string {
        if(get_post_meta($course,'_nexo_mode',true)==='erp'){return '<a class="nexo-text-button" href="'.esc_url(self::url($course)).'">Chamada por aula ↗</a>';}
        $attendance=Core::attendance($course,$uid);
        $out='<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" class="nexo-attendance-form">'.wp_nonce_field('nexo_attendance','_wpnonce',false,false).'<input type="hidden" name="action" value="nexo_attendance"><input type="hidden" name="course_id" value="'.$course.'"><input type="hidden" name="user_id" value="'.$uid.'"><input type="hidden" name="attendance_date" value="'.esc_attr($date).'"><select name="attendance_status" aria-label="Presença"><option value="">Registrar</option>';
        foreach(['present'=>'Presente','absent'=>'Ausente','excused'=>'Justificada'] as $value=>$label){$out.='<option value="'.$value.'" '.selected($attendance[$date]??'',$value,false).'>'.$label.'</option>';}
        return $out.'</select><button class="nexo-text-button">Salvar</button></form>';
    }
    public static function handle(): void {
        if(($_SERVER['REQUEST_METHOD']??'')!=='POST'){wp_die('Ação não permitida.','',['response'=>405]);}
        $course=absint($_POST['course_id']??0);
        if(!Core::can_manage($course)||get_post_meta($course,'_nexo_mode',true)!=='erp'){wp_die('Você não tem permissão para esta chamada.','',['response'=>403]);}
        check_admin_referer('nexo_classroom_'.$course,'_nexo_classroom_nonce');
        $op=sanitize_key($_POST['operation']??'');$id=absint($_POST['session_id']??0);$s=$id?Calendar::session($id):false;
        if($id&&(!$s||$s['course']!==$course)){wp_die('A aula não pertence ao curso selecionado.','',['response'=>403]);}
        $notice='Salvo.';$error=false;
        if($op==='generate'){$result=Calendar::synchronize($course);if(is_wp_error($result)){$notice=$result->get_error_message();$error=true;}else{$notice='Programação atualizada: '.$result['created'].' nova(s), '.$result['cancelled'].' cancelada(s), '.$result['protected'].' histórica(s) preservada(s).';}}
        elseif($op==='add'){
            $date=sanitize_text_field(wp_unslash($_POST['date']??''));$time=sanitize_text_field(wp_unslash($_POST['time']??''));$title=sanitize_text_field(wp_unslash($_POST['title']??''));
            if(!Calendar::date($date)||($time&&!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/D',$time))){$notice='Informe data e horário válidos.';$error=true;}
            else{$config=Calendar::config($course);$result=wp_insert_post(['post_type'=>'nexo_session','post_status'=>'publish','post_title'=>$title?:'Aula extra — '.Calendar::date($date)->format('d/m/Y'),'meta_input'=>['_nexo_course_id'=>$course,'_nexo_session_date'=>$date,'_nexo_session_time'=>$time,'_nexo_session_duration'=>$config['duration'],'_nexo_session_status'=>'scheduled','_nexo_generated'=>0]],true);if(is_wp_error($result)){$notice='Não foi possível criar a aula.';$error=true;}else{$id=(int)$result;$notice='Aula adicionada.';}}
        }
        elseif($op==='attendance'&&$s){
            $marks=is_array($_POST['attendance']??null)?array_slice($_POST['attendance'],0,100,true):[];$saved=0;$failed=0;
            foreach($marks as $uid=>$status){$result=Calendar::save_mark($id,absint($uid),is_string($status)?sanitize_key($status):'invalid');if(is_wp_error($result)||!$result){$failed++;}else{$saved++;}}
            $notice=$saved.' registro(s) salvo(s).'.($failed?' '.$failed.' registro(s) não puderam ser salvos.':'');$error=$failed>0;
        }
        elseif(in_array($op,['complete','reopen','cancel'],true)&&$s){
            if($op==='complete'&&($s['date']>wp_date('Y-m-d')||$s['status']==='cancelled')){$notice='Uma aula futura ou cancelada não pode ser concluída.';$error=true;}
            elseif($op==='cancel'&&Calendar::has_marks($id)){$notice='Esta aula tem presenças registradas e foi preservada. Corrija os registros antes de cancelar.';$error=true;}
            else{$status=['complete'=>'completed','reopen'=>'scheduled','cancel'=>'cancelled'][$op];update_post_meta($id,'_nexo_session_status',$status);if($op==='cancel'){update_post_meta($id,'_nexo_cancel_reason','manual');}else{delete_post_meta($id,'_nexo_cancel_reason');}$notice='Situação da aula atualizada. A chamada não foi apagada.';}
        }
        elseif($op==='import'){
            $by_date=[];foreach(Calendar::sessions($course,false) as $session){$by_date[$session['date']][]=$session;}$saved=0;
            foreach(Calendar::enrolled($course) as $uid=>$joined){foreach(Core::attendance($course,$uid) as $date=>$status){if(count($by_date[$date]??[])!==1||$date>wp_date('Y-m-d')){continue;}$session=$by_date[$date][0];$exists=false;foreach(Calendar::marks($course) as $r){if((int)$r->session_id===$session['id']&&(int)$r->user_id===$uid){$exists=true;break;}}if(!$exists&&!is_wp_error(Calendar::save_mark($session['id'],$uid,$status))){$saved++;}}}
            $notice=$saved.' presença(s) antiga(s) importada(s), somente em datas com uma única aula e sem sobrescrever registros.';
        }
        else{$notice='Operação inválida.';$error=true;}
        set_transient('nexo_classroom_notice_'.get_current_user_id(),['text'=>$notice,'error'=>$error],MINUTE_IN_SECONDS);
        wp_safe_redirect(self::url($course,$id?['nexo_session'=>$id]:[]));exit;
    }
    public static function render(int $selected,array $courses): void {
        $erp=array_values(array_filter($courses,static fn($c)=>get_post_meta($c->ID,'_nexo_mode',true)==='erp'&&Core::can_manage((int)$c->ID)));
        if(!$erp){return;}
        if(!$selected&&count($erp)===1){$selected=(int)$erp[0]->ID;}
        $course=null;foreach($erp as $candidate){if((int)$candidate->ID===$selected){$course=$candidate;break;}}
        echo '<section id="nexo-classroom" class="nexo-panel nexo-classroom"><div class="nexo-panel-title"><div><span class="nexo-eyebrow">GESTÃO / PRESENCIAL</span><h2>Aulas, chamada e engajamento</h2><p>Acompanhe cada encontro e cada aluno.</p></div></div>';
        echo '<form method="get" action="'.esc_url(Portal::in_context()?nexo_page_url('dashboard'):admin_url('admin.php')).'" class="nexo-filters">'.(Portal::in_context()?Portal::get_fields('classroom'):'<input type="hidden" name="page" value="nexo-cursos">').'<label>Curso presencial<select name="course_id">';foreach($erp as $c){echo '<option value="'.$c->ID.'" '.selected($selected,$c->ID,false).'>'.esc_html($c->post_title).'</option>';}echo '</select></label><button class="nexo-button nexo-button-secondary">Abrir curso</button></form>';
        if(!$course){echo '<p>Selecione um curso presencial para abrir o calendário e a chamada.</p></section>';return;}
        $key='nexo_classroom_notice_'.get_current_user_id();$notice=get_transient($key);if(is_array($notice)){delete_transient($key);echo '<div role="status" class="nexo-notice">'.esc_html($notice['text']).'</div>';}
        $sessions=Calendar::sessions($selected);$summary=Calendar::summary($selected);
        $id=absint($_GET['nexo_session']??0);$chosen=null;
        foreach($sessions as $s){if($id&&$s['id']===$id){$chosen=$s;break;}if(!$id&&$s['status']!=='cancelled'&&$s['date']<=wp_date('Y-m-d')){$chosen=$s;}}
        if(!$chosen&&!$id){foreach($sessions as $s){if($s['status']!=='cancelled'){$chosen=$s;break;}}}
        if($sessions&&Portal::in_context()){$choices=[];foreach($sessions as $session){$choices[$session['id']]=$session['title'].($session['status']==='cancelled'?' (cancelada)':'');}echo '<form method="get" action="'.esc_url(nexo_page_url('dashboard')).'#nexo-attendance-sheet" class="nexo-filters">'.Portal::get_fields('classroom').'<input type="hidden" name="course_id" value="'.$selected.'">'.Portal::select('Escolher aula','nexo_session',$choices,$chosen['id']??0).'<button class="nexo-button">Abrir chamada</button></form>';}
        echo '<details class="nexo-mobile-details" open><summary>Engajamento da turma</summary>';self::engagement($summary);echo '</details>';
        echo '<details class="nexo-schedule-tools"><summary>Programação e aulas extras</summary><div class="nexo-schedule-tools-grid"><div><p>Defina início, fim e frequência em Cursos → Editar curso. Aulas com chamada e aulas passadas são preservadas ao regenerar.</p>'.self::form_start($selected,'generate').'<button class="nexo-button nexo-button-secondary">Atualizar programação</button></form>'.self::form_start($selected,'import').'<button class="nexo-text-button">Importar chamadas antigas por data</button></form></div><div>'.self::form_start($selected,'add').'<label>Título da aula<input name="title" maxlength="160" placeholder="Ex.: Encontro de revisão"></label><label>Data<input type="date" name="date" required></label><label>Horário<input type="time" name="time"></label><button class="nexo-button">Adicionar aula extra</button></form></div></div></details>';
        if(!$sessions){echo '<div class="nexo-empty"><h3>Prepare a programação deste curso</h3><p>Preencha início, fim e frequência no cadastro do curso e habilite a geração das aulas, ou adicione uma aula extra.</p></div></section>';return;}
        echo '<details class="nexo-mobile-details nexo-classroom-calendar" open><summary>Calendário de aulas ('.count($sessions).')</summary><div class="nexo-table-scroll"><table class="nexo-table"><thead><tr><th>Aula</th><th>Data / horário</th><th>Situação</th><th>Chamada</th></tr></thead><tbody>';
        $page=max(1,absint($_GET['classroom_page']??1));$pages=max(1,(int)ceil(count($sessions)/12));$page=min($page,$pages);
        foreach(array_slice($sessions,($page-1)*12,12) as $s){$labels=['scheduled'=>'Programada','completed'=>'Realizada','cancelled'=>'Cancelada'];echo '<tr><td>'.esc_html($s['title']).($s['generated']?'':' <small>Extra</small>').'</td><td>'.esc_html(Calendar::date($s['date'])->format('d/m/Y').' '.$s['time']).'</td><td>'.esc_html($labels[$s['status']]??$s['status']).'</td><td><a class="nexo-text-button" href="'.esc_url(self::url($selected,['nexo_session'=>$s['id']])).'">Abrir chamada ↗</a></td></tr>';}
        echo '</tbody></table></div>';
        if($pages>1){echo '<nav class="nexo-pagination" aria-label="Páginas das aulas">'.wp_kses_post(paginate_links(['base'=>str_replace('%25%23%25','%#%',add_query_arg('classroom_page','%#%',self::url($selected))),'format'=>'','current'=>$page,'total'=>$pages])).'</nav>';}
        echo '</details>';
        if($chosen){self::sheet($selected,$chosen,$summary,is_array($notice)?$notice:null);}elseif($id){echo '<p>A aula selecionada não pertence a este curso.</p>';}
        echo '</section>';
    }
    public static function engagement(array $summary): void {
        $percent=$summary['percent'];$coverage=$summary['coverage'];$c=$summary['totals'];
        echo '<div class="nexo-attendance-kpis"><article><span>Frequência registrada</span><strong>'.($percent===null?'—':$percent.'%').'</strong><small>Presenças ÷ (presenças + faltas)</small></article><article><span>Cobertura das chamadas</span><strong>'.($coverage===null?'—':$coverage.'%').'</strong><small>Registros preenchidos nos encontros considerados</small></article><article><span>Chamada pendente</span><strong>'.absint($c['pending']).'</strong><small>Registros ainda não preenchidos</small></article></div>';
        $series=array_slice($summary['sessions'],-12);
        echo '<div class="nexo-engagement-chart"><h3>Engajamento por presença</h3><p>Frequência por aula · últimos 12 encontros realizados ou com chamada após seu horário. Aulas futuras e canceladas ficam fora.</p>';
        if(!$series){echo '<div class="nexo-empty"><p>O gráfico aparecerá após realizar aulas e registrar a chamada.</p></div></div>';return;}
        echo '<div class="nexo-chart-scroll"><div class="nexo-attendance-bars" role="img" aria-label="Frequência registrada por aula. Os valores também estão na tabela abaixo.">';
        foreach($series as $s){$pct=$s['percent'];echo '<div class="nexo-attendance-bar"><strong>'.($pct===null?'—':$pct.'%').'</strong><div class="nexo-bar-track"><span style="height:'.absint($pct??0).'%"></span></div><small>'.esc_html(Calendar::date($s['date'])->format('d/m')).'</small></div>';}
        echo '</div></div><details><summary>Ver os números e a fórmula do gráfico</summary><p>Frequência = presentes ÷ (presentes + ausentes). Justificadas e pendentes ficam fora dessa porcentagem. Cobertura = chamadas preenchidas ÷ alunos elegíveis. Alunos matriculados depois da aula ficam fora da base.</p><div class="nexo-table-scroll"><table class="nexo-table"><thead><tr><th>Aula</th><th>Presenças</th><th>Faltas</th><th>Justificadas</th><th>Pendentes</th><th>Frequência</th><th>Cobertura</th></tr></thead><tbody>';
        foreach($series as $s){echo '<tr><td>'.esc_html($s['title'].' · '.Calendar::date($s['date'])->format('d/m/Y')).'</td>';foreach(['present','absent','excused','pending'] as $status){echo '<td>'.absint($s['counts'][$status]).'</td>';}echo '<td>'.($s['percent']===null?'—':$s['percent'].'%').'</td><td>'.($s['coverage']===null?'—':$s['coverage'].'%').'</td></tr>';}
        echo '</tbody></table></div></details></div>';
    }
    private static function sheet(int $course,array $s,array $summary,?array $notice=null): void {
        $users=Calendar::enrolled($course);$eligible=[];foreach($users as $uid=>$joined){if(Calendar::eligible($s,$joined)){$eligible[]=$uid;}}
        $marks=[];foreach(Calendar::marks($course) as $row){if((int)$row->session_id===$s['id']){$marks[(int)$row->user_id]=$row->status;}}
        echo '<div id="nexo-attendance-sheet" class="nexo-classroom-sheet">'.($notice?'<div role="status" class="nexo-notice">'.esc_html($notice['text']).'</div>':'').'<div class="nexo-panel-title"><div><span class="nexo-eyebrow">CHAMADA DA AULA</span><h3>'.esc_html($s['title']).'</h3><p>'.esc_html(Calendar::date($s['date'])->format('d/m/Y').' '.$s['time']).' · '.count($eligible).' aluno(s) elegível(is)</p></div></div>';
        if($s['status']==='cancelled'){echo '<p>Aula cancelada. Reabra para registrar a chamada.</p>'.self::form_start($course,'reopen',$s['id']).'<button class="nexo-button nexo-button-secondary">Reabrir aula</button></form></div>';return;}
        $future=$s['date']>wp_date('Y-m-d');if($future){echo '<p class="nexo-notice">A chamada será liberada na data da aula. Não são registradas faltas automaticamente.</p>';}
        $page=max(1,absint($_GET['classroom_students']??1));$pages=max(1,(int)ceil(count($eligible)/50));$page=min($page,$pages);
        echo self::form_start($course,'attendance',$s['id']).'<div class="nexo-table-scroll"><table class="nexo-table"><thead><tr><th>Aluno</th><th>Presença nesta aula</th><th>Frequência registrada no curso</th><th>Cobertura</th></tr></thead><tbody>';
        foreach(array_slice($eligible,($page-1)*50,50) as $uid){$user=get_userdata($uid);if(!$user){continue;}$row=$summary['students'][$uid]??['present'=>0,'absent'=>0,'excused'=>0,'pending'=>0];$pct=Calendar::percentage($row);$cov=Calendar::coverage($row);
            echo '<tr><td><strong>'.esc_html($user->display_name).'</strong><small class="nexo-roster-email">'.esc_html($user->user_email).'</small></td><td><select name="attendance['.$uid.']" aria-label="'.esc_attr('Presença de '.$user->display_name).'" '.disabled($future,true,false).'>';
            foreach(['pending'=>'Pendente (sem registro)','present'=>'Presente','absent'=>'Ausente','excused'=>'Justificada'] as $status=>$label){echo '<option value="'.$status.'" '.selected($marks[$uid]??'pending',$status,false).'>'.$label.'</option>';}
            echo '</select></td><td><div class="nexo-progress-bar"><span style="width:'.absint($pct??0).'%"></span></div><small>'.($pct===null?'Sem registros':$pct.'%').'</small></td><td>'.($cov===null?'—':$cov.'%').'</td></tr>';
        }
        if(!$eligible){echo '<tr><td colspan="4">Nenhum aluno elegível para esta aula. Matricule os alunos pelo painel.</td></tr>';}
        echo '</tbody></table></div>';if(!$future&&$eligible){echo '<button class="nexo-button">Salvar chamada</button>';}echo '</form>';
        if($pages>1){echo '<nav class="nexo-pagination">'.wp_kses_post(paginate_links(['base'=>str_replace('%25%23%25','%#%',add_query_arg('classroom_students','%#%',self::url($course,['nexo_session'=>$s['id']]))),'format'=>'','current'=>$page,'total'=>$pages])).'</nav>';}
        echo '<div class="nexo-session-actions">';if(!$future){echo self::form_start($course,$s['status']==='completed'?'reopen':'complete',$s['id']).'<button class="nexo-button nexo-button-secondary">'.($s['status']==='completed'?'Reabrir aula':'Marcar aula como realizada').'</button></form>';}
        if(!Calendar::has_marks($s['id'])){echo self::form_start($course,'cancel',$s['id']).'<button class="nexo-text-button nexo-danger">Cancelar aula sem apagar registros</button></form>';}
        echo '</div></div>';
    }
}
