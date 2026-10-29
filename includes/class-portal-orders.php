<?php
/** HPOS-safe order access, scoped by purchased course snapshots. */
namespace NexoCursos;
defined('ABSPATH') || exit;
final class PortalOrders {
    public static function table(): string {global $wpdb;return $wpdb->prefix.'nexo_order_courses';}
    public static function boot(): void {
        if(get_option('nexo_order_index_db')!=='1'){self::install();}
        foreach(['woocommerce_update_order','woocommerce_new_order','woocommerce_payment_complete','woocommerce_order_status_changed','woocommerce_checkout_order_processed','woocommerce_store_api_checkout_order_processed','woocommerce_order_refunded'] as $hook){add_action($hook,[self::class,'index_order'],30,1);}
        foreach(['woocommerce_delete_order','woocommerce_trash_order'] as $hook){add_action($hook,[self::class,'remove_index']);}
        add_action('nexo_order_index_backfill',[self::class,'backfill']);add_action('init',[self::class,'schedule'],30);
    }
    public static function install(): void {
        global $wpdb;require_once ABSPATH.'wp-admin/includes/upgrade.php';$table=self::table();$collate=$wpdb->get_charset_collate();
        dbDelta("CREATE TABLE {$table} (
            order_id bigint(20) unsigned NOT NULL,
            course_id bigint(20) unsigned NOT NULL,
            created_at bigint(20) unsigned NOT NULL,
            order_status varchar(32) NOT NULL,
            PRIMARY KEY  (order_id,course_id),
            KEY course_date (course_id,created_at),
            KEY status_date (order_status,created_at)
        ) {$collate};");update_option('nexo_order_index_db','1',false);
    }
    public static function schedule(): void {if(function_exists('wc_get_orders')&&!get_option('nexo_order_index_done')&&!wp_next_scheduled('nexo_order_index_backfill')){wp_schedule_single_event(time()+10,'nexo_order_index_backfill');}}
    public static function backfill(): void {
        if(!function_exists('wc_get_orders')||get_option('nexo_order_index_done')){return;}
        if(!add_option('nexo_order_index_lock',time(),'','no')){if((int)get_option('nexo_order_index_lock')<time()-120){delete_option('nexo_order_index_lock');}return;}
        try{$page=max(1,(int)get_option('nexo_order_index_page',1));$ids=wc_get_orders(['type'=>'shop_order','limit'=>100,'page'=>$page,'orderby'=>'ID','order'=>'ASC','return'=>'ids','status'=>array_keys(wc_get_order_statuses())]);foreach($ids as $id){self::index_order($id);}if(count($ids)<100){update_option('nexo_order_index_done','1',false);}else{update_option('nexo_order_index_page',$page+1,false);self::schedule();}}finally{delete_option('nexo_order_index_lock');}
    }
    public static function remove_index($id): void {global $wpdb;$wpdb->delete(self::table(),['order_id'=>absint($id)],['%d']);}
    public static function item_courses($item): array {$saved=$item->get_meta('_nexo_course_ids',true);$ids=is_array($saved)?array_map('absint',$saved):Core::product_courses((int)$item->get_product_id(),(int)$item->get_variation_id());return array_values(array_unique(array_filter($ids)));}
    public static function index_order($value): void {
        if(!function_exists('wc_get_order')){return;}$order=is_a($value,'WC_Order')?$value:wc_get_order(absint($value));if(!$order||!is_a($order,'WC_Order')||$order->get_type()!=='shop_order'){return;}
        $id=$order->get_id();$ids=[];foreach($order->get_items() as $item){$ids=array_merge($ids,self::item_courses($item));}self::remove_index($id);if($order->get_status()==='trash'){return;}global $wpdb;
        foreach(array_unique($ids) as $course){$wpdb->replace(self::table(),['order_id'=>$id,'course_id'=>$course,'created_at'=>$order->get_date_created()?$order->get_date_created()->getTimestamp():time(),'order_status'=>$order->get_status()],['%d','%d','%d','%s']);}
    }
    public static function storewide(): bool {return current_user_can('manage_options')||current_user_can('manage_woocommerce');}
    public static function visible_items($order,int $course=0): array {
        $out=[];foreach($order->get_items() as $id=>$item){$ids=self::item_courses($item);if($course){if(in_array($course,$ids,true)&&Core::can_manage($course)){$out[$id]=$item;}continue;}
            if(self::storewide()){$out[$id]=$item;continue;}foreach($ids as $cid){if(Core::can_manage($cid)){$out[$id]=$item;break;}}}return $out;
    }
    public static function access(int $id) {
        if(!Portal::allowed()||!function_exists('wc_get_order')){return new \WP_Error('forbidden','Pedidos indisponíveis.');}$order=wc_get_order($id);
        if(!$order||!is_a($order,'WC_Order')||$order->get_type()!=='shop_order'||$order->get_status()==='trash'||(!self::storewide()&&!self::visible_items($order))){return new \WP_Error('forbidden','Pedido indisponível para sua conta.');}return $order;
    }
    public static function full($order): bool {
        if(self::storewide()){return true;}if(!Portal::allowed()||!$order->get_items()){return false;}
        foreach($order->get_items() as $item){$ids=self::item_courses($item);if(!$ids){return false;}foreach($ids as $id){if(!Core::can_manage($id)){return false;}}}return true;
    }
    public static function filters(array $d) {
        $from=Portal::text($d,'from')?:wp_date('Y-m-d',strtotime('-29 days'));$to=Portal::text($d,'to')?:wp_date('Y-m-d');$start=Calendar::date($from);$end=Calendar::date($to);$status=Portal::text($d,'order_status');$course=Portal::id($d,'course_id');
        if(!$start||!$end||$to<$from||(int)$start->diff($end)->days>366){return new \WP_Error('dates','Escolha um período válido de até um ano.');}
        $statuses=array_map(static fn($s)=>substr($s,3),array_keys(wc_get_order_statuses()));if($status&&!in_array($status,$statuses,true)){return new \WP_Error('status','Escolha uma situação válida.');}if($course&&is_wp_error(Portal::course($course))){return new \WP_Error('course','Curso indisponível.');}
        $currency=strtoupper(Portal::text($d,'currency')?:get_woocommerce_currency());if(!isset(get_woocommerce_currencies()[$currency])){return new \WP_Error('currency','Moeda inválida.');}
        return ['from'=>$from,'to'=>$to,'start'=>$start->getTimestamp(),'end'=>$end->setTime(23,59,59)->getTimestamp(),'status'=>$status,'course'=>$course,'order'=>Portal::id($d,'order_number'),'currency'=>$currency];
    }
    public static function listing(array $f,int $page=1,int $per=20): array {
        $page=max(1,$page);$per=max(1,min(200,$per));if(self::storewide()&&!$f['course']){
            $args=['type'=>'shop_order','status'=>$f['status']?[$f['status']]:array_keys(wc_get_order_statuses()),'limit'=>$per,'page'=>$page,'paginate'=>true,'orderby'=>'date','order'=>'DESC','date_created'=>$f['start'].'...'.$f['end']];if($f['order']){$args['include']=[$f['order']];}$r=wc_get_orders($args);return ['orders'=>$r->orders,'total'=>(int)$r->total,'pages'=>max(1,(int)$r->max_num_pages)];
        }
        $ids=$f['course']?[$f['course']]:Core::course_ids_for_manager();if(!$ids){return ['orders'=>[],'total'=>0,'pages'=>1];}global $wpdb;$holders=implode(',',array_fill(0,count($ids),'%d'));$where='course_id IN ('.$holders.') AND created_at >= %d AND created_at <= %d';$args=array_merge($ids,[$f['start'],$f['end']]);if($f['status']){$where.=' AND order_status=%s';$args[]=$f['status'];}if($f['order']){$where.=' AND order_id=%d';$args[]=$f['order'];}
        $total=(int)$wpdb->get_var($wpdb->prepare('SELECT COUNT(DISTINCT order_id) FROM '.self::table().' WHERE '.$where,$args));$query=$wpdb->prepare('SELECT order_id,MAX(created_at) AS sort_date FROM '.self::table().' WHERE '.$where.' GROUP BY order_id ORDER BY sort_date DESC,order_id DESC LIMIT %d OFFSET %d',array_merge($args,[$per,($page-1)*$per]));$orders=[];
        foreach($wpdb->get_col($query) as $id){$o=self::access((int)$id);if(!is_wp_error($o)&&self::visible_items($o,$f['course'])){$orders[]=$o;}}
        return ['orders'=>$orders,'total'=>$total,'pages'=>max(1,(int)ceil($total/$per))];
    }
    public static function amount($order,int $course=0): float {
        if(self::storewide()&&!$course){return max(0,(float)$order->get_total()-(float)$order->get_total_refunded());}$total=0;
        foreach(self::visible_items($order,$course) as $id=>$item){$taxes=$item->get_taxes();$refunded_tax=0;foreach(array_keys($taxes['total']??[]) as $tax_id){$refunded_tax+=(float)$order->get_tax_refunded_for_item($id,$tax_id);}$total+=max(0,(float)$item->get_total()+(float)$item->get_total_tax()-(float)$order->get_total_refunded_for_item($id)-$refunded_tax);}return $total;
    }
    private static function lock(int $id): bool {$key='nexo_order_action_'.$id;if(add_option($key,time(),'','no')){return true;}if((int)get_option($key)<time()-120){delete_option($key);return add_option($key,time(),'','no');}return false;}
    public static function mutate(string $action,array $d) {
        if(!function_exists('wc_get_order')){return new \WP_Error('woo','As vendas estão indisponíveis.');}if($action==='order_create'){return self::create($d);}
        $id=Portal::id($d,'order_id');$order=self::access($id);if(is_wp_error($order)){return $order;}if(!self::full($order)){return new \WP_Error('scope','Este pedido inclui itens fora da sua gestão. A coordenação responsável pelo pedido completo precisa fazer a alteração.');}
        if(!self::lock($id)){return new \WP_Error('busy','Este pedido está sendo atualizado. Aguarde e tente novamente.');}
        try{
            if($action==='order_note'){$note=Portal::text($d,'note');if(!$note){return new \WP_Error('note','Escreva a observação.');}$order->add_order_note(mb_substr($note,0,2000),false,true);}
            elseif($action==='order_customer'){
                if(!Portal::finance()||empty($d['confirm_customer'])){return new \WP_Error('forbidden','A vinculação da conta exige permissão financeira e confirmação.');}$user=get_user_by('email',sanitize_email(Portal::text($d,'email')));if(!$user){return new \WP_Error('account','Conta não encontrada. Cadastre o aluno antes de vinculá-lo.');}$order->set_customer_id($user->ID);$order->save();$order->add_order_note('Conta do aluno vinculada pela gestão.');Core::sync_order($id);
            }
            elseif($action==='order_status'){
                $status=Portal::text($d,'status');$allowed=['pending','on-hold','processing','completed','cancelled','failed'];if(!in_array($status,$allowed,true)||$order->has_status('refunded')){return new \WP_Error('status','Situação inválida para este pedido.');}
                if(!Portal::finance()&&(!$order->has_status('processing')||$status!=='completed')){return new \WP_Error('forbidden','Somente a equipe financeira pode alterar a situação do pagamento.');}
                if(in_array($status,['processing','completed'],true)&&!$order->has_status(['processing','completed'])&&empty($d['confirm_paid'])){return new \WP_Error('payment','Confirme que o pagamento foi recebido antes de liberar o acesso.');}
                $order->update_status($status,'Situação atualizada pela gestão da plataforma.',true);Core::sync_order($id);
            }
            elseif($action==='order_refund'){
                if(!Portal::finance()||empty($d['confirm_refund'])){return new \WP_Error('forbidden','O reembolso exige permissão financeira e confirmação.');}$method=Portal::text($d,'refund_method');$reason=Portal::text($d,'reason');if(!in_array($method,['manual','gateway'],true)||!$reason||!$order->has_status(['processing','completed'])){return new \WP_Error('refund','Informe o motivo e como o valor será devolvido para um pedido pago.');}
                $amount=(float)$order->get_remaining_refund_amount();if($amount<=0){return new \WP_Error('refund','Este pedido não tem saldo para reembolso.');}$lines=[];
                foreach($order->get_items(['line_item','shipping','fee']) as $iid=>$item){$type=$item->get_type();$tax=[];foreach(($item->get_taxes()['total']??[]) as $tid=>$total){$tax[$tid]=max(0,(float)$total-(float)$order->get_tax_refunded_for_item($iid,$tid,$type));}$lines[$iid]=['qty'=>$type==='line_item'?max(0,(float)$item->get_quantity()+(float)$order->get_qty_refunded_for_item($iid)):0,'refund_total'=>max(0,(float)$item->get_total()-(float)$order->get_total_refunded_for_item($iid,$type)),'refund_tax'=>$tax];}
                $refund=wc_create_refund(['order_id'=>$id,'amount'=>$amount,'reason'=>mb_substr($reason,0,1000),'line_items'=>$lines,'refund_payment'=>$method==='gateway','restock_items'=>true]);if(is_wp_error($refund)){return new \WP_Error('refund',$refund->get_error_message());}Core::sync_order($id);
            }else{return new \WP_Error('action','Operação inválida.');}
            self::index_order($id);return ['message'=>'Pedido atualizado. As matrículas foram conferidas conforme a situação do pedido.','view'=>'orders','args'=>['order_id'=>$id]];
        }catch(\Throwable $e){return new \WP_Error('order','Não foi possível concluir a alteração. Confira o pedido antes de tentar novamente.');}finally{delete_option('nexo_order_action_'.$id);}
    }
    private static function create(array $d) {
        $user=get_user_by('email',sanitize_email(Portal::text($d,'email')));$pid=Portal::id($d,'product_id');$product=$pid?wc_get_product($pid):false;
        if(!$user||!$product||!PortalSales::product_allowed($product)||$product->get_status()!=='publish'||!$product->is_purchasable()||!$product->is_in_stock()){return new \WP_Error('order','Confira a conta do aluno e escolha um produto publicado e disponível.');}
        if(!Portal::finance()&&!PortalPeople::student_role($user)){return new \WP_Error('account','Esta conta pertence à equipe. Peça à coordenação para vinculá-la.');}
        $key=Portal::text($d,'request_key');if(!preg_match('/^[a-zA-Z0-9-]{16,64}$/D',$key)){return new \WP_Error('request','Atualize o formulário para criar o pedido.');}$cache='nexo_new_order_'.get_current_user_id().'_'.hash('sha256',$key);$previous=(int)get_transient($cache);if($previous&&!is_wp_error(self::access($previous))){return ['message'=>'O pedido já foi criado.','view'=>'orders','args'=>['order_id'=>$previous]];}
        if(!add_option($cache.'_lock',time(),'','no')){return new \WP_Error('busy','Este pedido já está sendo criado. Aguarde e consulte a lista.');}$order=null;
        try{
            $order=wc_create_order(['customer_id'=>$user->ID,'created_via'=>'nexo-portal','status'=>'pending']);if(is_wp_error($order)){return $order;}
            $order->set_billing_email($user->user_email);$order->set_billing_first_name(get_user_meta($user->ID,'billing_first_name',true)?:$user->display_name);$order->set_billing_last_name(get_user_meta($user->ID,'billing_last_name',true));$order->set_billing_country(get_user_meta($user->ID,'billing_country',true)?:WC()->countries->get_base_country());
            $iid=$order->add_product($product,1);$item=$order->get_item($iid);$courses=Core::product_courses($product->is_type('variation')?$product->get_parent_id():$pid,$product->is_type('variation')?$pid:0);if($courses){$item->update_meta_data('_nexo_course_ids',$courses);$item->save();}
            $coupon=Portal::text($d,'coupon');if($coupon){$result=$order->apply_coupon(wc_format_coupon_code($coupon));if(is_wp_error($result)){$order->delete(true);return new \WP_Error('coupon',$result->get_error_message());}}
            $order->calculate_totals();$order->save();$order->add_order_note('Pedido criado na área de gestão por '.wp_get_current_user()->display_name.'.');self::index_order($order);set_transient($cache,$order->get_id(),DAY_IN_SECONDS);
            if(!empty($d['send_invoice'])){$emails=WC()->mailer()->get_emails();if(isset($emails['WC_Email_Customer_Invoice'])){$emails['WC_Email_Customer_Invoice']->trigger($order->get_id(),$order);}}
            return ['message'=>'Pedido criado como pagamento pendente. O acesso será liberado após a confirmação do pagamento.','view'=>'orders','args'=>['order_id'=>$order->get_id()]];
        }catch(\Throwable $e){if($order instanceof \WC_Order&&$order->get_id()){$order->add_order_note('A criação pela gestão não foi concluída. Revise este pedido antes de continuar.');}return new \WP_Error('order','Não foi possível concluir o pedido. Confira a lista antes de tentar novamente.');}finally{delete_option($cache.'_lock');}
    }
}
