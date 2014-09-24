<?php 
/*
  Plugin Name: WooCommerce Paymaster Payment Gateway
  Plugin URI: http://qazomardok.ru/
  Description: Allows you to use PayMaster (extended Webmoney Merchand) payment gateway with the WooCommerce plugin. Requiered Woocommerce 2.2.3
  Version: 0.1.9 Develop Edition
  Author: Grishunin Anton
  Author URI: http://qazomardok.ru
 */
		

/************************************************************
 *  
 *  
 * 		Активация платежного шлюза
 *		paymaster_rub_currency_symbol()
 *		paymaster_rub_currency()
 *		paym_style()
 *
 *		woocommerce_paymaster()
 *  
 *************************************************************/		

add_filter( 'woocommerce_currency_symbol', 'paymaster_rub_currency_symbol', 10, 2 );
add_filter( 'woocommerce_currencies', 'paymaster_rub_currency', 10, 1 );
add_action( 'admin_enqueue_scripts', 'paym_style' );	
add_action('plugins_loaded', 'woocommerce_paymaster', 0);
	
function paymaster_rub_currency_symbol( $currency_symbol, $currency ) {
    if($currency == "RUB") {
        $currency_symbol = 'р.';
    }
    return $currency_symbol;
}

function paymaster_rub_currency( $currencies ) {
    $currencies["RUB"] = 'Russian Roubles';
    return $currencies;
}

function paym_style() {
	wp_register_style( 'paym-woocommerce-style', plugin_dir_url(__FILE__).'css/paym.css', null, 1.0, 'screen' );
	wp_enqueue_style( 'paym-woocommerce-style' );
		
	$thiser = get_option('woocommerce_paymaster_settings');	
			if ( $thiser['enabled'] != "yes"){
			$v = current_time('timestamp').'.0';
			wp_register_style( 'paymalt-woocommerce-style', plugin_dir_url(__FILE__).'css/paym_alt.css', null, $v, 'screen' );
			wp_enqueue_style( 'paymalt-woocommerce-style' );
	}
}

function woocommerce_paymaster(){

/***************************************	
 *		add_paymaster_gateway()
 *		url_for_return_list()
 *		url_for_return()
 *		return_payment()
 *		wc_pm_return_payment()
 *		paym_columns_names()
 *		paym_columns_content()
 *		add_views_column_css()
 *		paym_columns_content_ident()
 *		err_codes()
 *		paym_columns_content_status()
 *		get_sys_payment_id()
 *
 *		class WC_paymaster()
****************************************/ 
	add_filter('woocommerce_payment_gateways', 'add_paymaster_gateway');
	add_action('admin_head', 'add_views_column_css');
	add_filter('manage_edit-shop_order_columns', 'paym_columns_names', 11 );
	add_action('manage_shop_order_posts_custom_column', 'paym_columns_content', 2 );
	//add_action('woocommerce_admin_order_actions_start','wc_customer_order_csv_export_export_order', 10, 2);
	add_action('paym_columns_content_ident_col',  'paym_columns_content_ident', 2);
	//add_action('woocommerce_admin_order_actions_end',  'paym_columns_content_buttons', 2);
	add_action('paym_columns_content_status_col',  'paym_columns_content_status', 2);
	add_action('woocommerce_order_actions_end',  'return_payment', 2);
	add_action('woocommerce_order_actions_end',  'wc_pm_return_payment', 2);
	add_action('admin_print_scripts', 'pm_action_javascript', 999);
	add_action('wp_ajax_paym_status', 'paym_status_callback');
	
	function add_paymaster_gateway($methods){
		$methods[] = 'WC_paymaster';
		return $methods;
	}
	
	function url_for_return_list($inv_id, $pm_id = NULL) {
		global $woocommerce;
			
		$thiser = new WC_paymaster;
		
		$arg['login'] =  $thiser->get_option('paymaster_robo_login');
		$arg['password'] =  $thiser->get_option('paymaster_robo_pass');
		$arg['nonce'] = wp_create_nonce( $inv_id.current_time('timestamp').rand(1,3));
		$arg['accountID'] = $thiser->get_option('account_id');
	if($pm_id==NULL) {
		$argu['paymentID'] = get_post_meta($inv_id, '_order_id_in_paymaster', true);
	} else {
		$argu['paymentID'] = $pm_id;
	}
		$arg['periodFrom'] = '';
		$arg['periodTo'] = '';
		$arg['externalID'] = '';

		$str = $arg['login'].";".$arg['password'].";".$arg['nonce'].";".$arg['accountID'].";".$argu['paymentID'].";".$arg['periodFrom'].";".$arg['periodTo'].";".$arg['externalID'];

		$hash = base64_encode(sha1($str, true));
		$returns['url'] = 'https://paymaster.ru/partners/rest/listRefunds?'.http_build_query($arg).'&hash='.$hash.'&paymentID='.$argu['paymentID'];
		$returns['arg'] = $arg;
		return $returns;
		}
		
	function url_for_return($inv_id) {
		global $woocommerce;
			
		$thiser = new WC_paymaster;
		$order = new WC_Order($inv_id);
		
		$arg['login'] =  $thiser->get_option('paymaster_robo_login');
		$arg['password'] =  $thiser->get_option('paymaster_robo_pass');
		$arg['nonce'] = wp_create_nonce( $inv_id.current_time('timestamp'));
		$arg['paymentID'] = get_post_meta($inv_id, '_order_id_in_paymaster', true);
		$argu['amount'] = $order->order_total;
		$arg['externalID'] = '';

		$str = $arg['login'].";".$arg['password'].";".$arg['nonce'].";".$arg['paymentID'].";".$argu['amount'].";".$arg['externalID'];

		$hash = base64_encode(sha1($str, true));
		$returns['url'] = 'https://paymaster.ru/partners/rest/refundPayment?'.http_build_query($arg).'&hash='.$hash.'&amount='.$argu['amount'];
		$returns['arg'] = $arg;
		return $returns;

	}		

	function return_payment($inv_id) {
			
		global $woocommerce;
		$order = new WC_Order($inv_id);
		$url = url_for_return_list($inv_id);
		$answ = json_decode(file_get_contents($url['url']));
		$Refunds = $answ->Response->Refunds;
		
		echo '<li class="wide"><div id="delete-action">';
	if($error = err_codes($answ->ErrorCode)) {
				echo $error; 
				
	} else {	
		if((($order->status=='completed') or ($order->status=='processing'))  and ($order->payment_method=='paymaster')) {
		
	if((!isset($Refunds[0]->RefundID)) and (count($answ->Response->Refunds)==0))	 {
		
		echo '<a class="submitdelete deletion" href="'. wp_nonce_url( admin_url( 'post.php?post='.$order->id.'&action=edit&order_id=' . base64_encode(base64_encode($Refunds[0]->refundPayment)) ), 'wc_pm_return_payment' ).'">Оформить возврат денег</a> &bull; '. $order->order_total. ''.get_option('woocommerce_currency');
			
		}
			
		}  else {
		
		
	}
	 
	if((get_post_meta($inv_id, '_order_id_in_paymaster', true)!='') and (count($answ->Response->Refunds)==1)) {	
		echo '<p>Возврат на сумму '. $Refunds[0]->Amount.' '.get_option('woocommerce_currency');
			switch($Refunds[0]->State) {
			case'INITIATED': echo 'начат'; break;
			case'PROCESSING': echo 'проводится'; break;
			case'SUCCESS': echo 'завершен успешно'; break;
			case'COMPLETE': echo 'завершен успешно'; break;
			case'CANCELLED': echo 'завершен неуспешно'; break;
			}
		echo '.<br>Платеж №<a href="https://paymaster.ru/partners/ru/Payments/FullDetails/'. $Refunds[0]->PaymentID .'" target="blank">'. $Refunds[0]->PaymentID .'</a>. ID возврата: '. $Refunds[0]->RefundID .'</p>';
		}
	}

	echo '</div></li>';
		
		 
				
	}

	function wc_pm_return_payment($inv_id){
		
		
		
		
		parse_str(parse_url( 'http://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'] ,PHP_URL_QUERY), $actual_link);
		parse_str(parse_url( wp_nonce_url( admin_url( 'post.php?post='.$_GET['post'].'&action=edit&order_id=' . $_GET['order_id'] ), 'wc_pm_return_payment' ),PHP_URL_QUERY),$need_link);
		
		if ($actual_link['_wpnonce'] === $need_link['amp;_wpnonce']){
		
			global $woocommerce;
		
		$order = new WC_Order($inv_id);
		
		
		$url = url_for_return($_GET['post']);
		$answrw = json_decode(file_get_contents($url['url']));
		
		
		?><li class="wide">
			<div id="delete-action"><?
		if($error = err_codes($answrw->ErrorCode, $answrw->ErrorDesc)) { 
			//		echo $error; 
					
			} else {
			$Refund	= $answrw->Refund;
			 echo 'Платеж №'.$Refund->PaymentID.' ';
			switch($Refund->State) {
				case"PENDING": echo 'поставлен в очередь на совершение операции возврата'; break;
				case"EXECUTING": echo 'в стадии проведения транзакции возврата платежа'; break;
				case"SUCCESS": echo 'успешно возвращен.'; break;
				case"FAILURE": echo 'не возвращен.'; break;
			}
		
			$STATUS['NOTE'] 	= 'Оператором инициализирован возврат средств';
			$STATUS['STATUS']	= 'refunded';
			$order->add_order_note('[PAYM]'.serialize($STATUS).'[/PAYM]'); 
			$order->update_status('refunded','[PAYM]'.serialize($STATUS).'[/PAYM]');
		//			if ( !update_post_meta(...) ) add_post_meta(...) )
			add_post_meta( $STATUS['PA_WPID'], '_payment_status', $STATUS['PA_ID'], true );
			
			?><script type="text/javascript">document.location.href = '<? echo admin_url( 'edit.php?post_type=shop_order') ?>';</script><?
			
			}
	?>
		</div>

		</li><?
		
			
		} else {
			echo '';
		}
	}

	function paym_columns_names($columns){
		$new_columns = (is_array($columns)) ? $columns : array();
		unset( $new_columns['order_actions'] );

		$new_columns['PAYM_IDENT'] = 'Счет покупателя';

		$new_columns['order_actions'] = $columns['order_actions'];
		return $new_columns;
	}

	function paym_columns_content($column){
		global $post;
		$data = get_post_meta( $post->ID );

		if ( $column == 'PAYM_STATUS' ) {    
			do_action('paym_columns_content_status_col', $post->ID);
		}
		if ( $column == 'PAYM_IDENT' ) {   
		   do_action('paym_columns_content_ident_col', $post->ID);
		}
		
	}

	function add_views_column_css($order){

		echo '	
		<script type="text/javascript">
		function shhi(id)
		{
		document.getElementById(\'jj_row\'+id);
		//
		// Проверяем отображается ли в данный момент этот блок
		//
		if (  document.getElementById(\'jj_row\'+id).style.display == \'none\' )
		{
			  document.getElementById("jj_row"+id).style.display = \'block\';
			  document.getElementById("j"+id).className = \'jj opened\';
		}
		else
		{
			 document.getElementById(\'jj_row\'+id).style.display = \'none\';
			  document.getElementById("j"+id).className = \'jj\';
		}
	} </script>
		';
	}

	function paym_columns_content_ident( $order ) {
		
		$thiser = new WC_paymaster;
		$co = new WC_Order($order);
		
		
		if($co->payment_method=='paymaster') {
		
		$arg['login'] =  $thiser->get_option('paymaster_robo_login');
		$arg['password'] =  $thiser->get_option('paymaster_robo_pass');
		$arg['invoiceID'] = $order;
		$arg['siteAlias'] =  $thiser->get_option('paymaster_merchant');
		$arg['nonce'] = wp_create_nonce( $order.current_time('timestamp'));
		$str = $arg['login'].";".$arg['password'].";".$arg['nonce'].";".$arg['invoiceID'].";".$arg['siteAlias'];
		$ref = '';
		
		$hash = base64_encode(sha1($str, true));
		$url = 'https://paymaster.ru/partners/rest/getpaymentbyinvoiceid?'.http_build_query($arg).'&hash='.$hash;
		
		$url_hash = base64_encode(base64_encode($url));
		
		echo '<div id="paym_order_'.$order.'" handler="paym_order_status" order="'.$order.'" hash='.$url_hash.'><img src="'.plugins_url('/img/payloader.gif', __FILE__).'" /></div>';
		
		
		}
	}

	function pm_action_javascript() {
	echo "
	<script type=\"text/javascript\" >
	jQuery(document).ready(function($) {
	
	obj = $(\"div[handler='paym_order_status']\");
		jQuery.each(obj, function(i, val) {
			
			var data = {
				action: 'paym_status',
				hash: $(val).attr('hash'),
				order: $(val).attr('order'),
			};
			
			var pm_id = $(val).attr('id');
				
			$.post(ajaxurl, data, function(response) {
				console.log(response);
				$(\"#\"+pm_id).html(response);
			});
		});
	});
	</script>"; } 
	
	// Получение статуса из Paymaster. Используется Аякс
	function paym_status_callback() {
		
		$order = $_POST['order'];
		$hash = $_POST['hash'];
		
		$url = base64_decode(base64_decode($hash));
		//echo $_POST['order'].'==='.$url; 
		
		
		//exit();
		$thiser = new WC_paymaster;
		
		$answ = json_decode(file_get_contents($url));
		
		
		if($answ->ErrorCode != 0) echo '<b>'.err_codes($answ->ErrorCode).'</b><br>';
		$ref = '';
		if($thiser->get_option('refunds_on')=='yes'){	
			$urlreturn = url_for_return_list($order, $answ->Payment->PaymentID);
			$answrreturn = json_decode(file_get_contents($urlreturn['url']));
			echo $answrreturn->Payment->PaymentID;
		//	var_dump($answrreturn);
		
			$Refunds = $answrreturn->Response->Refunds;
		
		if($error = err_codes($answrreturn->ErrorCode, $answrreturn->ErrorDesc)) { 
			} else {
			
			switch($Refunds[0]->State) {
				default: $ref = ''; break;
				case"PENDING": $ref = 'Поставлен в очередь на совершение операции возврата'; break;
				case"EXECUTING": $ref = 'В стадии проведения транзакции возврата платежа'; break;
				case"SUCCESS": $ref = 'Был возвращен'; break;
				case"FAILURE": $ref = 'Не возвращен'; break;
			}
			 }
		} 
		
		echo '<a class="paym-status" onclick="shhi('.$order.')">';
		
		if($answ->ErrorCode == 0) {
			switch($answ->Payment->State) {
			default:
			case'INITIATED': echo '<span style="color:#DC019A">Оплачивается</span> '; break;
			case'PROCESSING': echo '<span style="color:#F2A400">Идёт перевод денег</span> '; break;
			case'COMPLETE': echo '<span style="color:#01A362">Оплачено</span> '; break;
			case'CANCELLED': echo '<span style="color:red">Не оплачено</span> '; break;
		}}
		if($ref!='') echo '<br><span style="color:#F2A400">'.$ref.'</span>';
		echo '</a><div style="display:none;" id="jj_row'.$order.'">';
		if($answ->ErrorCode == 0) echo '(<a href="https://paymaster.ru/partners/ru/Payments/FullDetails/'.$answ->Payment->PaymentID.'" target="_blank">№'.$answ->Payment->PaymentID.'</a>)<br>';
		if($answ->Payment->IsTestPayment) echo '<i>Тестовый платеж</i><br>';
		if($answ->ErrorCode == 0) echo '<b>Оплачено с помощью</b>: '.$answ->Payment->PaymentMethod.'<br>';
		if($answ->ErrorCode == 0) echo '<b>Лицевой счёт</b>: '.$answ->Payment->UserIdentifier.'<br>';
		if($answ->ErrorCode == 0) echo '<b>Телефон</b>: '.$answ->Payment->UserPhoneNumber.'<br>';
		echo '</div>';
		exit();
		
	}
	
	function err_codes($code, $desc = NULL) {
		switch($code) {
			default:
			case'-1': $decode = 'Неизвестная ошибка. Сбой в системе PayMaster. Если ошибка повторяется, обратитесь в техподдержку.'; break;
			case'-2': $decode = 'Сетевая ошибка. Сбой в системе PayMaster. Если ошибка повторяется, обратитесь в техподдержку.'; break;
			case'-6': $decode = 'Нет доступа. Неверно указан логин, или у данного логина нет прав на запрошенную информацию.'; break;
			case'-7': $decode = 'Неверная подпись запроса. Неверно сформирован хеш запроса.'; break;
			case'-13': $decode = 'Платеж не найден по номеру счета.'; break;
			case'-14': $decode = 'Повторный запрос с тем же nonce.'; break;
			case'-18': $decode = 'Неверное значение суммы (в случае возвратов имеется в виду значение amount).'; break;
			case'0': $decode = false; break;
		}
	if($desc!=NULL) $decode .= ' '.$desc;
		return $decode;
	}
		
	function paym_columns_content_status( $order ) {
		
		$notes = get_sys_payment_id($order);
		echo $notes['id'];
	}

	function get_sys_payment_id($order_id) { 

	$args = array(
		'status' => 'approve',
		'post_id' => $order_id
	);
	$notes = get_comments($args); $i=0;

	krsort($notes);
	foreach ($notes as $note) {
		
		preg_match_all("@\[PAYM\](.+?)\[\/PAYM\]@i", $note->comment_content, $sernote); 
			if(!$sernote[1][0]) continue;
			$noter[$i] = unserialize($sernote[1][0]);
			
			if($noter[$i]['PA_ID']!=NULL) { $noter['id'] = $noter[$i]['PA_ID'];}
			$i++;
			
		}

		return $noter;
	}

	//class WC_paymaster	
	if (!class_exists('WC_Payment_Gateway')) return; 
	if(class_exists('WC_paymaster')) return;
	class WC_paymaster extends WC_Payment_Gateway{	
	/************************************************************
		
			Класс платежного шлюза
			__construct()
					
			// Проверка, что пользователю доступны платежи в рублях
					is_valid_for_use()
			// Страница настроек
					admin_options()
			// Применение поля "Описание" для кнопки оплаты
					payment_fields()
			// Опции
					init_form_fields()
			// Генерация формы "Оплатить"
					generate_form() 
			// Страница оплаты
					receipt_page()
			// Проверка строки с хешем
				check_ipn_request_is_valid()
			// Обработка ответов
				check_ipn_response()

	*************************************************************/	
		
		public function __construct(){
			
			$this->plugin_dir = plugin_dir_url(__FILE__);

			global $woocommerce;
			$this->id = 'paymaster';
			$this->icon = apply_filters('woocommerce_paymaster_icon', plugin_dir_url(__FILE__).'img/paymaster.png');
			$this->has_fields = false;
			
			$action_adr = 'https://paymaster.ru/Payment/Init';
			
			// Загрузка опций
			$this->init_form_fields();
			$this->init_settings();

			// Определение переменных
			$this->title = $this->get_option('title');
			$this->afterstatus = $this->get_option('afterstatus');
			$this->paymaster_merchant = $this->get_option('paymaster_merchant');
			$this->secret_key = $this->get_option('secret_key');
			$this->hashtype = $this->get_option('hashtype');
			$this->testmode = $this->get_option('testmode');
			$this->debug = $this->get_option('debug');
			$this->description = $this->get_option('description');
			$this->instructions = $this->get_option('instructions');
			$this->refunds_on = $this->get_option('refunds_on');
			$this->cron_on = $this->get_option('cron_on');
			$this->endstatus = $this->get_option('endstatus');
			$this->cron_seconds = $this->get_option('cron_seconds');
			$this->paymaster_period_cron = $this->get_option('paymaster_period_cron');

			// Логи
			if ( 'yes' == $this->debug ) {
				$this->log = new WC_Logger();
			}

			// Экшены
			add_action('valid-paymaster-standard-ipn-reques', array($this, 'successful_request') );
			add_action('woocommerce_receipt_' . $this->id, array($this, 'receipt_page'));
			add_action('admin_head', array($this, 'notechange'));
		
			// Сохранение опций
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

			// Подключение к API woocommerce
			add_action('woocommerce_api_wc_' . $this->id, array($this, 'check_ipn_response'));
			
			if (!$this->is_valid_for_use()){
				$this->enabled = false;
			}
		}
		
		// Проверка, что пользователю доступны платежи в рублях
		function is_valid_for_use(){
			if (!in_array(get_option('woocommerce_currency'), array('RUB'))){
				return false;
			}
			return true;
		}
		
		// Страница настроек
		public function admin_options() {
		
			echo "
			<br>
			<nav id=\"site-navigation\" class=\"main-navigation\" role=\"navigation\">
				<div id=\"logo\">
					<a href=\"http://info.paymaster.ru\"><img alt=\"paymaster\" src=\"http://info.paymaster.ru/wp-content/themes/paymaster/img/paymaster_logo.png\" target=\"blank\"></a>
					<div id=\"lk-top\"><a href=\"https://paymaster.ru/Partners/authentication/login?ReturnUrl=%2fPartners%2fru\" target=\"blank\"> </a></div>
			<p class=\"tel\">8-495-646-98-32</p>
			</nav>
			<h3>Инструкция по настройке</h3>
			<p>Перейдите в личный кабинет PayMaster</p>
			<p>В разделе “Обратные вызовы” укажите следующие параметры:</p>
				<ul>
				<li>Payment notification: <code>".get_bloginfo("url")."/?wc-api=wc_paymaster&paymaster=notification</code></li>
				<li>Success redirect: <code>".get_bloginfo("url")."/?wc-api=wc_paymaster&paymaster=success</code></li>
				<li>Failure redirect: <code>".get_bloginfo("url")."/?wc-api=wc_paymaster&paymaster=failure</code></li>
				<li>Invoice confirmation: <code>".get_bloginfo("url")."/?wc-api=wc_paymaster&paymaster=invoice</code></li>
				</ul>
			<b>Метод отсылки данных для всех строк: <code>POST запрос</code></b><hr>
			<h1>Настройка приема электронных платежей через Merchant PayMaster.</h1>";
			
			// Генерация HTML формы страницы настроек.
			if ( $this->is_valid_for_use() ) { 	
					printf('<table class="form-table pm">');
					$this->generate_settings_html();
					printf('</table><!--/.form-table-->');
			} else {
					printf('<div class="inline error"><p><strong>'. _e('Шлюз отключен', 'woocommerce').'</strong>: '. _e('paymaster не поддерживает валюты Вашего магазина.', 'woocommerce' ).'</p></div>');
			}

		} // End admin_options()

		// Опции
		function init_form_fields(){
			$this->plugin_dir = plugin_dir_url(__FILE__);
			$last_time = get_option('paymaster_last_cron');
			$cron_seconds = $this->get_option('cron_seconds');
			
			
				$cron['cs'] = (int) $cron_seconds;
				$cron['ms'] = (int) $cron_seconds / 2629743;
				$cron['ws'] = (int) $cron_seconds / 604800;
				$cron['hs'] = (int) $cron_seconds / 86400;
				$cron['mis'] = (int) $cron_seconds / 60;
		
				$cron['ms'] = floor($cron['ms']);
				$cron['ws'] = floor($cron['ws']);
				$cron['hs'] = floor($cron['hs']);
				$cron['mis'] = floor($cron['mis']);
				
			$this->form_fields = array(
					'enabled' => array(
						'title' => __('Включить/Выключить', 'woocommerce'),
						'type' => 'checkbox',
						'label' => __('Активировать платежный модуль</label><br><br><h2>Основные настройки</h2><label>', 'woocommerce'),
						'default' => 'yes'
					),
					 	'title' => array(
						'title' => __( 'Название модуля', 'woocommerce' ),
						'type' => 'textarea',
						'description' => __( 'title', 'woocommerce' ),
						'default' => 'paymaster'
					),
					'description' => array(
						'title' => __( 'Description', 'woocommerce' ),
						'type' => 'textarea',
						'description' => __( 'Это название, которое пользователь видит во время выбора метода оплаты. Рядом с логотипом<br><br><table><tr><td>'.$this->get_option('description').'</td><td><img src="'.$this->plugin_dir.'/img/paymaster.png"></td></tr></table>', 'woocommerce' ),
						'default' => 'Оплата с помощью paymaster.'
					),
					
					'paymaster_robo_login' => array(
						'title' => __('Логин робота', 'woocommerce'),
						'type' => 'text',
						'description' => __('Перейдите в консоль Paymaster в раздел "Пользователи". Добавьте нового пользователя. При создании, выбирайте способ входа "Автоматический". После того, как создадите, нажмите на ссылку "изменить" напротив нового имени. Вы попадете на страницу "Установка прав пользователя". Нажмите кнопку "Добавить". Выберите роль "Бухгалтер", укажите сайт и нажмите сохранить. E-mail адрес, который вы указали для нового пользователя системы введите в поле выше', 'woocommerce'),
						'default' => ''
					),
					'paymaster_robo_pass' => array(
						'title' => __('Пароль робота', 'woocommerce'),
						'type' => 'password',
						'description' => __('Здесь введите пароль пользователя, которого вы создавали в предыдущем пункте.', 'woocommerce'),
						'default' => ''
					),
					
					'paymaster_merchant' => array(
						'title' => __('Идентификатор', 'woocommerce'),
						'type' => 'text',
						'description' => __('Введите идентификатор вашего магазина. Узнать его можно на странице "Учетная запись" в личном кабинете Paymaster.', 'woocommerce'),
						'default' => ''
					),
					'account_id' => array(
						'title' => __('ID аккаунта', 'woocommerce'),
						'type' => 'text',
						'description' => __('Откройте страницу "Учетная запись" в личном кабинете Paymaster. Посмотрите на url: он вида "https://paymaster.ru/partners/ru/accounts/details/XXXX". XXXX - как раз и есть ID аккаунта. Скопируйте его и вставьте в это поле. Это значение требуется для работы функционала, как например: "Возврат платежей".'),
						'default' => ''
					),
					'secret_key' => array(
						'title' => __('Секретный ключ', 'woocommerce'),
						'type' => 'password',
						'description' => __('Секретное слово не передается по сети, и используется для формирования контрольной подписи запроса Payment Notification.', 'woocommerce'),
						'default' => ''
					),
					'hashtype' => array(
						'title' => __( 'Тип подписи', 'woocommerce' ),
						'type'        => 'select',
						'description'	=>  __( 'Способ формирования хеша (контрольной подписи) - MD5 или SHA1', 'woocommerce' ),
						'default'	=> 'MD5',
						'desc_tip'    => true,
						'options'     => array(
							'MD5' => __( 'MD5', 'woocommerce' ),
							'SHA1' => __( 'SHA1', 'woocommerce' ),
							'SHA256' => __( 'SHA256', 'woocommerce' ),
						)
					),
				
					'testmode' => array(
						'title' => __( 'Тест режим', 'woocommerce' ),
						'type'        => 'select',
						'description'	=>  __( 'Отключите, когда Paymaster активирует ваш сайт.', 'woocommerce' ),
						'default'	=> '2',
						'desc_tip'    => true,
						'options'     => array(
							'' => __( 'Откл.', 'woocommerce' ),
							'0' => __( 'Имитировать успешное выполнение', 'woocommerce' ),
							'1' => __( 'Имитировать выполнение с ошибкой', 'woocommerce' ),
							'2' => __( '80% - успех, 20; - ошибка', 'woocommerce' ),
						)
					),
					'debug' => array(
						'title' => __('Debug', 'woocommerce'),
						'type' => 'checkbox',
						'label' => __('Включить логирование', 'woocommerce'),
						'default' => 'no'
					),
					'refunds_on' => array(
						'title' => __('Функционал возвратов', 'woocommerce'),
						'type' => 'checkbox',
						'label' => __('Включите, если планируете контролировать возвраты на страницах заказов. Включайте только на мощных и стабильных серверах, - этот модуль может сильно сказаться на скорости работы вашего сайта на страницах заказов в косоли. Внимание! Это экспериментальная опция.</label><br><br><h2>Автоматизация</h2><label>', 'woocommerce'),
						'default' => 'no'
					),
					
					'afterstatus' => array(
						'title' => __( 'Пост-статус', 'woocommerce' ),
						'type'        => 'select',
						'description'	=>  __( 'Какой статус присваивать заказу после успешного проведения платежа?', 'woocommerce' ),
						'default'	=> 'processing',
						'desc_tip'    => false,
						'options'     => array(
							'wc-processing' => __( 'В обработке (processing)', 'woocommerce' ),
							'wc-completed' => __( 'Завершен (completed)', 'woocommerce' ),
							'wc-on-hold' => __( 'Зарезервирован (on-hold)', 'woocommerce' ),
							'wc-pending' => __( 'Ожидание (pending)', 'woocommerce' ),
						)
					),
					
					'cron_on' => array(
						'title' => __('Включить планировщик', 'woocommerce'),
						'type' => 'checkbox',
						'label' => __('Планировщик позволяет сменить статус заказа с Пост-статуса на другой по расписанию. Например, вы хотите, чтоб заказы автоматически отмечались как "Выполнено" по прошествии трёх дней (такие заказы можно считать устаревшими),- тогда включите эту опцию.', 'woocommerce'),
						'default' => 'no'
					),
					
					'endstatus' => array(
						'title' => __( 'Энд-статус', 'woocommerce' ),
						'type'        => 'select',
						'description'	=>  __( 'Если вы включили планировщик, то выберите, какой статус присваивать устаревшим заказам', 'woocommerce' ),
						'default'	=> 'completed',
						'desc_tip'    => false,
						'options'     => array(
							'wc-processing' => __( 'В обработке (processing)', 'woocommerce' ),
							'wc-completed' => __( 'Завершен (completed)', 'woocommerce' ),
							'wc-on-hold' => __( 'Зарезервирован (on-hold)', 'woocommerce' ),
							'wc-pending' => __( 'Ожидание (pending)', 'woocommerce' ),
						)
					),
				
					'cron_seconds' => array(
						'title' => __('Срок заказов', 'woocommerce'),
						'type' => 'text',
						'description' => __('<code>'.$cron_seconds.' секунд это: '.$cron['ms'].' мес., или '.$cron['ws'].' нед., или '.$cron['hs'].' д., или '.$cron['mis'].' мин. </code><br>Если вы включили планировщик, то введите в секундах "срок жизни" заказов. Например: если вы хотите помечать как "устаревшие" заказы спустя 1 неделю после публикации - напишите 604800.<br><code>1 минута = [60] секунд</code>, <code>1 час = [3600] секунд</code>, <code>1 день = [86400] секунд</code>, <code>1 неделя = [604800] секунд</code>, <code>1 месяц (30.44 дней) = [2629743] секунд</code>, <code> год (365.24 дней) = [31556926] секунд</code>', 'woocommerce'),
						'default' => '259200'
					),
					
					'paymaster_period_cron' => array(
						'title' => __('Таймаут планировщика', 'woocommerce'),
						'type' => 'text',
						'description' => __('<code>Последний запуск планировщика: '.date_i18n("j F Y, H:i:s", $last_time).' ('.$last_time.')</code>Если вы включили планировщик, то укажите в секундах, как часто будет производиться проверка (и замена статусов) на давность заказов. Не указывайте слишком малое количество: для каждого устаревшего заказа производится проверка оплаты в системе Paymaster. Смена статусов заказов выполняется при загрузке wp_header, поэтому, если у вас много заказов, это может сказаться на работоспособности во время работы планировщика.<br><code>1 минута = [60] секунд</code>, <code>1 час = [3600] секунд</code>, <code>1 день = [86400] секунд</code>, <code>1 неделя = [604800] секунд</code>, <code>1 месяц (30.44 дней) = [2629743] секунд</code>, <code>1 год (365.24 дней) = [31556926] секунд</code></label><br><br><h2>Дополнительно</h2><label>', 'woocommerce'),
						'default' => '43200'
					),
					
					'instructions' => array(
						'title' => __( 'Instructions', 'woocommerce' ),
						'type' => 'textarea',
						'description' => __( 'Инструкции, которые будут добавлены на страницу благодарностей.', 'woocommerce' ),
						'default' => 'Оплата с помощью paymaster.'
					)
				);
		}

		// Применение поля "Описание" для кнопки оплаты
		function payment_fields(){
			if ($this->description){
				echo wpautop(wptexturize($this->description));
			}
		}
		
		// Генерация формы "Оплатить"
		public function generate_form($order_id){
			global $woocommerce;
			$order = new WC_Order( $order_id );
						
			$action_adr = 'https://paymaster.ru/Payment/Init';
			
			$out_summ = number_format($order->order_total, 2, '.', '');

			$args = array(
					'LMI_MERCHANT_ID' => $this->paymaster_merchant,
					'LMI_PAYMENT_AMOUNT' => $out_summ,
					'LMI_CURRENCY' => get_option('woocommerce_currency'),
					'LMI_PAYMENT_NO' => $order_id,
					'LMI_PAYER_EMAIL' => $order->billing_email,
					'PAYER_NAME' => $order->billing_last_name.' '.$order->billing_first_name,
					'PAYER_ADRESS' => $order->billing_address_1.' '.$order->billing_address_2,
					'PAYER_UID' => $order->customer_user,
					'PAYER_IP' => $order->customer_ip_address,
				);
				
			if ( $order->billing_phone ) $args['LMI_PAYER_PHONE_NUMBER'] = $order->billing_phone;
			if ($this->testmode!=''){ $args['LMI_SIM_MODE'] = $this->testmode;}
			if ( sizeof( $order->get_items() ) > 0 ) {
				foreach( $order->get_items() as $item ) { 
					$descar[] = $item['name'].' '.sprintf( 'x %s', $item['qty'] );
					} 
				$desc = implode(", ", $descar);
				} else {
				$desc = 'Без описания.';
				}
			
			
			if(count($order->get_used_coupons())>0) $desc = 'Купоны: "'.implode("\", \"", $order->get_used_coupons()).'" (общая сумма скидки: '.$order->get_total_discount().'). '.$desc;
			
			if($order->customer_message!='') $desc .= '. Сообщение: '.$order->customer_message;
			$args['LMI_PAYMENT_DESC'] = $desc;
			
			$paypal_args = apply_filters('woocommerce_paymaster_args', $args);

			$args_array = array();

			foreach ($args as $key => $value){
				if(($this->testmode>0) and (current_user_can('manage_options'))) {
					$addinfo = '<br>'.esc_attr($key).': '.esc_attr($value);} else { $addinfo = '';
				}
			//	$args_array[] = '<p>'.esc_attr($key).': '.esc_attr($value).'</p>'.$addinfo;
				$args_array[] = '<input type="hidden" name="'.esc_attr($key).'" value="'.esc_attr($value).'" />'.$addinfo;
			}
			return
				'<form action="'.esc_url($action_adr).'" method="POST" id="paymaster_payment_form">'."\n".
				implode("\n", $args_array).
				'<span id="ppform">
					<input type="submit" onclick="document.getElementById(\'ppform\').remove();document.getElementById(\'paymaster_payment_form\').submit();" class="button btn btn-default" id="submit_paymaster_payment_form" value="'.__('Оплатить', 'woocommerce').'" style="display:inline-block" /> <a class="button btn alt btn-black" href="'.$order->get_cancel_order_url().'">'.__('Отказаться от оплаты & вернуться в корзину', 'woocommerce').'</a>
					
				</span>'."\n".
				'</form>
				';
		}
		
		//
		function process_payment($order_id){
			$order = new WC_Order($order_id);

			return array(
				'result' => 'success',
				'redirect'	=> add_query_arg('order', $order->id, add_query_arg('key', $order->order_key, get_permalink(woocommerce_get_page_id('pay'))))
			);
		}
		
		// Страница оплаты
		function receipt_page($order){
			echo '<p>'.__('Спасибо за Ваш заказ, пожалуйста, нажмите кнопку ниже, чтобы заплатить.', 'woocommerce').'</p>';
			echo $this->generate_form($order);
		}
		
		// Проверка строки с хешем
		function check_ipn_request_is_valid($posted){
			
			switch($this->hashtype) {
				case'MD5': 
				
				 $str = base64_encode(pack("H*", md5($posted["LMI_MERCHANT_ID"].";".$posted["LMI_PAYMENT_NO"].";".$posted["LMI_SYS_PAYMENT_ID"].";".$posted["LMI_SYS_PAYMENT_DATE"].";".$posted["LMI_PAYMENT_AMOUNT"].";".$posted["LMI_CURRENCY"].";".$posted["LMI_PAID_AMOUNT"].";".$posted["LMI_PAID_CURRENCY"].";".$posted["LMI_PAYMENT_SYSTEM"].";".$posted["LMI_SIM_MODE"].";".$this->secret_key))); break;
				
				case'SHA1': 
				 $str = base64_encode(pack("H*", sha1($posted["LMI_MERCHANT_ID"].";".$posted["LMI_PAYMENT_NO"].";".$posted["LMI_SYS_PAYMENT_ID"].";".$posted["LMI_SYS_PAYMENT_DATE"].";".$posted["LMI_PAYMENT_AMOUNT"].";".$posted["LMI_CURRENCY"].";".$posted["LMI_PAID_AMOUNT"].";".$posted["LMI_PAID_CURRENCY"].";".$posted["LMI_PAYMENT_SYSTEM"].";".$posted["LMI_SIM_MODE"].";".$this->secret_key))); break;
				 
				case'SHA256': 
				 $str = base64_encode(pack("H*", sha256($posted["LMI_MERCHANT_ID"].";".$posted["LMI_PAYMENT_NO"].";".$posted["LMI_SYS_PAYMENT_ID"].";".$posted["LMI_SYS_PAYMENT_DATE"].";".$posted["LMI_PAYMENT_AMOUNT"].";".$posted["LMI_CURRENCY"].";".$posted["LMI_PAID_AMOUNT"].";".$posted["LMI_PAID_CURRENCY"].";".$posted["LMI_PAYMENT_SYSTEM"].";".$posted["LMI_SIM_MODE"].";".$this->secret_key))); break;
			}
			
			if ($posted['LMI_HASH'] == $str)
			{	echo 'OK';	return true;} else { return false;	}
			
		}
		
		// Обработка ответов
		function check_ipn_response(){
			global $woocommerce;
			 
			////////////////////  INVOICE   //////////////		
			
			@ob_clean();
			$_POST = stripslashes_deep($_POST);
			
			if (isset($_GET['paymaster']) AND $_GET['paymaster'] == 'invoice'){
				
				$STATUS['NOTE'] = 'Покупатель выбрал платежную систему, авторизуется, вводит необходимую информацию.';
				$STATUS['PA_WPID'] = $_POST['LMI_PAYMENT_NO']; 
				$STATUS['PA_ID'] = $_POST['LMI_SYS_PAYMENT_ID']; 
				$STATUS['PA_BANK_NAME']	= $_POST['LMI_PAYMENT_SYSTEM'];
				$STATUS['PA_BANK_METH']	= $_POST['LMI_PAYMENT_METHOD'];
				$STATUS['STATUS'] = 'pending';
				
				$inv_id = $_POST['LMI_PAYMENT_NO'];
				$order = new WC_Order( $inv_id );
							
				$order->update_status($STATUS['STATUS']);
				$order->add_order_note('[PAYM]'.serialize($STATUS).'[/PAYM]');
				
				if ($_POST['LMI_PAID_AMOUNT']==$order->order_total) {$answer = 'YES';}
				else if ($_POST['LMI_PAYMENT_AMOUNT']==$order->order_total) {$answer = 'YES';}
				else if ($_POST['LMI_PAID_CURRENCY']==get_option('woocommerce_currency')) {$answer = 'YES';}
				else if ($_POST['LMI_MERCHANT_ID']==$order->paymaster_merchant) {$answe0r = 'YES';}
				else if ($_POST['LMI_CURRENCY']==get_option('woocommerce_currency')) {$answer = 'YES';}
				else if ($_POST['LMI_PAYMENT_NO']==$order->id) {$answer = 'YES';}
				else if ($_POST['PAYER_NAME']==$order->billing_last_name.' '.$order->billing_first_name) {$answer = 'YES';}
				else if ($_POST['PAYER_ADRESS']==$order->billing_address_1.' '.$order->billing_address_2) {$answer = 'YES';}
				else if ($_POST['PAYER_UID']==$order->customer_user) {$answer = 'YES';}
				else if ($_POST['PAYER_IP']==$order->customer_ip_address) {$answer = 'YES';}
				else {$answer = 'NO';}
				
				
				if($answer == 'YES') {
					$STATUS['STATUS'] = 'pending';
					$STATUS['NOTE'] = 'Покупатель указал верные реквизиты. Магазин готов принять платеж.';
					
					$order->update_status('pending');
					$order->add_order_note('[PAYM]'.serialize($STATUS).'[/PAYM]');
				
				} else {
					
					$STATUS['STATUS'] = 'canceled';
					$STATUS['NOTE'] = 'Возвращены неверные данные';
					$order->update_status('canceled');
					$order->add_order_note('[PAYM]'.serialize($STATUS).'[/PAYM]');
				
				}
				
					
				echo $answer;
				exit;
			} 
			
			
			////////////////////  NOTIFICATION   //////////////		
			
			else if (isset($_GET['paymaster']) AND $_GET['paymaster'] == 'notification'){
				@ob_clean();
							
				$STATUS['PA_ID']		= $_POST['LMI_SYS_PAYMENT_ID'];
				$STATUS['PA_DATE']		= $_POST['LMI_SYS_PAYMENT_DATE'];
				$STATUS['PA_BANK_NAME']	= $_POST['LMI_PAYMENT_SYSTEM'];
				$STATUS['PA_BANK_METH']	= $_POST['LMI_PAYMENT_METHOD'];
				$STATUS['PA_BANK_NUM']	= $_POST['LMI_PAYER_IDENTIFIER'];
				$STATUS['PA_SHOP_ID']	= $_POST['LMI_SHOP_ID'];
				$STATUS['PA_WPID'] 		= $_POST['LMI_PAYMENT_NO'];  
				
				$inv_id = $_POST['LMI_PAYMENT_NO'];
				$order = new WC_Order($inv_id);
				//			if ( !update_post_meta(...) ) add_post_meta(...) )
				add_post_meta( $STATUS['PA_WPID'], '_order_id_in_paymaster', $STATUS['PA_ID'], true );
				
				if($this->check_ipn_request_is_valid($_POST)){
					
					$STATUS['STATUS'] = $this->afterstatus;
					$STATUS['NOTE'] = 'Платеж успешно оплачен. Ожидание выполнения работ';
					$order->update_status($STATUS['STATUS']);
					$order->add_order_note('[PAYM]'.serialize($STATUS).'[/PAYM]');
					WC()->cart->empty_cart();
					
					} 
					
				else {
					$rt = '';
					foreach($hasharray as $key => $val) {
						
					$rt .= $key.'='.$val.';';
					}
					$STATUS['STATUS'] = 'failed';
					$STATUS['NOTE'] = 'Ошибка в подписи';
					$order->update_status('failed');
					$order->add_order_note('[PAYM]'.serialize($STATUS).'[/PAYM]');
					
					}
				
				wp_redirect( $this->get_return_url( $order ) );
			}
			
			
			////////////////////  SUCCESS   //////////////
			
			else if (isset($_GET['paymaster']) AND $_GET['paymaster'] == 'success'){
				
				$inv_id = $_POST['LMI_PAYMENT_NO'];
				$order = new WC_Order($inv_id);
				
				$STATUS['PA_ID']		= $_POST['LMI_SYS_PAYMENT_ID'];
				$STATUS['PA_DATE']		= $_POST['LMI_SYS_PAYMENT_DATE'];
				$STATUS['PA_BANK_NAME']	= $_POST['LMI_PAYMENT_SYSTEM'];
				$STATUS['PA_BANK_NUM']	= $_POST['LMI_PAYER_IDENTIFIER'];
				$STATUS['PA_WPID'] 		= $_POST['LMI_PAYMENT_NO'];
				$STATUS['PA_ID']		= $_POST['LMI_SYS_PAYMENT_ID']; 
				$STATUS['NOTE'] 		= 'Пользователь перенаправлен на страницу SUCCESS';
				$order->add_order_note('[PAYM]'.serialize($STATUS).'[/PAYM]');
				
				
				WC()->cart->empty_cart();
				wp_redirect( $this->get_return_url( $order ) );
			}
			 
			////////////////////  FAIL   //////////////
			else if (isset($_GET['paymaster']) AND $_GET['paymaster'] == 'failure'){
				
				
				$inv_id = $_POST['LMI_PAYMENT_NO'];
				$order = new WC_Order($inv_id);
			
				$STATUS['PA_ID']		= $_POST['LMI_SYS_PAYMENT_ID'];
				$STATUS['PA_DATE']		= $_POST['LMI_SYS_PAYMENT_DATE'];
				$STATUS['PA_BANK_NAME']	= $_POST['LMI_PAYMENT_SYSTEM'];
				$STATUS['PA_BANK_NUM']	= $_POST['LMI_PAYER_IDENTIFIER'];
				$STATUS['PA_WPID'] 		= $_POST['LMI_PAYMENT_NO'];
				$STATUS['PA_ID']		= $_POST['LMI_SYS_PAYMENT_ID']; 
				$STATUS['NOTE'] 		= 'Пользователь отказался от оплаты или при проведении платежа произошла ошибка. Перенаправлен на страницу FAILURE';
				$STATUS['STATUS'] = 'canceled';
				$order->add_order_note('[PAYM]'.serialize($STATUS).'[/PAYM]'); 
				$order->update_status('canceled');
				
				wp_redirect($order->get_cancel_order_url());
				exit;
			}

		}

		//
		function successful_request($posted){
			global $woocommerce;

			$out_summ = $posted['OutSum'];
			$inv_id = $posted['InvId'];

			$order = new WC_Order($inv_id);

			// Check order not already completed
			if ($order->status == 'completed'){
				exit;
			}

			// Payment completed
			$order->add_order_note(__('Платеж успешно завершен.', 'woocommerce'));
			$order->payment_complete();
			exit;
		}
		
		//
		function notechange() {
			global $woocommerce, $post;
			remove_filter( 'comments_clauses', array( 'WC_Comments', 'exclude_order_comments' ), 10, 1 );

			$args = array(
				'post_id' 	=> $_GET['post'],
				'approve' 	=> 'approve',
				'type' 		=> 'order_note'
			);		
							
			$notes = get_comments($args);

			add_filter('comments_clauses', 'note_scrip', 10, 1);
	}

		//
		function note_scrip($notes){
			echo '<ul class="order_notes">';
			if ( $notes ) {
				foreach( $notes as $note ) {
					$note_classes = get_comment_meta( $note->comment_ID, 'is_customer_note', true ) ? array( 'customer-note', 'note' ) : array( 'note' );

					?>
					<li rel="<?php echo absint( $note->comment_ID ) ; ?>" class="<?php echo implode( ' ', $note_classes ); ?>">
						<div class="note_content">
							<?php 
							preg_match_all("@\[PAYM\](.+?)\[\/PAYM\]@i", $note, $PAYNOTE );
							$PAYNOTE = unserialize($PAYNOTE[1][0]);
							echo $PAYNOTE['NOTE']
							
							?>
						</div>
						<p class="meta">
							<abbr class="exact-date" title="<?php echo $note->comment_date_gmt; ?> GMT"><?php printf( __( 'added %s ago', 'woocommerce' ), human_time_diff( strtotime( $note->comment_date_gmt ), current_time( 'timestamp', 1 ) ) ); ?></abbr>
							<?php if ( $note->comment_author !== __( 'WooCommerce', 'woocommerce' ) ) printf( ' ' . __( 'by %s', 'woocommerce' ), $note->comment_author ); ?>
							<a href="#" class="delete_note"><?php _e( 'Delete note', 'woocommerce' ); ?></a>
						</p>
					</li>
					<?php
				}
			} else {
				echo '<li>' . __( 'There are no notes for this order yet.', 'woocommerce' ) . '</li>';
			}

			echo '</ul>';	

		}	

	}
}
 
/************************************************************
 *  
 *  
 * 		Планировщик автоматической смены статуса заказа	
 *		pm_cron()
 *  
 *************************************************************/
add_action('get_header', 'pm_cron' );
function pm_cron() {
		
	$thiser = get_option('woocommerce_paymaster_settings');
	
	if($thiser['cron_on']!="yes") return;
	
	$last_time = get_option('paymaster_last_cron');
	$now = current_time('timestamp');
		
	if(empty($last_time)) {add_option('paymaster_last_cron', $now); $last_time = $now;}
	$period = $thiser['paymaster_period_cron'];
	if(empty($period) or ($period<600)) {$period = 600;} 
			
	//if($now-$period>0) {
	if($now-$period>=$last_time) {

		global $woocommerce;
		
		$cron_seconds = $thiser['cron_seconds']+2;
		$low= $now - (int)$thiser['cron_seconds'];
		$need_time = date("Y-m-d H:i:s", $low);

		$pmargs = array(
			
			'post_type' => 'shop_order',
			'post_status' => $thiser['afterstatus'],
			'posts_per_page' => -1,
			'date_query' => array(
				array(
					'after'     => '2000-01-01 00:00:00',
					'before'     => $need_time,
				),
			),	
		);
		
		
		$querys = new WP_Query( $pmargs );
	/*	var_dump($pmargs);
		var_dump($querys);
		var_dump($pmargs);*/
		
		while ( $querys->have_posts() ) {
			$querys->the_post();
			$order_id_in_pm = get_post_meta(get_the_ID(), '_order_id_in_paymaster', true);
	//	var_dump($order_id_in_pm);
			
			if($order_id_in_pm) {
			
				$arg['login'] =  $thiser['paymaster_robo_login'];
				$arg['password'] =  $thiser['paymaster_robo_pass'];
				$arg['invoiceID'] = get_the_ID();
				$arg['siteAlias'] =  $thiser['paymaster_merchant'];
				$arg['nonce'] = wp_create_nonce( get_the_ID().current_time('timestamp'));
				$str = $arg['login'].";".$arg['password'].";".$arg['nonce'].";".$arg['invoiceID'].";".$arg['siteAlias'];
				$ref = '';
				$hash = base64_encode(sha1($str, true));
				$url = 'https://paymaster.ru/partners/rest/getpaymentbyinvoiceid?'.http_build_query($arg).'&hash='.$hash;
				$answ = json_decode(file_get_contents($url));
				$ref = '';
				
				if($answ->ErrorCode == 0) {
					switch($answ->Payment->State) {
					default: $new_status = 'failed'; break;
					case'INITIATED': continue; break;
					case'PROCESSING': continue; break;
					case'COMPLETE': $new_status = $thiser['endstatus']; break;
					case'CANCELLED': $new_status = 'canceled'; break;
				}
				} else {
					$new_status = 'failed';
				}
				if($new_status=='') $new_status = 'failed';	
			}
			
			$wr = new WC_Order(get_the_ID());
			$wr->update_status( $new_status, 'Автостатус на /'.$answ->Payment->State.'/'); 
			update_option('paymaster_last_cront', get_the_ID());
			unset($new_status,$url,$order_id_in_pm); 
		}
		
		update_option('paymaster_last_cron', current_time('timestamp'));
	}
}

?>