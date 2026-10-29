<?php
/** All presentation choices are stored in WordPress, never baked into course records. */
defined('ABSPATH') || exit;
function shalom_design_fields(): array {
    return [
        'header'=>['Cabeçalho e navegação',[
            'brand_subtitle'=>['Assinatura da marca','FORMAÇÃO & PROPÓSITO'],
            'announcement'=>['Faixa superior','Uma fé que cresce. Uma vida que serve.'],
            'announcement_link'=>['Texto do link da faixa','Conheça nosso propósito ↗'],
            'announcement_url'=>['Destino do link da faixa','/#proposito','url'],
            'nav_home'=>['Menu: início','Início'],'nav_catalog'=>['Menu: cursos','Cursos'],'nav_purpose'=>['Menu: propósito','Nosso propósito'],'nav_account'=>['Menu: meus cursos','Meus cursos'],'nav_dashboard'=>['Menu: gerente','Painel do gerente'],'nav_cart'=>['Menu: carrinho','Carrinho'],'nav_wcaccount'=>['Menu: conta','Minha conta'],'nav_login'=>['Menu: entrar','Entrar / cadastrar'],'nav_logout'=>['Menu: sair','Sair'],
            'show_announcement'=>['Exibir faixa superior',true,'checkbox'],'show_purpose_link'=>['Exibir propósito no menu',true,'checkbox'],'show_cart_link'=>['Exibir carrinho no menu',true,'checkbox'],
        ]],
        'hero'=>['Home: apresentação',[
            'eyebrow'=>['Chamada superior','ESCOLA DE FORMAÇÃO · IGREJA SHALOM'],
            'headline'=>['Título principal',"Discipular.\nAperfeiçoar.\nEnviar.",'textarea'],
            'intro'=>['Apresentação','Um caminho de crescimento em Cristo. Cursos livres para fortalecer a fé, desenvolver dons e preparar a igreja para a obra do ministério.','textarea'],
            'hero_button'=>['Botão principal','Encontre seu próximo curso'],'hero_url'=>['Destino do botão principal','/#cursos','url'],
            'hero_secondary'=>['Link secundário','Conheça nosso propósito'],'hero_secondary_url'=>['Destino do link secundário','/#proposito','url'],
            'hero_note'=>['Nota de apoio','Fundamentados na Palavra. Formados para servir.'],
            'hero_art_title'=>['Título na arte',"Um caminho.\nUm chamado.",'textarea'],
            'hero_art_note'=>['Legenda na arte','CAMINHO PARA A MATURIDADE'],
            'hero_image'=>['Imagem da apresentação (substitui a ilustração)',0,'image'],
            'hero_alt'=>['Descrição acessível da imagem','Caminho em direção às montanhas iluminadas pelo nascer do sol'],
            'show_hero_art'=>['Exibir arte / imagem',true,'checkbox'],
        ]],
        'purpose'=>['Home: propósito',[
            'strip_note'=>['Faixa: apresentação','Uma jornada de formação, um mesmo chamado.'],
            'strip_words'=>['Faixa: palavras','Discipular · Aperfeiçoar · Enviar'],
            'purpose_eyebrow'=>['Chamada superior','APRENDER PARA VIVER O CHAMADO'],
            'purpose_title'=>['Título',"Conhecimento que se torna\nvida e serviço.",'textarea'],
            'purpose_intro'=>['Apresentação','Cremos em uma igreja que cresce em maturidade e se coloca à disposição. Nossa formação une a Palavra de Deus à prática da vida cristã, preparando pessoas para edificar o corpo de Cristo.','textarea'],
            'pillar1_title'=>['Primeiro pilar: título','Discipular'],'pillar1_text'=>['Primeiro pilar: texto','Aprofundar as raízes da fé, conhecer a Palavra e caminhar cada dia mais perto de Jesus.','textarea'],'pillar1_note'=>['Primeiro pilar: assinatura','Raízes firmes na Palavra'],
            'pillar2_title'=>['Segundo pilar: título','Aperfeiçoar'],'pillar2_text'=>['Segundo pilar: texto','Desenvolver dons, amadurecer na caminhada e estar preparado para a obra do ministério.','textarea'],'pillar2_note'=>['Segundo pilar: assinatura','Maturidade para servir'],
            'pillar3_title'=>['Terceiro pilar: título','Enviar'],'pillar3_text'=>['Terceiro pilar: texto','Colocar o aprendizado em ação, compartilhar o evangelho e abençoar vidas onde Deus nos chamar.','textarea'],'pillar3_note'=>['Terceiro pilar: assinatura','O chamado em movimento'],
        ]],
        'courses'=>['Home: cursos disponíveis',[
            'courses_eyebrow'=>['Chamada superior','SEU PRÓXIMO PASSO COMEÇA AQUI'],
            'courses_title'=>['Título',"Cursos para sua\njornada de formação.",'textarea'],
            'courses_intro'=>['Apresentação','Encontre uma oportunidade para crescer no conhecimento, na fé e no serviço.','textarea'],
            'courses_link'=>['Texto do link','Explorar todos os cursos'],'courses_note'=>['Nota abaixo dos cursos','Cursos gratuitos: crie sua conta ou entre para se inscrever. Cursos pagos: inscrição pelo checkout.','textarea'],
            'courses_empty'=>['Mensagem sem cursos','Novos cursos em breve. Uma jornada de aprendizado está sendo preparada para você.','textarea'],
            'courses_limit'=>['Quantidade de cursos (1–12)',3,'number'],
        ]],
        'scripture'=>['Home: fundamento bíblico',[
            'scripture_eyebrow'=>['Chamada superior','O FUNDAMENTO DO NOSSO PROPÓSITO'],
            'scripture_text'=>['Texto',"Preparar os santos para a obra do ministério,\npara que o corpo de Cristo seja edificado.",'textarea'],
            'scripture_ref'=>['Referência','Inspirado em Efésios 4:12'],
        ]],
        'journey'=>['Home: convite final',[
            'journey_eyebrow'=>['Chamada superior','HÁ UM PRÓXIMO PASSO PARA VOCÊ'],
            'journey_title'=>['Título',"Aprenda. Sirva.\nFaça parte.",'textarea'],
            'journey_text'=>['Apresentação','Seu crescimento também pode abençoar outras vidas. Encontre o seu curso e caminhe com a gente.','textarea'],
            'journey_button'=>['Botão','Começar minha jornada'],'journey_url'=>['Destino do botão','catalog','url'],
        ]],
        'footer'=>['Rodapé',[
            'footer_logo'=>['Logo específico do rodapé',0,'image'],
            'footer_subtitle'=>['Assinatura','Formação para a vida. Serviço para o Reino.'],
            'footer_text'=>['Apresentação','Uma igreja que aprende, cresce e compartilha o amor de Cristo.','textarea'],
            'footer_links_title'=>['Título dos links','SUA JORNADA'],
            'footer_purpose_title'=>['Título do propósito','NOSSO PROPÓSITO'],
            'footer_purpose'=>['Texto do propósito',"Discipular.\nAperfeiçoar.\nEnviar.",'textarea'],
            'footer_ref'=>['Referência bíblica','Efésios 4:12'],
            'footer_copyright'=>['Complemento do copyright','Cursos livres'],
            'footer_note'=>['Frase final','Enraizados na Palavra. Prontos para servir.'],
        ]],
    ];
}
function shalom_value(string $key) {
    foreach(shalom_design_fields() as $section){if(isset($section[1][$key])){return get_theme_mod('shalom_'.$key,$section[1][$key][1]);}}
    return '';
}
function shalom_text(string $key,bool $breaks=false): void {$text=esc_html((string)shalom_value($key));echo $breaks?nl2br($text):$text;}
function shalom_brand_name(): string {return function_exists('nexo_settings')?nexo_settings()['brand_name']:get_bloginfo('name');}
function shalom_link(string $key): string {
    $v=(string)shalom_value($key);if(in_array($v,['catalog','login','account','dashboard'],true)){return shalom_url($v);}if(str_starts_with($v,'/')){return home_url($v);}return esc_url($v);
}
function shalom_sanitize_url($value): string {if(in_array($value,['catalog','login','account','dashboard'],true)){return $value;}return esc_url_raw((string)$value,['http','https','mailto','tel']);}
function shalom_sanitize_order($value): string {$allowed=['hero','strip','purpose','courses','scripture','journey'];$list=array_values(array_unique(array_intersect(array_map('trim',explode(',',(string)$value)),$allowed)));return implode(',',$list);}
function shalom_sanitize_bool($v): bool {return (bool)$v;}
function shalom_sanitize_count($v): int {return max(1,min(12,absint($v)));}
function shalom_design_customize(\WP_Customize_Manager $c): void {
    $c->add_panel('shalom_design',['title'=>'Identidade e home Shalom','priority'=>25,'description'=>'Personalize toda a apresentação. Os dados e as matrículas dos cursos são mantidos no plugin.']);
    foreach(shalom_design_fields() as $section=>$data){$id='shalom_'.$section;$c->add_section($id,['title'=>$data[0],'panel'=>'shalom_design']);foreach($data[1] as $key=>$field){$type=$field[2]??'text';$sanitize=match($type){'checkbox'=>'shalom_sanitize_bool','image'=>'absint','url'=>'shalom_sanitize_url','number'=>'shalom_sanitize_count','textarea'=>'sanitize_textarea_field',default=>'sanitize_text_field'};$setting='shalom_'.$key;$c->add_setting($setting,['default'=>$field[1],'sanitize_callback'=>$sanitize]);$args=['section'=>$id,'label'=>$field[0],'type'=>$type,'description'=>$type==='url'?'Use uma URL, /#cursos, /#proposito ou catalog, login, account, dashboard.':''];if($type==='image'){unset($args['type']);$c->add_control(new \WP_Customize_Media_Control($c,$setting,$args+['mime_type'=>'image']));}else{$c->add_control($setting,$args);}}}
    $c->add_section('shalom_layout',['title'=>'Ordem e visibilidade das seções','panel'=>'shalom_design']);
    $c->add_setting('shalom_section_order',['default'=>'hero,strip,purpose,courses,scripture,journey','sanitize_callback'=>'shalom_sanitize_order']);$c->add_control('shalom_section_order',['label'=>'Ordem das seções','description'=>'Separe por vírgulas: hero, strip, purpose, courses, scripture, journey. Remova uma seção para ocultá-la.','type'=>'text','section'=>'shalom_layout']);
    $c->add_section('shalom_palette',['title'=>'Cores, fontes e cartões','panel'=>'shalom_design']);
    if(class_exists('NexoCursos\\Brand')){
        foreach(\NexoCursos\Brand::colors() as $key=>$data){$id='nexo_settings['.$key.']';$c->add_setting($id,['type'=>'option','default'=>$data[1],'capability'=>'manage_options','sanitize_callback'=>'sanitize_hex_color']);$c->add_control(new \WP_Customize_Color_Control($c,$id,['label'=>$data[0],'section'=>'shalom_palette']));}
        foreach(['body_font'=>'Fonte dos textos','heading_font'=>'Fonte dos títulos'] as $key=>$label){$id='nexo_settings['.$key.']';$c->add_setting($id,['type'=>'option','default'=>'modern','capability'=>'manage_options','sanitize_callback'=>static fn($v)=>array_key_exists($v,\NexoCursos\Brand::fonts())?$v:'modern']);$c->add_control($id,['label'=>$label,'section'=>'shalom_palette','type'=>'select','choices'=>['modern'=>'Moderna','geometric'=>'Geométrica','classic'=>'Clássica']]);}
        $c->add_setting('nexo_settings[corner_radius]',['type'=>'option','default'=>10,'capability'=>'manage_options','sanitize_callback'=>static fn($v)=>max(0,min(30,absint($v)))]);$c->add_control('nexo_settings[corner_radius]',['label'=>'Arredondamento (0–30 px)','type'=>'number','section'=>'shalom_palette','input_attrs'=>['min'=>0,'max'=>30]]);
        $c->add_setting('nexo_settings[brand_name]',['type'=>'option','default'=>'Igreja Shalom','capability'=>'manage_options','sanitize_callback'=>'sanitize_text_field']);$c->add_control('nexo_settings[brand_name]',['label'=>'Nome da marca','section'=>'title_tagline']);
    }
}
add_action('customize_register','shalom_design_customize');
/** One-time migration only: never rewrite a user's later choices. */
function shalom_design_migrate(): void {
    if(get_theme_mod('shalom_design_profile')==='gps-1'){return;}
    $settings=(array)get_option('nexo_settings',[]);if(empty($settings['logo_id'])&&get_theme_mod('custom_logo')){$settings['logo_id']=absint(get_theme_mod('custom_logo'));}
    if(empty($settings['brand_name'])||$settings['brand_name']==='Nexo Cursos'){$settings['brand_name']='Igreja Shalom';}
    // These historical colors identify prior defaults only; the active palette comes from Brand.
    foreach(['primary_color'=>['#167b60','#173b34'],'accent_color'=>['#dbef8c','#c6a56c']] as $key=>$old){if(empty($settings[$key])||in_array(strtolower($settings[$key]),$old,true)){$settings[$key]=$key==='primary_color'?'#071450':'#00AEEF';}}
    update_option('nexo_settings',$settings);set_theme_mod('shalom_design_profile','gps-1');
}
add_action('admin_init','shalom_design_migrate');add_action('after_switch_theme','shalom_design_migrate');
add_filter('nexo_navigation_items',static function(array $items): array {foreach($items as $key=>&$item){$item['label']=(string)shalom_value('nav_'.$key);}unset($item);if(!shalom_value('show_purpose_link')){unset($items['purpose']);}if(!shalom_value('show_cart_link')){unset($items['cart']);}return $items;});

add_filter('nexo_available_empty_message',static fn($message)=>(string)shalom_value('courses_empty'));

add_filter('pre_set_theme_mod_custom_logo',static function($new,$old){
    if(function_exists('nexo_settings')){$settings=(array)get_option('nexo_settings',[]);$id=absint($new);if((int)($settings['logo_id']??0)!==$id){$settings['logo_id']=$id;update_option('nexo_settings',$settings);}}
    return absint($new);
},10,2);
