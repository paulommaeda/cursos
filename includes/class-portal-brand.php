<?php
namespace NexoCursos;
defined('ABSPATH') || exit;
final class PortalBrand {
    public static function mutate(string $action,array $d) {
        if(!Portal::brand()){return new \WP_Error('forbidden','Você não tem permissão para alterar a apresentação da plataforma.');}
        if($action==='home_save'){
            if(!function_exists('shalom_design_fields')){return new \WP_Error('theme','Ative o tema Shalom Formação para editar a página inicial.');}
            $values=[];foreach(shalom_design_fields() as $section){foreach($section[1] as $key=>$field){$type=$field[2]??'text';$name='home_'.$key;
                if($type==='image'){$image=Portal::upload($name);if(is_wp_error($image)){return $image;}$values[$key]=$image?:(!empty($d['remove_'.$name])?0:absint(shalom_value($key)));}
                elseif($type==='checkbox'){$values[$key]=!empty($d[$name]);}
                elseif($type==='url'){$values[$key]=shalom_sanitize_url(Portal::text($d,$name));}
                elseif($type==='number'){$values[$key]=shalom_sanitize_count(Portal::id($d,$name));}
                elseif($type==='textarea'){$values[$key]=sanitize_textarea_field(wp_unslash(is_string($d[$name]??null)?$d[$name]:''));}
                else{$values[$key]=Portal::text($d,$name);}
            }}foreach($values as $key=>$value){set_theme_mod('shalom_'.$key,$value);}set_theme_mod('shalom_section_order',shalom_sanitize_order(Portal::text($d,'section_order')));
            return ['message'=>'Página inicial atualizada.','view'=>'home'];
        }
        $old=nexo_settings();$name=Portal::text($d,'brand_name');if(!$name||mb_strlen($name)>80){return new \WP_Error('brand','Informe um nome de até 80 caracteres.');}
        $s=Brand::sanitize($d,$old);$s['brand_name']=$name;$s['login_title']=mb_substr(Portal::text($d,'login_title'),0,120);$s['login_message']=mb_substr(Portal::text($d,'login_message'),0,400);
        $logo=Portal::upload('brand_logo');if(is_wp_error($logo)){return $logo;}$s['logo_id']=$logo?:(!empty($d['remove_brand_logo'])?0:$old['logo_id']);update_option('nexo_settings',$s,false);
        if(current_theme_supports('nexo-navigation')){set_theme_mod('custom_logo',$s['logo_id']);}
        return ['message'=>'Identidade visual salva.','view'=>'brand'];
    }
    public static function render(string $view): void {
        if(!Portal::brand()){echo '<p class="np-error">A identidade é editada pela coordenação ou pelo administrador.</p>';return;}
        if($view==='home'){self::home();return;}$s=nexo_settings();echo '<section class="np-card"><h2>Uma plataforma com a sua identidade</h2><p>As escolhas são compartilhadas com o site, o acesso dos alunos e a gestão.</p>'.Portal::form('brand_save','brand').Portal::input('Nome da marca','brand_name',$s['brand_name'],'text','required maxlength="80"').Portal::image_field('Logo','brand_logo',(int)$s['logo_id']).'<fieldset><legend>Paleta de cores</legend><div class="np-form-grid">';
        foreach(Brand::colors() as $key=>$field){echo Portal::input($field[0],$key,$s[$key],'color');}echo '</div></fieldset><fieldset><legend>Leitura e formato</legend><div class="np-form-grid">'.Portal::select('Fonte dos textos','body_font',['modern'=>'Moderna','geometric'=>'Geométrica','classic'=>'Clássica'],$s['body_font']).Portal::select('Fonte dos títulos','heading_font',['modern'=>'Moderna','geometric'=>'Geométrica','classic'=>'Clássica'],$s['heading_font']).Portal::input('Tamanho base do texto (16–20 px)','base_font_size',$s['base_font_size']??17,'number','min="16" max="20"').Portal::input('Arredondamento dos cartões (0–30 px)','corner_radius',$s['corner_radius'],'number','min="0" max="30"').'</div></fieldset><fieldset><legend>Tela de acesso</legend><div class="np-form-grid">'.Portal::input('Título de acesso','login_title',$s['login_title'],'text','maxlength="120"').Portal::input('Boas-vindas','login_message',$s['login_message'],'text','maxlength="400"').'</div>';
        foreach(['login_eyebrow'=>'Chamada superior','login_headline'=>'Título da apresentação','login_story'=>'Apresentação','login_footer'=>'Assinatura'] as $key=>$label){echo Portal::textarea($label,$key,$s[$key],3,'maxlength="600"');}echo '</fieldset>'.Portal::button('Salvar identidade').'</form></section>';
    }
    private static function home(): void {
        if(!function_exists('shalom_design_fields')){echo '<p class="np-empty">A edição da home fica disponível com o tema Shalom Formação ativo.</p>';return;}
        echo '<section class="np-card"><h2>Personalizar a página inicial</h2><p>Edite as seções, os textos e as imagens sem sair da gestão.</p>'.Portal::form('home_save','home');
        foreach(shalom_design_fields() as $key=>$section){echo '<details class="np-settings-section" '.($key==='hero'?'open':'').'><summary>'.esc_html($section[0]).'</summary><div class="np-form-grid">';foreach($section[1] as $field=>$definition){$type=$definition[2]??'text';$name='home_'.$field;$value=shalom_value($field);
            if($type==='image'){echo Portal::image_field($definition[0],$name,(int)$value);}
            elseif($type==='checkbox'){echo '<label class="np-check"><input type="checkbox" name="'.esc_attr($name).'" value="1" '.checked((bool)$value,true,false).'>'.esc_html($definition[0]).'</label>';}
            elseif($type==='textarea'){echo Portal::textarea($definition[0],$name,(string)$value,4);}
            else{echo Portal::input($definition[0],$name,$value,$type==='number'?'number':'text',$type==='number'?'min="1" max="12"':'');}
        }echo '</div></details>';}
        echo '<fieldset><legend>Ordem e visibilidade</legend>'.Portal::input('Seções da home','section_order',get_theme_mod('shalom_section_order','hero,strip,purpose,courses,scripture,journey')).'<p class="np-help">Reordene os nomes separados por vírgulas: hero (apresentação), strip (faixa), purpose (propósito), courses (cursos), scripture (versículo), journey (convite). Remova um nome para ocultar a seção.</p></fieldset>'.Portal::button('Salvar página inicial').'</form></section>';
    }
}
