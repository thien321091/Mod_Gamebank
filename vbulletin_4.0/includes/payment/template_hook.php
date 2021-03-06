<?php
//######################## REQUIRE BACK-END  ################# Delete when completed
//require_once('./global.php');
//require_once(DIR . '/includes/adminfunctions.php');
//require_once(DIR . '/includes/class_bbcode.php');
//#############################################################

include_once ('lib/class.payment_history.php');
global $forumid, $vb;

if($vbulletin->options['payment_enable'] == 1 && $vbulletin -> userinfo['userid'])//Check if mod is enable and user is login ....................
{
	//################   Start Get setting  #################
	
	/**
	 * Get gamebank account from vbulletin option Your Payment (string)
	 */
	$gamebank_account = $vbulletin -> options['payment_id'] != "0" ? $vbulletin -> options['payment_id'] : "thien321091";
	
	/**
	 * Get column name from vbulletin option Your Payment (string)
	 */
	global $gamebank_column;
	$gamebank_column = $vbulletin -> options['payment_column'];
	
	/**
	 * Get nav name from vbulletin option Your Payment (string)
	 */
	 $name_nav = $vbulletin -> options['payment_namenav'];
	/**
	 * Getting setting enable widget (boolean)
	 */
	$payment_enable_widget = $vbulletin -> options['payment_enable_widget'];
	/**
	 * Get top link option 
	 */	
	$payment_top_link = $vbulletin->options['payment_toplink'];
	/**
	 * Get currency option 
	 */
	include_once ('plugin/config_currency.php');
	
	
	/**
	 * Get user detail from $vbulletin (userid, username, coins);
	 */
	global $user_detail;
	
	$user_detail = array(
		'userid' => $vbulletin -> userinfo['userid'],
		'username' => $vbulletin -> userinfo['username'],
		'coins' => $vbulletin ->userinfo[$gamebank_column]		
	);
	//#######################################################
	
	
	if ($gamebank_column != "") //Check if option is setting completed .................... 
	{		
		if (isset($_POST['payment']) ) //Checked if request from form...  
		{
			include_once ('lib/nusoap.php');
			$telco = $_POST['lstTelco'];
			$code = $_POST['txtCode'];
			$seri = $_POST['txtSeri'];
			
			//Create client request
			$client = new nusoap_client("http://pay.gamebank.vn/service/csv6.php/?wsdl", true);

			//Get client request 
			$result = $client -> call("creditCard", array("seri" => $seri, "code" => $code, "cardtype" => $telco, "gamebank_account" => $gamebank_account, "option" => "nap bang vbulletin","website"=> $_SERVER['SERVER_NAME']));

			//print_r($result);
			/**
			 * create script to alert result 
			 **/
			$str_result = "<script> alert('";
			$status = 10000;
			if ($result[0] >= 10000) {
				//echo "Nap thanh cong ".$result[0];
				//Nap tien thanh cong, $result['resultCode'] là mệnh giá thẻ khách nạp
				/**
				 * include class game bank 
				 */
				include("/lib/class.gamebank.php");
				
				/**
				 * Calulate user coins 
				 */
				if($currencys[$result[0]]){
					$currencys[$result[0]] = $result[0];
				}
				
				$user_payment =  $currencys[$result[0]] += $user_detail['coins'];
				$user_detail['coins'] = $user_payment;
								
				$UserPay = new GameBank($user_detail['userid'], $user_payment);
				if ($UserPay -> UpdatePayment()) //Update user coins to database 
				{
					$str_result .="Nạp tiền thành công!!!. bạn đã nạp :" . $result[0] . " vào tài khoản";
				}
				
			} else {
				//Lỗi nạp tiền, dựa vào bảng mã lỗi để show thông tin khách hàng lên
				$status = $result[0];
				switch($result[0]) {
					case -3 :
						$str_result .= "The khong su dung duoc";
						break;
					case -10 :
						$str_result .= "Nhap sai dinh dang the";
						break;
					case -1001 :
						$str_result .= "Nhap sai qua 3 lan ";
						break;
					case -1002 :
						$str_result .= "Loi he thong ";
						break;
					case -1003 :
						$str_result .= "IP khong duoc phep truy cap vui long quay lai sau 5 phut";
						break;
					case -1004 :
						$str_result .= "Ten dang nhap gamebank khong dung";
						break;
					case -1005 :
						$str_result .= "Loai the khong dung";
						break;
					case -1006 :
						$str_result .= "He thong dang bao tri";
						break;
					default :
						$str_result .= "Ket noi voi Gamebank that bai";
				}					
			}		
			$str_result .= "');</script>";
			//Alert script to show result
			$payment_new  = new payment($seri,$code,$status,$result[0]);
			$payment_new->insertItemp($user_detail['username']);	
			echo $str_result;
		}
	}
	
	
   	global $template_hook;
	
	/**
	 * Get form content 
	 */
	ob_start();
		/**
		 * Get from act content;
		 */
		require_once('payment/form.php');		
		$php_include = ob_get_contents();
		
		/**
		 * Get content template
		 */
		$php_frmmain_include = "";
		require_once('payment/content/frmmain.txt');	
		$php_frmmain_include = ob_get_contents();	
		
		/**
		 * Get content widget template
		 */
		require_once('payment/content/content_widget.txt');		
		$php_widget_include = ob_get_contents();
	ob_end_clean();
	/**
	 * Payment detail User detail, User coins (Username : coins)
	 */
	$payment_detail = $user_detail['username']." : ".$user_detail['coins'];
	$header_cont = "<a class='navtab' href='/payment/' href='#top' onclick='document.location.hash='form-gamebank'; return false;'>Nạp card</a>";
	if($name_nav != "") //Checking changed value name_menu
	{
		/**
		 * Payment content link in header and navbar ... 
		 */	
		$header_cont = "<a class='navtab' href='/payment/' href='#top' onclick='document.location.hash='form-gamebank'; return false;'>".$name_nav."</a>";
		//add to menu
		$template_hook['navtab_middle'] .= "<li>$header_cont</li>";
	}
	
	
	/**
	 * 
	 * Edit header content
	 * 
	 */	
	$vbulletin -> userinfo['username'] .= ":".$user_detail['coins'] ." coins";
	
	if($payment_top_link){
		$ad_location['payment_url'] = $header_cont;
	}
	/**
	 * Import value to your_payment template page  
	 */
	$templater = vB_Template::create('your_payment');
		$templater->register('payment_content', $php_include);
		$templater->register('payment_title','Payment Online');
		$templater->register('payment_detail', $payment_detail);
	$ad_location['your_payment'] .= $templater->render();

	/**
	 * Insert content to widget if enable widget
	 */
	if($payment_enable_widget){
		$ad_location['payment_content'] = $php_include;     
		$ad_location['payment_title'] ='Payment Online';
		$ad_location['payment_detail'] = $payment_detail;
	} 
	
	//$vbulletin -> userinfo['username']  = $user_detail['username'];
   	/**
	 * Import value to FORUMHOME template  
	 */
	//vB_Template::preRegister('FORUMHOME', array('payment_content'=> $php_include,
	//											'payment_title'=>'Payment Online',
	//											'payment_detail'=> $payment_detail));
	
	
	
	
}
?>