<?php
/** Shared configurable palette, typography and course-level overrides. */
namespace NexoCursos;
defined('ABSPATH') || exit;
final class Brand {
    public static function colors(): array {
        return ['primary_color'=>['Azul principal','#071450'],'accent_color'=>['Azul luminoso / destaque','#00AEEF'],'surface_color'=>['Superfícies e cartões','#FFFFFF'],'paper_color'=>['Fundo geral','#F4F8FE'],'soft_color'=>['Fundo de seções','#EAF2FC'],'text_color'=>['Texto principal','#06102D'],'muted_color'=>['Texto secundário','#5B6D86'],'warm_color'=>['Luz / destaque quente','#F8BD69'],'footer_color'=>['Fundo do rodapé','#031C46'],'footer_text_color'=>['Texto do rodapé','#D8E8FC'],'absent_color'=>['Faltas no painel','#C95568'],'excused_color'=>['Justificativas no painel','#C29436']];
    }
    public static function defaults(): array {$d=[];foreach(self::colors() as $key=>$c){$d[$key]=$c[1];}return $d+['body_font'=>'modern','heading_font'=>'modern','corner_radius'=>10,'login_eyebrow'=>'UMA JORNADA COM PROPÓSITO','login_headline'=>"Discipular.\nAperfeiçoar.\nEnviar.",'login_story'=>"Cresça no conhecimento da Palavra.\nPrepare-se para servir e abençoar vidas.",'login_footer'=>'Fé que aprende. Amor que serve.'];}
    public static function fonts(): array {return ['modern'=>'"Segoe UI",Arial,sans-serif','geometric'=>'"Trebuchet MS",Arial,sans-serif','classic'=>'Georgia,"Times New Roman",serif'];}
    public static function variables(array $s): string {
        $vars=['surface_color'=>'surface','paper_color'=>'paper','soft_color'=>'soft','text_color'=>'text','muted_color'=>'muted','warm_color'=>'warm','footer_color'=>'footer','footer_text_color'=>'footer-text','absent_color'=>'absent','excused_color'=>'excused'];$css='';
        foreach($vars as $key=>$var){$css.='--nexo-'.$var.':'.(sanitize_hex_color($s[$key]??'')?:self::colors()[$key][1]).';';}
        $fonts=self::fonts();$css.='--nexo-font-body:'.($fonts[$s['body_font']??'modern']??$fonts['modern']).';--nexo-font-heading:'.($fonts[$s['heading_font']??'modern']??$fonts['modern']).';--nexo-radius:'.max(0,min(30,absint($s['corner_radius']??10))).'px;';return $css;
    }
    public static function fields(array $s): void {
        echo '<h2>Apresentação da tela de acesso</h2><div class="nexo-meta-grid">';foreach(['login_eyebrow'=>'Chamada superior','login_headline'=>'Título da apresentação','login_story'=>'Texto de apresentação','login_footer'=>'Assinatura'] as $key=>$label){echo '<label>'.esc_html($label).'<textarea name="'.esc_attr($key).'" rows="3" maxlength="600">'.esc_textarea($s[$key]??'').'</textarea></label>';}echo '</div>';
        echo '<h2>Paleta e tipografia completas</h2><div class="nexo-meta-grid">';
        foreach(self::colors() as $key=>$data){if(in_array($key,['primary_color','accent_color'],true)){continue;}echo '<label>'.esc_html($data[0]).'<input type="color" name="'.esc_attr($key).'" value="'.esc_attr($s[$key]??$data[1]).'"></label>';}
        foreach(['body_font'=>'Fonte dos textos','heading_font'=>'Fonte dos títulos'] as $key=>$label){echo '<label>'.esc_html($label).'<select name="'.$key.'">';foreach(['modern'=>'Moderna / sem serifa','geometric'=>'Geométrica','classic'=>'Clássica / com serifa'] as $v=>$text){echo '<option value="'.$v.'" '.selected($s[$key]??'modern',$v,false).'>'.$text.'</option>';}echo '</select></label>';}
        echo '<label>Arredondamento dos cartões (0–30 px)<input type="number" min="0" max="30" name="corner_radius" value="'.absint($s['corner_radius']??10).'"></label></div><p>Textos, imagens, botões, seções e ordem da home também podem ser editados em Aparência → Personalizar. Nenhuma cor é reaplicada automaticamente depois que você personaliza.</p>';
    }
    public static function sanitize(array $input,array $old): array {
        foreach(['login_eyebrow','login_headline','login_story','login_footer'] as $key){if(isset($input[$key])&&is_string($input[$key])){$old[$key]=mb_substr(sanitize_textarea_field(wp_unslash($input[$key])),0,600);}}
        foreach(self::colors() as $key=>$data){if(isset($input[$key])&&is_string($input[$key])){$old[$key]=sanitize_hex_color(wp_unslash($input[$key]))?:($old[$key]??$data[1]);}}
        foreach(['body_font','heading_font'] as $key){if(isset($input[$key])&&array_key_exists($input[$key],self::fonts())){$old[$key]=$input[$key];}}
        if(isset($input['corner_radius'])){$old['corner_radius']=max(0,min(30,absint($input['corner_radius'])));}return $old;
    }
    public static function course_fields(\WP_Post $post): void {
        $color=get_post_meta($post->ID,'_nexo_course_color',true);$tagline=get_post_meta($post->ID,'_nexo_course_tagline',true);
        echo '<div class="nexo-meta-grid"><label>Cor própria do curso (opcional)<input name="nexo_course_color" placeholder="#071450" pattern="#[0-9a-fA-F]{6}" value="'.esc_attr($color).'"><small>Deixe vazio para herdar a paleta da plataforma.</small></label><label>Frase na capa (opcional)<input name="nexo_course_tagline" maxlength="120" value="'.esc_attr($tagline).'" placeholder="Ex.: Caminho para a Plenitude"></label></div><p>A imagem destacada é a capa personalizável deste curso. Use a arte específica do curso sem alterar a identidade dos demais.</p>';
    }
    public static function course_style(int $id): string {$color=sanitize_hex_color(get_post_meta($id,'_nexo_course_color',true));return $color?'--nexo-course-color:'.$color.';':'';}
}
