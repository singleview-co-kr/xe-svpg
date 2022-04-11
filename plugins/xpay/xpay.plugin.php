<?php
class xpay extends SvpgPlugin 
{
	var $plugin_info;
	const _g_sPluginLogHome = 'files/svpg/xpay';
/**
 * @brief
 */
	function pluginInstall($args) 
	{
		// mkdir
		//FileHandler::makeDir(_XE_PATH_.self::_g_sPluginLogHome.'/'.$args->plugin_srl.'/key');
		FileHandler::makeDir(_XE_PATH_.self::_g_sPluginLogHome.'/'.$args->plugin_srl.'/log');
		// copy files
		FileHandler::copyFile(_XE_PATH_.'modules/svpg/plugins/xpay/.htaccess', _XE_PATH_.self::_g_sPluginLogHome.'/'.$args->plugin_srl.'/.htaccess');
		FileHandler::copyFile(_XE_PATH_.'modules/svpg/plugins/xpay/readme.txt', _XE_PATH_.self::_g_sPluginLogHome.'/'.$args->plugin_srl.'/readme.txt');
	}
/**
 * @brief
 */
	function xpay() 
	{
		parent::SvpgPlugin();
	}
/**
 * @brief
 */
	function init(&$args) 
	{
		$this->plugin_info = new StdClass();
		foreach( $args as $key=>$val )
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
//var_dump( $this->_g_aPaymentMethod );
		if (!$args->price) return new BaseObject(0,'No input of price');
		if (!$args->svpg_module_srl) return new BaseObject(-1,'No input of svpg_module_srl');
		if (!$args->module_srl) return new BaseObject(-1,'No input of module_srl');

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
		if ($this->plugin_info->paymethod_card=='Y') $usablepay[] = 'SC0010';
		if ($this->plugin_info->paymethod_directbank=='Y') $usablepay[] = 'SC0030';
		if ($this->plugin_info->paymethod_virtualbank=='Y') $usablepay[] = 'SC0040';
		if ($this->plugin_info->paymethod_phone=='Y') $usablepay[] = 'SC0060';
		// LGUPLUS XPAY는 에스크로를 위한 통신 장치가 없고, 해당 결제가 에스크로인지 알려주기만 함, XPAY 관리자화면에서 수기로 에스크로를 통제해야 함
		if( is_null( $this->plugin_info->use_escrow ) )	$this->plugin_info->use_escrow = 'N';
		//LGD_ESCROW_USEYN
		Context::set('LGD_CUSTOM_USABLEPAY', implode('-',$usablepay));
		$oTemplate = &TemplateHandler::getInstance();
		$tpl_path = _XE_PATH_."modules/svpg/plugins/xpay/tpl";
		$tpl_file = 'formdata.html';
		$form_data = $oTemplate->compile($tpl_path, $tpl_file);
		$output = new BaseObject();
		$output->data = $form_data;
		return $output;
	}
/**
 * @brief
 */
	function processReview($args) 
	{
		$xpay_home = _XE_PATH_.self::_g_sPluginLogHome.'/'.$args->plugin_srl; //sprintf(_XE_PATH_."files/svpg/xpay/%s", $args->plugin_srl);
		$configPath = _XE_PATH_."modules/svpg/plugins/xpay/libs";
		$CST_PLATFORM = $this->plugin_info->cst_platform; //LG유플러스 결제 서비스 선택(test:테스트, service:서비스)
		$CST_MID = $this->plugin_info->mert_id; //상점아이디(LG유플러스로 부터 발급받으신 상점아이디를 입력하세요)
										//테스트 아이디는 't'를 반드시 제외하고 입력하세요.
		$LGD_MID = (('test' == $CST_PLATFORM) ? 't' : '' ).$CST_MID;  //상점아이디(자동생성)
		$LGD_PAYKEY = $this->plugin_info->mert_key;
		$LGD_OID = $args->order_srl;
		$LGD_AMOUNT= $args->price;
		$LGD_TIMESTAMP=date('YmdHiS');
		$config = $this->getConfig();
		require_once(_XE_PATH_."modules/svpg/plugins/xpay/libs/XPayClient.php");
		$xpay = &new XPayClient($configPath, $config, $this->plugin_info->cst_platform);
		$xpay->config['log_dir'] = $xpay_home . '/log';
		$xpay->Init_TX($LGD_MID);
		$LGD_HASHDATA = md5($LGD_MID.$LGD_OID.$LGD_AMOUNT.$LGD_TIMESTAMP.$LGD_PAYKEY);
		$LGD_CUSTOM_PROCESSTYPE = "TWOTR";

		// 싱글뷰가 전송하는 LGU+ 테스트 계정의 OID가 중복될 수 있음. 이런 경우 아래와 같은 에러코드 발생함
		// [result_code] => A016 [result_message] => 중복된 주문번호입니다.#
		// [result_code] => P015 [result_message] => 요청정보가 이전 거래정보(은행,금액,예금주명,주민등록번호)와 상이하여 처리결과를 확인할수 없습니다.<BR>이전 거래정보를 확인하시고 재확인하십시오#

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
		if( $this->plugin_info->paymethod_card=='Y' && $args->payment_method==$this->_g_aPaymentMethod['credit_card'] )
			$usablepay = 'SC00101';
		if( $this->plugin_info->paymethod_directbank=='Y' && $args->payment_method==$this->_g_aPaymentMethod['internet_banking'] )
			$usablepay = 'SC0030';
		if( $this->plugin_info->paymethod_virtualbank=='Y' && $args->payment_method==$this->_g_aPaymentMethod['virtual_account'] )
			$usablepay = 'SC0040';
		if( $this->plugin_info->paymethod_phone=='Y' && $args->payment_method==$this->_g_aPaymentMethod['mobile_phone'] )
			$usablepay = 'SC0060';
		Context::set('lgd_usablepay', $usablepay );

		$oTemplate = &TemplateHandler::getInstance();
		$tpl_path = _XE_PATH_."modules/svpg/plugins/xpay/tpl";
		$tpl_file = 'review.html';
		$tpl_data = $oTemplate->compile($tpl_path, $tpl_file);
		$output = new BaseObject();
		$output->add('tpl_data', $tpl_data);
		return $output;
	}
/**
 * @brief
 */
	function getConfig()
	{
		$xpay_home = _XE_PATH_.self::_g_sPluginLogHome.'/'.$this->plugin_info->plugin_srl;//sprintf(_XE_PATH_."files/svpg/xpay/%s", $this->plugin_info->plugin_srl);
		$config = array();
		$config['server_id'] = '01';
		$config['timeout'] = '60';
		$config['log_level'] = '4';
		$config['verify_cert'] = '1';
		$config['verify_host'] = '1';
		$config['report_error'] = '1';
		$config['output_UTF8'] = '1';
		$config['auto_rollback'] = '1';
		$config['log_dir'] = $xpay_home.'/log';
		$config[(('test' == $this->plugin_info->cst_platform) ? 't' : '' ).$this->plugin_info->mert_id] = $this->plugin_info->mert_key;		
		$config['url'] = 'https://xpayclient.lgdacom.net/xpay/Gateway.do';
		$config['test_url'] = 'https://xpayclient.lgdacom.net:7443/xpay/Gateway.do';
		$config['aux_url'] = 'http://xpayclient.lgdacom.net:7080/xpay/Gateway.do';
		return $config;
	}
/**
 * @brief
 */
	function processPayment($args) 
	{
		$configPath = _XE_PATH_."modules/svpg/plugins/xpay/libs";
		$xpay_home = _XE_PATH_.self::_g_sPluginLogHome.'/'.$args->plugin_srl;//sprintf(_XE_PATH_."files/svpg/xpay/%s", $args->plugin_srl);
		$LGD_PAYKEY = Context::get('lgd_paykey');
		$CST_PLATFORM = Context::get('cst_platform');
		$CST_MID = Context::get('cst_mid');
		$LGD_MID = (("test" == $CST_PLATFORM)?"t":"").$CST_MID;
		$config = $this->getConfig();
		require_once(_XE_PATH_."modules/svpg/plugins/xpay/libs/XPayClient.php");
		$xpay = &new XPayClient($configPath, $config, $CST_PLATFORM);
		$xpay->config[$LGD_MID] = $this->plugin_info->mert_key;
		$xpay->Init_TX($LGD_MID);
		$xpay->Set("LGD_TXNAME", "PaymentByKey");
		$xpay->Set("LGD_PAYKEY", $LGD_PAYKEY);
		
		// 2. 최종결제 요청 결과처리
		// 최종 결제요청 결과 리턴 파라미터는 연동메뉴얼을 참고하시기 바랍니다.
		// 싱글뷰가 전송하는 LGU+ 테스트 계정의 OID가 중복될 수 있음. 이런 경우 아래와 같은 에러코드 발생함
		// [result_code] => A016 [result_message] => 중복된 주문번호입니다.#
		// [result_code] => P015 [result_message] => 요청정보가 이전 거래정보(은행,금액,예금주명,주민등록번호)와 상이하여 처리결과를 확인할수 없습니다.<BR>이전 거래정보를 확인하시고 재확인하십시오#
		$utf8VACTName = '';
		$utf8VACTInputName = '';
		if ($xpay->TX()) 
		{
			$utf8ResultMsg = $xpay->Response_Msg();
			$utf8VACTName = $xpay->Response('LGD_SAOWNER');
			$utf8VACTInputName = $xpay->Response('LGD_PAYER');

			// error check
			if ($xpay->Response_Code() != '0000') 
			{
				$output = new BaseObject(-1, $utf8ResultMsg);
				$output->add('state', '3'); // failure
			}
			else
			{
				$output = new BaseObject(0, $utf8ResultMsg);
				if ($this->getPaymethod($xpay->Response('LGD_PAYTYPE',0))=='VA')
					$output->add('state', '1'); // not completed
				else 
					$output->add('state', '2'); // completed (success)
			}
		}
		else 
		{
			$utf8ResultMsg = "결제요청이 실패하였습니다.";
			$output = new BaseObject(-1, $utf8ResultMsg);
			$output->add('state', '3'); // failure
		}
		// 무통장입금 시 계좌 만료일 $vars->lgd_closedate 기록하기 위함
		$vars = Context::getRequestVars();
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
		$output->add('vact_date', $vars->lgd_closedate); // 송금일자
		$output->add('vact_time', ''); // 송금시간
		$output->add('pg_tid', $xpay->Response('LGD_TID',0));
		$original = array();
		$keys = $xpay->Response_Names();
		foreach($keys as $name) 
			$original[] = $name." = ".$xpay->Response($name, 0)."\n";
		$output->add('ORIGINAL', $original);
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
		$CST_PLATFORM = $this->plugin_info->cst_platform;//$HTTP_POST_VARS["CST_PLATFORM"];  //LG유플러스 결제 서비스 선택(test:테스트, service:서비스)
		$CST_MID =  $this->plugin_info->mert_id;//$HTTP_POST_VARS["CST_MID"]; //상점아이디(LG유플러스으로 부터 발급받으신 상점아이디를 입력하세요)
		$LGD_MID = (("test" == $CST_PLATFORM)?"t":"").$CST_MID;  //상점아이디(자동생성)    
		$LGD_TID = $oXpayTrId;//$HTTP_POST_VARS["LGD_TID"];			 //LG유플러스으로 부터 내려받은 거래번호(LGD_TID)
		//LG유플러스에서 제공한 환경파일("/conf/lgdacom.conf") 위치 지정.   
		$config = $this->getConfig();
		require_once(_XE_PATH_."modules/svpg/plugins/xpay/libs/XPayClient.php");
		$configPath = _XE_PATH_."modules/svpg/plugins/xpay/libs";
		$xpay = &new XPayClient($configPath, $config, $CST_PLATFORM);
		$xpay->Init_TX($LGD_MID);
		$xpay->Set("LGD_TXNAME", "Cancel");
		$xpay->Set("LGD_TID", $LGD_TID);

		// 프로토콜 취소 불가능한 결제 방식 판단
		if( $xpay->TX() )
		{
			//$msg = '결제 취소요청이 완료되었습니다. TX Response_code = '.$xpay->Response_Code().' TX Response_msg = '.$xpay->Response_Msg();
			$output = new BaseObject();
			//$output->add('payment_method','결제 취소요청이 완료되었습니다.');
			$sResponseCode = $xpay->Response_Code();
			if( $sResponseCode == '0000' )
				$sRstCode = 'completed(success)';
			else
				$sRstCode = 'completed(failure)';

			$output->add('result_code',$sResponseCode);
			$output->add('result_message',$xpay->Response_Msg());
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
			$msg = '결제 취소요청이 실패하였습니다. TX Response_code = '.$xpay->Response_Code().' TX Response_msg = '.$xpay->Response_Msg();
			$output = new BaseObject(-1, $msg);
			//$output->add('payment_method','결제 취소요청이 실패하였습니다.');
			$output->add('result_code',$xpay->Response_Code());
			$output->add('result_message',$xpay->Response_Msg());
			$output->add('status_msg','not_completed');
			//2)API 요청 실패 화면처리
			//echo "결제 취소요청이 실패하였습니다.  <br>";
			//echo "TX Response_code = " . $xpay->Response_Code() . "<br>";
			//echo "TX Response_msg = " . $xpay->Response_Msg() . "<p>";
		}
		return $output;
	}
/**
 * @brief
 */
	function processReport(&$transaction)
	{
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
	function getPaymethod($paymethod)
	{
		switch($paymethod)
		{
			case 'SC0010':
				return $this->_g_aPaymentMethod['credit_card'];
			case 'SC0030':
				return $this->_g_aPaymentMethod['internet_banking'];
			case 'SC0040':
				return $this->_g_aPaymentMethod['virtual_account'];
			case 'SC0060':
				return $this->_g_aPaymentMethod['mobile_phone'];
			default:
				return '  ';
		}
	}
/**
 * @brief svpg.controller.php::procSvpgReport()에서 호출
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
/* End of file xpay.plugin.php */
/* Location: ./modules/svpg/plugins/xpay/xpay.plugin.php */