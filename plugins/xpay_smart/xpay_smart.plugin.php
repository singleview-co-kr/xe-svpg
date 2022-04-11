<?php
class xpay_smart extends SvpgPlugin 
{
	var $plugin_info;
	const _g_sPluginLogHome = 'files/svpg/xpay_smart';
/**
 * @brief
 */
	function pluginInstall($args) 
	{
		// mkdir
		//FileHandler::makeDir(_XE_PATH_.self::_g_sPluginLogHome.'/'.$args->plugin_srl.'/key');
		FileHandler::makeDir(_XE_PATH_.self::_g_sPluginLogHome.'/'.$args->plugin_srl.'/log');
		// copy files
		FileHandler::copyFile(_XE_PATH_.'modules/svpg/plugins/xpay_smart/.htaccess', _XE_PATH_.self::_g_sPluginLogHome.'/'.$args->plugin_srl.'/.htaccess');
		FileHandler::copyFile(_XE_PATH_.'modules/svpg/plugins/xpay_smart/readme.txt', _XE_PATH_.self::_g_sPluginLogHome.'/'.$args->plugin_srl.'/readme.txt');
	}
/**
 * @brief
 */
	function xpay_smart() 
	{
		parent::SvpgPlugin();
	}
/**
 * @brief
 */
	function init(&$args) 
	{
		$this->plugin_info = new StdClass();
		foreach ($args as $key=>$val)
		{
			$this->plugin_info->{$key} = $val;
		}
		foreach ($args->extra_var as $key=>$val)
		{
			$this->plugin_info->{$key} = $val->value;
		}
		Context::set('plugin_info', $this->plugin_info);
	}
/**
 * @brief
 */
	function getFormData($args) 
	{
		if( !$args->price ) 
			return new BaseObject(0,'No input of price');
		if( !$args->svpg_module_srl ) 
			return new BaseObject(-1,'No input of svpg_module_srl');
		if( !$args->module_srl ) 
			return new BaseObject(-1,'No input of module_srl');

		Context::set('module_srl', $args->module_srl);
		Context::set('svpg_module_srl', $args->svpg_module_srl);
		Context::set('plugin_srl', $this->plugin_info->plugin_srl);
		Context::set('item_name', $args->item_name);
		Context::set('purchaser_name', $args->purchaser_name);
		Context::set('purchaser_email', $args->purchaser_email);
		Context::set('purchaser_telnum', $args->purchaser_telnum);
		Context::set('script_call_before_submit', $args->script_call_before_submit);
		Context::set('join_form', $args->join_form);
		$CST_PLATFORM = $this->plugin_info->cst_platform; //LG유플러스 결제 서비스 선택(test:테스트, service:서비스)
		$CST_MID = $this->plugin_info->mert_id; //상점아이디(LG유플러스로 부터 발급받으신 상점아이디를 입력하세요)
		//테스트 아이디는 't'를 반드시 제외하고 입력하세요.
		$LGD_MID = (('test' == $CST_PLATFORM) ? 't' : '' ).$CST_MID;  //상점아이디(자동생성)
		Context::set('CST_MID', $CST_MID);
		Context::set('LGD_MID', $LGD_MID);
		$usablepay = array();
		if( $this->plugin_info->paymethod_card=='Y' )
			$usablepay[] = 'SC0010';
		if( $this->plugin_info->paymethod_directbank=='Y' )
			$usablepay[] = 'SC0030';
		if( $this->plugin_info->paymethod_virtualbank=='Y' )
			$usablepay[] = 'SC0040';
		if( $this->plugin_info->paymethod_phone=='Y' )
			$usablepay[] = 'SC0060';
		Context::set('LGD_CUSTOM_USABLEPAY', implode('-',$usablepay));

		$oTemplate = &TemplateHandler::getInstance();
		$tpl_path = _XE_PATH_."modules/svpg/plugins/xpay_smart/tpl";
		$tpl_file = 'formdata.html';
		$form_data = $oTemplate->compile($tpl_path, $tpl_file);
		$output = new BaseObject();
		$output->data = $form_data;
		return $output;
	}
/**
 * @brief
 */
	function getConfig( $LGD_MID )
	{
		// 아래의 두가지 설정 파일을 대신 처리함
		//./lib/conf/mall.conf
		//./lib/conf/lgdacom.conf
		$xpay_smart_home = _XE_PATH_.self::_g_sPluginLogHome.'/'.$this->plugin_info->plugin_srl;//sprintf(_XE_PATH_."files/svpg/xpay_smart/%s", $this->plugin_info->plugin_srl);
		$config = array();
		$config['server_id'] = '01';
		$config['timeout'] = '60';
		$config['log_level'] = '4';
		$config['verify_cert'] = '1';
		$config['verify_host'] = '1';
		$config['report_error'] = '1';
		$config['output_UTF8'] = '1';
		$config['auto_rollback'] = '1';
		$config['log_dir'] = $xpay_smart_home.'/log';
		$config[$LGD_MID] = $this->plugin_info->mert_key;
		$config['url'] = 'https://xpayclient.lgdacom.net/xpay/Gateway.do';
		$config['test_url'] = 'https://xpayclient.lgdacom.net:7443/xpay/Gateway.do';
		$config['aux_url'] = 'http://xpayclient.lgdacom.net:7080/xpay/Gateway.do';
		return $config;
	}
/**
 * @brief
 */
	function processReview($args) 
	{
		$oModuleModel = &getModel('module');
		$svpg_module_srl = Context::get('svpg_module_srl');
		$svpg_module_info = $oModuleModel->getModuleInfoByModuleSrl($svpg_module_srl);
		$xpay_smart_home = _XE_PATH_.self::_g_sPluginLogHome.'/'.$args->plugin_srl; //sprintf(_XE_PATH_."files/svpg/xpay_smart/%s", $args->plugin_srl);
		$configPath = _XE_PATH_."modules/svpg/plugins/xpay_smart/libs";
		$CST_PLATFORM = $this->plugin_info->cst_platform; //LG유플러스 결제 서비스 선택(test:테스트, service:서비스)
		$CST_MID = $this->plugin_info->mert_id; //상점아이디(LG유플러스로 부터 발급받으신 상점아이디를 입력하세요)
		//테스트 아이디는 't'를 반드시 제외하고 입력하세요.
		$LGD_MID = (('test' == $CST_PLATFORM) ? 't' : '' ).$CST_MID;  //상점아이디(자동생성)
		$LGD_PAYKEY = $this->plugin_info->mert_key;
		$LGD_OID = $args->order_srl;
		$LGD_AMOUNT= $args->price;
		$LGD_TIMESTAMP = date('YmdHiS');
		$config = $this->getConfig( $LGD_MID );
		require_once(_XE_PATH_."modules/svpg/plugins/xpay_smart/libs/XPayClient.php");
		$xpay_smart = &new XPayClient($configPath, $this->plugin_info->cst_platform, $config );
		$xpay_smart->config[$LGD_MID] = $LGD_PAYKEY;
		$xpay_smart->config['log_dir'] = $xpay_smart_home.'/log';
		$xpay_smart->Init_TX($LGD_MID);
		$LGD_HASHDATA = md5($LGD_MID.$LGD_OID.$LGD_AMOUNT.$LGD_TIMESTAMP.$xpay_smart->config[$LGD_MID]);
		$LGD_CUSTOM_PROCESSTYPE = "TWOTR";
		
		//if( $args->delivfee_inadvance == 'N' )
		//	$args->price -= $args->delivery_fee;

		$date = new DateTime('NOW');
		$date->add(new DateInterval('P3D'));
		Context::set('price', $args->price);
		Context::set('order_srl', $args->order_srl);
		Context::set('timestamp', $LGD_TIMESTAMP);
		Context::set('hashdata', $LGD_HASHDATA);
		Context::set('processtype', $LGD_CUSTOM_PROCESSTYPE);
		Context::set('purchaser_name', $args->purchaser_name);
		Context::set('purchaser_cellphone', $args->purchaser_cellphone);
		Context::set('bank_close_date', $date->format('YmdHis') );
		$sReturnUrl = getNotEncodedFullUrl('','module','svpg','act','procSvpgDoPayment','module_srl',$args->module_srl, 'svpg_module_srl',$args->svpg_module_srl, 'plugin_srl',$this->plugin_info->plugin_srl, 'order_srl', $args->order_srl, 'svpg_target_module', $args->target_module);
		Context::set('return_url', $sReturnUrl );
		$oTemplate = &TemplateHandler::getInstance();
		$tpl_path = _XE_PATH_.'modules/svpg/plugins/xpay_smart/tpl';
		$tpl_file = 'review.html';
		$tpl_data = $oTemplate->compile($tpl_path, $tpl_file);

// accept_result.html 에서 처리할 세션 설정 시작
		$payReqMap['CST_PLATFORM'] = $CST_PLATFORM;              // 테스트, 서비스 구분
		$payReqMap['CST_WINDOW_TYPE'] = 'submit';           // 수정불가
		$payReqMap['CST_MID'] = $CST_MID;                   // 상점아이디
		$payReqMap['LGD_MID'] = $LGD_MID;                   // 상점아이디
		$payReqMap['LGD_OID'] = $args->order_srl;                   // 주문번호
		$payReqMap['LGD_BUYER'] = $args->purchaser_name;            	   // 구매자
		$payReqMap['LGD_BUYERPHONE'] = $args->purchaser_cellphone;            	   // 구매자전화번호
		$payReqMap['LGD_BUYEREMAIL'] = $args->purchaser_email;            // 구매자 이메일
		$payReqMap['LGD_PRODUCTINFO'] = $args->item_name;     	   // 상품정보
		$payReqMap['LGD_AMOUNT'] = $args->price;                // 결제금액
		$payReqMap['LGD_CUSTOM_SKIN'] = 'SMART_XPAY2';           // 결제창 SKIN
		$payReqMap['LGD_CUSTOM_PROCESSTYPE'] = $LGD_CUSTOM_PROCESSTYPE;    // 트랜잭션 처리방식
		$payReqMap['LGD_TIMESTAMP'] = $LGD_TIMESTAMP;             // 타임스탬프
		$payReqMap['LGD_HASHDATA'] = $LGD_HASHDATA;              // MD5 해쉬암호값
		$payReqMap['LGD_RETURNURL'] = $sReturnUrl;      	   // 응답수신페이지
		$payReqMap['LGD_VERSION'] = "PHP_SmartXPay_1.0";		   // 버전정보 (삭제하지 마세요)
		$payReqMap['LGD_CUSTOM_FIRSTPAY'] = 'SC0010';	   // 디폴트 결제수단
		$payReqMap['LGD_CUSTOM_SWITCHINGTYPE'] = "SUBMIT";	       // 신용카드 카드사 인증 페이지 연동 방식
// 안드로이드폰 신용카드 ISP(국민/BC)결제에만 적용 (시작)*
//(주의)LGD_CUSTOM_ROLLBACK 의 값을  "Y"로 넘길 경우, LG U+ 전자결제에서 보낸 ISP(국민/비씨) 승인정보를 고객서버의 note_url에서 수신시  "OK" 리턴이 안되면  해당 트랜잭션은  무조건 롤백(자동취소)처리되고,
//LGD_CUSTOM_ROLLBACK 의 값 을 "C"로 넘길 경우, 고객서버의 note_url에서 "ROLLBACK" 리턴이 될 때만 해당 트랜잭션은  롤백처리되며  그외의 값이 리턴되면 정상 승인완료 처리됩니다.
//만일, LGD_CUSTOM_ROLLBACK 의 값이 "N" 이거나 null 인 경우, 고객서버의 note_url에서  "OK" 리턴이  안될시, "OK" 리턴이 될 때까지 3분간격으로 2시간동안  승인결과를 재전송합니다.
		$payReqMap['LGD_CUSTOM_ROLLBACK'] = "";			   	   				     // 비동기 ISP에서 트랜잭션 처리여부
		$payReqMap['LGD_KVPMISPNOTEURL'] = $LGD_KVPMISPNOTEURL;			   // 비동기 ISP(ex. 안드로이드) 승인결과를 받는 URL
		$payReqMap['LGD_KVPMISPWAPURL'] = $LGD_KVPMISPWAPURL;			   // 비동기 ISP(ex. 안드로이드) 승인완료후 사용자에게 보여지는 승인완료 URL
		$payReqMap['LGD_KVPMISPCANCELURL'] = $LGD_KVPMISPCANCELURL;		   // ISP 앱에서 취소시 사용자에게 보여지는 취소 URL

// 안드로이드폰 신용카드 ISP(국민/BC)결제에만 적용    (끝) *
// 안드로이드 에서 신용카드 적용  ISP(국민/BC)결제에만 적용 (선택)
// $payReqMap['LGD_KVPMISPAUTOAPPYN'] = "Y";
// Y: 안드로이드에서 ISP신용카드 결제시, 고객사에서 'App To App' 방식으로 국민, BC카드사에서 받은 결제 승인을 받고 고객사의 앱을 실행하고자 할때 사용
// 가상계좌(무통장) 결제연동을 하시는 경우  할당/입금 결과를 통보받기 위해 반드시 LGD_CASNOTEURL 정보를 LG 유플러스에 전송해야 합니다 .
		$payReqMap['LGD_CASNOTEURL'] = $LGD_CASNOTEURL;               // 가상계좌 NOTEURL
		//Return URL에서 인증 결과 수신 시 셋팅될 파라미터 입니다.*/
		$payReqMap['LGD_RESPCODE'] = '';
		$payReqMap['LGD_RESPMSG'] = '';
		$payReqMap['LGD_PAYKEY'] = '';
		$_SESSION['PAYREQ_MAP'] = $payReqMap;
		$_SESSION['svpg_http_vars'] = Context::getRequestVars();
		$_SESSION['bank_close_date'] = $date->format('YmdHis');
// accept_result.html 에서 처리할 세션 설정 끝
		$output = new BaseObject();
		$output->add('tpl_data', $tpl_data);
		return $output;
	}
/*
 * [결제취소 요청 페이지]
 * LG유플러스으로 부터 내려받은 거래번호(LGD_TID)를 가지고 취소 요청을 합니다.(파라미터 전달시 POST를 사용하세요)
 * (승인시 LG유플러스으로 부터 내려받은 PAYKEY와 혼동하지 마세요.)
 * 가상계좌의 경우 환불대상 계좌정보가 있어야 함
 */
	function processCancel($oXpayTrId)
	{
		$CST_PLATFORM = $this->plugin_info->cst_platform;//$HTTP_POST_VARS["CST_PLATFORM"];       //LG유플러스 결제 서비스 선택(test:테스트, service:서비스)
		$CST_MID =  $this->plugin_info->mert_id;//$HTTP_POST_VARS["CST_MID"];            //상점아이디(LG유플러스으로 부터 발급받으신 상점아이디를 입력하세요)
		//테스트 아이디는 't'를 반드시 제외하고 입력하세요.
		$LGD_MID = (("test" == $CST_PLATFORM)?"t":"").$CST_MID;  //상점아이디(자동생성)    
		$LGD_TID = $oXpayTrId;//$HTTP_POST_VARS["LGD_TID"];			 //LG유플러스으로 부터 내려받은 거래번호(LGD_TID)
		//$configPath = "/home/singleview125/www/xpay/lgdacom"; 						 //LG유플러스에서 제공한 환경파일("/conf/lgdacom.conf") 위치 지정.   
		//$config = $this->getConfig();
		$config = $this->getConfig( $LGD_MID );
		require_once(_XE_PATH_."modules/svpg/plugins/xpay_smart/libs/XPayClient.php");
		$configPath = _XE_PATH_."modules/svpg/plugins/xpay_smart/libs";
		$xpay_smart = &new XPayClient($configPath, $this->plugin_info->cst_platform, $config);
		$xpay_smart->Init_TX($LGD_MID);
		$xpay_smart->Set("LGD_TXNAME", "Cancel");
		$xpay_smart->Set("LGD_TID", $LGD_TID);
// 프로토콜 취소 불가능한 결제 방식 판단
		if( $xpay_smart->TX() )
		{
			//$msg = '결제 취소요청이 완료되었습니다. TX Response_code = '.$xpay->Response_Code().' TX Response_msg = '.$xpay->Response_Msg();
			$output = new BaseObject();
			//$output->add('payment_method','결제 취소요청이 완료되었습니다.');
			$sResponseCode = $xpay_smart->Response_Code();
			if( $sResponseCode == '0000' )
				$sRstCode = 'completed(success)';
			else
				$sRstCode = 'completed(failure)';

			$output->add('result_code',$sResponseCode);
			$output->add('result_message',$xpay_smart->Response_Msg());
			$output->add('status_msg',$sRstCode);
			//1)결제취소결과 화면처리(성공,실패 결과 처리를 하시기 바랍니다.)
			//echo "결제 취소요청이 완료되었습니다.<br>";
			//echo "TX Response_code = " . $xpay->Response_Code() . "<br>";
			//echo "TX Response_msg = " . $xpay->Response_Msg() . "<p>";
		}
		else
		{
			//$msg = '결제 취소요청이 실패하였습니다. TX Response_code = '.$xpay->Response_Code().' TX Response_msg = '.$xpay->Response_Msg();
			//return new BaseObject($msg);
			//$msg = '결제 취소요청이 완료되었습니다. TX Response_code = '.$xpay->Response_Code().' TX Response_msg = '.$xpay->Response_Msg();
			$msg = '결제 취소요청이 실패하였습니다. TX Response_code = '.$xpay_smart->Response_Code().' TX Response_msg = '.$xpay_smart->Response_Msg();
			$output = new BaseObject(-1, $msg);
			//$output->add('payment_method','결제 취소요청이 실패하였습니다.');
			$output->add('result_code',$xpay_smart->Response_Code());
			$output->add('result_message',$xpay_smart->Response_Msg());
			$output->add('status_msg','not_completed');
			//2)API 요청 실패 화면처리
			//echo "결제 취소요청이 실패하였습니다.  <br>";
			//echo "TX Response_code = " . $xpay->Response_Code() . "<br>";
			//echo "TX Response_msg = " . $xpay->Response_Msg() . "<p>";
		}
		return $output;
	}
/*
* [최종결제요청 페이지(STEP2-2)]
* LG유플러스으로 부터 내려받은 LGD_PAYKEY(인증Key)를 가지고 최종 결제요청.(파라미터 전달시 POST를 사용하세요)
*/
	function processPayment( $args ) 
	{
		// ※ 중요
		// 환경설정 파일의 경우 반드시 외부에서 접근이 가능한 경로에 두시면 안됩니다.
		// 해당 환경파일이 외부에 노출이 되는 경우 해킹의 위험이 존재하므로 반드시 외부에서 접근이 불가능한 경로에 두시기 바랍니다. 
		// 예) [Window 계열] C:\inetpub\wwwroot\lgdacom ==> 절대불가(웹 디렉토리)
		//
		//$configPath = "/home/singleview125/www/smart/lgdacom"; //LG유플러스에서 제공한 환경파일("/conf/lgdacom.conf,/conf/mall.conf") 위치 지정. 
		$configPath = _XE_PATH_."modules/svpg/plugins/xpay_smart/libs"; //LG유플러스에서 제공한 환경파일("/conf/lgdacom.conf,/conf/mall.conf") 위치 지정. 
// 1.최종결제 요청 - BEGIN
//  (단, 최종 금액체크를 원하시는 경우 금액체크 부분 주석을 제거 하시면 됩니다.)
		//$CST_PLATFORM               = $HTTP_POST_VARS["CST_PLATFORM"];
		//$CST_MID                    = $HTTP_POST_VARS["CST_MID"];
		//$LGD_MID                    = (("test" == $CST_PLATFORM)?"t":"").$CST_MID;
		//$LGD_PAYKEY                 = $HTTP_POST_VARS["LGD_PAYKEY"];
		//require_once("./lgdacom/XPayClient.php");

		$CST_PLATFORM = $this->plugin_info->cst_platform; //LG유플러스 결제 서비스 선택(test:테스트, service:서비스)
		$CST_MID = $this->plugin_info->mert_id; //상점아이디(LG유플러스로 부터 발급받으신 상점아이디를 입력하세요)
		//$CST_PLATFORM               = Context::get('cst_platform');
		//$CST_MID                    = Context::get('cst_mid');
		$LGD_MID                    = (("test" == $CST_PLATFORM)?"t":"").$CST_MID;
		$LGD_PAYKEY                 = Context::get('LGD_PAYKEY');
		require_once(_XE_PATH_."modules/svpg/plugins/xpay_smart/libs/XPayClient.php");
		$config = $this->getConfig( $LGD_MID );
		$xpay = &new XPayClient($configPath, $CST_PLATFORM, $config );
		$xpay->Init_TX($LGD_MID);
		$xpay->Set("LGD_TXNAME", "PaymentByKey");
		$xpay->Set("LGD_PAYKEY", $LGD_PAYKEY);

		//금액을 체크하시기 원하는 경우 아래 주석을 풀어서 이용하십시요.
		//$DB_AMOUNT = "DB나 세션에서 가져온 금액"; //반드시 위변조가 불가능한 곳(DB나 세션)에서 금액을 가져오십시요.
		//$xpay->Set("LGD_AMOUNTCHECKYN", "Y");
		//$xpay->Set("LGD_AMOUNT", $DB_AMOUNT);

// 1.최종결제 요청(수정하지 마세요) - END
// 2. 최종결제 요청 결과처리
// 최종 결제요청 결과 리턴 파라미터는 연동메뉴얼을 참고하시기 바랍니다.
		if ($xpay->TX()) 
		{
			if( "0000" == $xpay->Response_Code() ) 
			{
				//최종결제요청 결과 성공 DB처리
				$utf8ResultMsg = "최종결제요청 결과 성공 DB처리하시기 바랍니다.";
				$output = new BaseObject(0, $utf8ResultMsg);
				if ($this->getPaymethod($xpay->Response('LGD_PAYTYPE',0))=='VA')
					$output->add('state', '1'); // not completed
				else 
					$output->add('state', '2'); // completed (success)

				//최종결제요청 결과 성공 DB처리 실패시 Rollback 처리
				$isDBOK = true; //DB처리 실패시 false로 변경해 주세요.
				if( !$isDBOK ) 
				{
					$xpay->Rollback("상점 DB처리 실패로 인하여 Rollback 처리 [TID:" . $xpay->Response("LGD_TID",0) . ",MID:" . $xpay->Response("LGD_MID",0) . ",OID:" . $xpay->Response("LGD_OID",0) . "]");            		            		
					//echo "TX Rollback Response_code = " . $xpay->Response_Code() . "<br>";
					//echo "TX Rollback Response_msg = " . $xpay->Response_Msg() . "<p>";
					if( "0000" == $xpay->Response_Code() ) 
					{
						$utf8ResultMsg = "자동취소가 정상적으로 완료 되었습니다.";
						$output = new BaseObject(0, $utf8ResultMsg);
						$output->add('state', 'A'); // cancelled (abnormal)
					}
					else
					{
						//echo "자동취소가 정상적으로 처리되지 않았습니다.<br>";
						$utf8ResultMsg = "자동취소가 정상적으로 처리되지 않았습니다.";
						$output = new BaseObject(0, $utf8ResultMsg);
						$output->add('state', '0'); // cancelled (abnormal)
					}
				}            	
			}
			else
			{
				//최종결제요청 결과 실패 DB처리
				$utf8ResultMsg = "최종결제요청 결과 실패 DB처리하시기 1바랍니다.";
				$output = new BaseObject(-1, $utf8ResultMsg);
				$output->add('state', '0'); // failure
			}
		}
		else 
		{
			//2)API 요청실패 화면처리
			//echo "결제요청이 실패하였습니다.  <br>";
			//echo "TX Response_code = " . $xpay->Response_Code() . "<br>";
			//echo "TX Response_msg = " . $xpay->Response_Msg() . "<p>";
			//최종결제요청 결과 실패 DB처리
			//echo "최종결제요청 결과 실패 DB처리하시기 바랍니다1.<br>";
			$utf8ResultMsg = "결제요청이 실패하였습니다.";
			$output = new BaseObject(-1, $utf8ResultMsg);
			$output->add('state', '0'); // failure
		}
///////////////////////////////////////////////////////////////////////////////////////////////////
		$output->add('payment_method', $this->getPaymethod($xpay->Response('LGD_PAYTYPE',0)));
		$output->add('payment_amount', $xpay->Response('LGD_AMOUNT',0));
		$output->add('result_code', $xpay->Response_Code());
		$output->add('result_message', $utf8ResultMsg);
		$output->add('vact_num', $xpay->Response('LGD_ACCOUNTNUM',0)); // 계좌번호
		$output->add('vact_bankname', $xpay->Response('LGD_FINANCENAME',0)); //은행코드
		$output->add('vact_bankcode', $xpay->Response('LGD_FINANCECODE',0)); //은행코드
		$output->add('vact_use_escrow', $xpay->Response('LGD_ESCROWYN',0)); //에스크로 사용여부
		$output->add('vact_name', $utf8VACTName); // 예금주
		$output->add('vact_inputname', $utf8VACTInputName); // 송금자
		$output->add('vact_date', $_SESSION['bank_close_date']); // 송금일자
		$output->add('vact_time', ''); // 송금시간
		$output->add('pg_tid', $xpay->Response('LGD_TID',0));
		$original = array();
		$keys = $xpay->Response_Names();
		foreach($keys as $name) 
			$original[] = $name." = ".$xpay->Response($name, 0)."\n";

		$output->add('ORIGINAL', $original);
		return $output;
	}
/**
 * @brief
 */
	function processReport(&$transaction) 
	{
		//$vars = Context::getRequestVars();
		$tid = Context::get('LGD_TID');
		$casflag = Context::get('LGD_CASFLAG');
		$output = new BaseObject();

		// check for TID
		if ($transaction->pg_tid != $tid)
		{
debugPrint( 'xpay_TID mismatch_sent' );
			$output->setError(-1); // not completed
			$output->setMessage('LGD_TID mismatch');
			return $output;
		}
		if( $casflag == 'R' ) // LGD_CASFLAG R:가상계좌 할당
		{
			$output->setError(0); // successfully completed
			$output->setMessage('SVPG_VAC'); // virutal_account_confirmed
		}
		else if( $casflag == 'I' ) // LGD_CASFLAG I: 입금
		{
			$output->order_srl = Context::get('LGD_OID');
			$output->amount = Context::get('LGD_CASTAMOUNT');
			if ($output->amount == $transaction->payment_amount)
			{
debugPrint( 'xpay_ok_sent' );
				$output->setError(0); // successfully completed
				$output->setMessage('SVPG_RC'); // receive_confirmed
			}
			else
			{
debugPrint( 'xpay_amount_mismatch_sent' );
				$output->setError(-1); // not completed
				$output->setMessage('SVPG_AME'); // amount_mismatch_error
			}
		}
		else if( $casflag == 'C' ) // LGD_CASFLAG C:은행에서 입금취소
		{
debugPrint( 'virtual account receivement cancelled' );
$vars = Context::getRequestVars();
debugPrint( $vars );
			$output->setError(0); // successfully completed
			$output->setMessage('SVPG_CC'); // cancel_confirmed
		}
		else // LGD_CASFLAG 오류
		{
debugPrint( 'xpay_CASFLAG mismatch_sent' );
			$output->setError(-1); // not completed
			$output->setMessage('SVPG_UE'); // unknown error
		}
		return $output;
	}
/**
 * @brief
 */
	function getReport() 
	{
		$vars = Context::getRequestVars();
		debugPrint($vars);

		$output = new BaseObject();
		$output->order_srl = Context::get('LGD_OID');
		$output->amount = Context::get('LGD_CASTAMOUNT');
		return $output;
	}
/**
 * @brief
 */
	function getPaymethod($paymethod) {
		switch ($paymethod) {
			case 'SC0010':
				return 'CC';
			case 'SC0030':
				return 'IB';
			case 'SC0040':
				return 'VA';
			case 'SC0060':
				return 'MP';
			default:
				return '  ';
		}
	}
/**
 * @brief svpg.model.php::getSvpgReceipt()에서 호출
 */
	function getReceipt($pg_tid)
	{
		$authdata = md5($this->plugin_info->mert_id.$pg_tid.$this->plugin_info->mert_key);
		Context::set('tid', $pg_tid);
		Context::set('authdata', $authdata);
		$oTemplate = &TemplateHandler::getInstance();
		$tpl_path = _XE_PATH_."modules/svpg/plugins/xpay_smart/tpl";
		$tpl_file = 'receipt.html';
		$tpl = $oTemplate->compile($tpl_path, $tpl_file);
		return $tpl;
	}
}
/* End of file xpay_smart.plugin.php */
/* Location: ./modules/svpg/plugins/xpay_smart/xpay_smart.plugin.php */