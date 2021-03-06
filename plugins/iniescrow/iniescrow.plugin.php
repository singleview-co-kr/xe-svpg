<?php
define('INIPAY_HOME', _XE_PATH_.'files/svpg/iniescrow');
define('INIPAY_LOGDIR', _XE_PATH_.'files/svpg/iniescrow/log');
define('INIPAY_KEYDIR', _XE_PATH_.'files/svpg/iniescrow/key');

class iniescrow extends SvpgPlugin 
{
	var $plugin_info;

	function pluginInstall($args) 
	{
		// mkdir
		FileHandler::makeDir(sprintf(_XE_PATH_."files/svpg/iniescrow/%s/key",$args->plugin_srl));
		FileHandler::makeDir(sprintf(_XE_PATH_."files/svpg/iniescrow/%s/log",$args->plugin_srl));
		// copy files
		FileHandler::copyFile(_XE_PATH_.'modules/svpg/plugins/iniescrow/.htaccess',sprintf(_XE_PATH_."files/svpg/%s/.htaccess",$args->plugin_srl));
		FileHandler::copyFile(_XE_PATH_.'modules/svpg/plugins/iniescrow/readme.txt',sprintf(_XE_PATH_."files/svpg/%s/readme.txt",$args->plugin_srl));
		FileHandler::copyFile(_XE_PATH_.'modules/svpg/plugins/iniescrow/key/pgcert.pem',sprintf(_XE_PATH_."files/svpg/%s/key/pgcert.pem",$args->plugin_srl));
	}

	function iniescrow() 
	{
		parent::SvpgPlugin();
	}

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
	 * item_name
	 * price
	 * purchaser_name
	 * purchaser_email
	 * purchaser_telnum
	 */
	function getFormData($args)
	{
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

		$oTemplate = &TemplateHandler::getInstance();
		$tpl_path = _XE_PATH_."modules/svpg/plugins/iniescrow/tpl";
		$tpl_file = 'formdata.html';
		$form_data = $oTemplate->compile($tpl_path, $tpl_file);

		$output = new BaseObject();
		$output->data = $form_data;
		return $output;
	}

	function processReview($args)
	{
		$inipayhome = sprintf(_XE_PATH_."files/svpg/%s", $args->plugin_srl);

		require("libs/INILib.php");
		$inipay = new INIpay50;
		$inipay->SetField("inipayhome", $inipayhome);
		$inipay->SetField("type", "chkfake");
		$inipay->SetField("debug", "true");
		$inipay->SetField("enctype","asym");
		$inipay->SetField("admin", $this->plugin_info->inicis_pass);
		$inipay->SetField("checkopt", "false");
		$inipay->SetField("mid", $this->plugin_info->inicis_id);
		$inipay->SetField("price", $args->price);
		$inipay->SetField("nointerest", "no");
		$inipay->SetField("quotabase", iconv('UTF-8', 'EUC-KR', '??????:?????????:2??????:3??????:6??????'));

		/* ????????? ??????/?????? ????????? */
		$inipay->startAction();

		/* ????????? ?????? */
		if( $inipay->GetResult("ResultCode") != "00" ) {
			$resultMsg = iconv("EUC-KR", "UTF-8", $inipay->GetResult("ResultMsg"));
			return new BaseObject(-1, $resultMsg);
		}

		/* ???????????? ?????? */
		$_SESSION['INI_MID'] = $this->plugin_info->inicis_id;	//??????ID
		$_SESSION['INI_ADMIN'] = $this->plugin_info->inicis_pass;	// ???????????????(???????????? ??????, ??????????????? ??????????????? ????????????)
		$_SESSION['INI_PRICE'] = $args->price;   //?????? 
		$_SESSION['INI_RN'] = $inipay->GetResult("rn"); //?????? (?????? ?????? ??????)
		$_SESSION['INI_ENCTYPE'] = $inipay->GetResult("enctype"); //?????? (?????? ?????? ??????)

		Context::set('encfield', $inipay->GetResult('encfield'));
		Context::set('certid', $inipay->GetResult('certid'));
		Context::set('inicis_id', $this->plugin_info->inicis_id);
		
		//if( $args->delivfee_inadvance == 'N' )
		//	$args->price -= $args->delivery_fee;

		Context::set('price', $args->price);
		Context::set('order_srl', $args->order_srl);
		$oTemplate = &TemplateHandler::getInstance();
		$tpl_path = _XE_PATH_."modules/svpg/plugins/iniescrow/tpl";
		$tpl_file = 'review.html';
		$tpl_data = $oTemplate->compile($tpl_path, $tpl_file);

		$output = new BaseObject();
		$output->add('tpl_data', $tpl_data);
		return $output;
	}

	function processPayment($args)
	{
		$inipayhome = sprintf(_XE_PATH_."files/svpg/%s", $args->plugin_srl);

		$vars = Context::getRequestVars();
		extract(get_object_vars($vars));

		$goodname = Context::get('goodname');
		$currency = Context::get('currency');

		require("libs/INILib.php");
		$inipay = new INIpay50;
		$inipay->SetField("inipayhome", $inipayhome);
		$inipay->SetField("type", "securepay");
		$inipay->SetField("pgid", "INIphp".$pgid); // $pgid is global var which defined in INICls.php
		$inipay->SetField("subpgip","203.238.3.10");
		$inipay->SetField("admin", $_SESSION['INI_ADMIN']);
		$inipay->SetField("debug", "true");
		$inipay->SetField("uid", $uid);
		$inipay->SetField("goodname", iconv("UTF-8", "EUC-KR", $goodname));
		$inipay->SetField("currency", $currency);

		$inipay->SetField("mid", $_SESSION['INI_MID']);
		$inipay->SetField("rn", $_SESSION['INI_RN']);
		$inipay->SetField("price", $_SESSION['INI_PRICE']);
		$inipay->SetField("enctype", $_SESSION['INI_ENCTYPE']);

		$inipay->SetField("buyername", iconv("UTF-8", "EUC-KR", Context::get('buyername')));
		$inipay->SetField("buyertel", Context::get('buyertel'));
		$inipay->SetField("buyeremail", Context::get('buyeremail'));
		$inipay->SetField("paymethod", Context::get('paymethod'));
		$inipay->SetField("encrypted", Context::get('encrypted'));
		$inipay->SetField("sessionkey", Context::get('sessionkey'));
		$inipay->SetField("url", 'singleview.co.kr');
		$inipay->SetField("cardcode", Context::get('cardcode'));

		$inipay->SetField("parentemail", Context::get('parentemail'));
		$inipay->SetField("recvname", Context::get('recvname'));
		$inipay->SetField("recvtel", Context::get('recvtel'));
		$inipay->SetField("recvaddr", Context::get('recvaddr'));
		$inipay->SetField("recvpostnum", Context::get('recvpostnum'));
		$inipay->SetField("recvmsg", Context::get('recvmsg'));
		$inipay->SetField("joincard", Context::get('joincard'));
		$inipay->SetField("joinexpire", Context::get('joinexpire'));
		$inipay->SetField("id_customer", Context::get('id_customer'));

		/* ???????????? */
		$inipay->startAction();

		$utf8ResultMsg = iconv('EUC-KR', 'UTF-8', $inipay->GetResult('ResultMsg'));
		$utf8VACTName = iconv('EUC-KR', 'UTF-8', $inipay->GetResult('VACT_Name'));
		$utf8VACTInputName = iconv('EUC-KR', 'UTF-8', $inipay->GetResult('VACT_InputName'));

		// error check
		if ($inipay->GetResult('ResultCode') != '00') 
		{
			$output = new BaseObject(-1, $utf8ResultMsg);
			$output->add('state', '3'); // failure
		}
		else
		{
			$output = new BaseObject(0, $utf8ResultMsg);
			if ($this->getPaymethod(Context::get('paymethod'))=='VA')
			{
				$output->add('state', '1'); // not completed
			} else {
				$output->add('state', '2'); // completed (success)
			}
		}

		$output->add('payment_method', $this->getPaymethod(Context::get('paymethod')));
		$output->add('payment_amount', $_SESSION['INI_PRICE']);
		$output->add('result_code', $inipay->GetResult('ResultCode'));
		$output->add('result_message', $utf8ResultMsg);
		$output->add('vact_num', $inipay->GetResult('VACT_Num')); // ????????????
		$output->add('vact_bankname', $this->getBankName($inipay->GetResult('VACT_BankCode'))); //????????????
		$output->add('vact_bankcode', $inipay->GetResult('VACT_BankCode')); //????????????
		$output->add('vact_name', $utf8VACTName); // ?????????
		$output->add('vact_inputname', $utf8VACTInputName); // ?????????
		$output->add('vact_regnum', $inipay->GetResult('VACT_RegNum')); //????????? ??????
		$output->add('vact_date', $inipay->GetResult('VACT_Date')); // ????????????
		$output->add('vact_time', $inipay->GetResult('VACT_Time')); // ????????????
		$output->add('pg_tid', $inipay->GetResult('TID'));
		return $output;
	}

	function processReport(&$transaction)
	{
		$inipayhome = sprintf(_XE_PATH_."files/svpg/%s", $transaction->plugin_srl);
		$TEMP_IP = $_SERVER["REMOTE_ADDR"];
		$PG_IP  = substr($TEMP_IP,0, 10);

		//PG?????? ???????????? IP??? ??????
		if( $PG_IP != "203.238.37" && $PG_IP != "210.98.138" )  {
			return new BaseObject(-1, 'msg_invalid_request');
		}
		/*
		$msg_id = $msg_id;             //????????? ??????
		$no_tid = $no_tid;             //????????????
		$no_oid = $no_oid;             //?????? ????????????
		$id_merchant = $id_merchant;   //?????? ?????????
		$cd_bank = $cd_bank;           //?????? ?????? ?????? ??????
		$cd_deal = $cd_deal;           //?????? ?????? ??????
		$dt_trans = $dt_trans;         //?????? ??????
		$tm_trans = $tm_trans;         //?????? ??????
		$no_msgseq = $no_msgseq;       //?????? ?????? ??????
		$cd_joinorg = $cd_joinorg;     //?????? ?????? ??????

		$dt_transbase = $dt_transbase; //?????? ?????? ??????
		$no_transeq = $no_transeq;     //?????? ?????? ??????
		$type_msg = $type_msg;         //?????? ?????? ??????
		$cl_close = $cl_close;         //?????? ????????????
		$cl_kor = $cl_kor;             //?????? ?????? ??????
		$no_msgmanage = $no_msgmanage; //?????? ?????? ??????
		$no_vacct = $no_vacct;         //??????????????????
		$amt_input = $amt_input;       //????????????
		$amt_check = $amt_check;       //????????? ????????? ??????
		$nm_inputbank = $nm_inputbank; //?????? ???????????????
		$nm_input = $nm_input;         //?????? ?????????
		$dt_inputstd = $dt_inputstd;   //?????? ?????? ??????
		$dt_calculstd = $dt_calculstd; //?????? ?????? ??????
		$flg_close = $flg_close;       //?????? ??????
		*/
		/*
		//????????????????????? ??????????????? ??????????????????????????? ??????
		$dt_cshr      = $dt_cshr;       //??????????????? ????????????
		$tm_cshr      = $tm_cshr;       //??????????????? ????????????
		$no_cshr_appl = $no_cshr_appl;  //??????????????? ????????????
		$no_cshr_tid  = $no_cshr_tid;   //??????????????? ??????TID
		*/
		$logfile = fopen($inipayhome."/log/vbank_" . date("Ymd") . ".log", "a+");
		$vars = Context::getRequestVars();
		foreach ($vars as $key=>$val) {
			fwrite( $logfile,$key." : ".$val."\n");
		}
		/*
		$output = $this->processPayment(Context::get('no_oid'), Context::get('amt_input'));
		if (!$output->toBool()) return $output;
		*/
		fwrite( $logfile,"************************************************\n\n");
		fclose( $logfile );

		//????????? ?????? ????????????????????? ?????? ??????????????? ????????? ??????????????? "OK"??? ???????????????
		//????????????????????????. ?????? ????????? ?????????????????? ????????? ?????? FLAG ????????? ????????????
		//(??????) OK??? ???????????? ???????????? ???????????? ?????? ????????? "OK"??? ?????????????????? ?????? ???????????? ???????????????
		//?????? ?????? ????????? PRINT( echo )??? ?????? ???????????? ????????????
		$output = new BaseObject();
		$output->order_srl = Context::get('no_oid');
		$output->amount = Context::get('amt_input');
		if ($output->amount == $transaction->payment_amount)
		{
			//echo "OK";
			$output->setError(0); // successfully completed
			$output->setMessage('SVPG_RC'); // receive_confirmed
			//$output->state = '2'; // successfully completed
		}
		else
		{
			//$output->setError(-1);
			//$output->setMessage('amount not match');
			//$output->state = '1'; // not completed
			$output->setError(-1); // not completed
			$output->setMessage('SVPG_AME'); // amount_mismatch_error
		}
		return $output;
	}

	function getReceipt($pg_tid, $paymethod = NULL)
	{
		Context::set('tid', $pg_tid);
		$oTemplate = &TemplateHandler::getInstance();
		$tpl_path = _XE_PATH_."modules/svpg/plugins/iniescrow/tpl";
		$tpl_file = 'receipt.html';
		$tpl = $oTemplate->compile($tpl_path, $tpl_file);
		return $tpl;
	}

	function getReport() 
	{
		$output = new BaseObject();
		$output->order_srl = Context::get('no_oid');
		$output->amount = Context::get('amt_input');
		return $output;
	}

	function dispEscrowDelivery(&$order_info, &$payment_info, &$escrow_info)
	{
		Context::set('order_info', $order_info);
		Context::set('payment_info', $payment_info);
		Context::set('escrow_info', $escrow_info);
		$oTemplate = &TemplateHandler::getInstance();
		$tpl_path = _XE_PATH_."modules/svpg/plugins/iniescrow/tpl";
		$tpl_file = 'escrow_delivery.html';
		return $oTemplate->compile($tpl_path, $tpl_file);
	}

	function dispEscrowConfirm(&$order_info, &$payment_info, &$escrow_info)
	{
		Context::set('order_info', $order_info);
		Context::set('payment_info', $payment_info);
		Context::set('escrow_info', $escrow_info);
		$oTemplate = &TemplateHandler::getInstance();
		$tpl_path = _XE_PATH_."modules/svpg/plugins/iniescrow/tpl";
		$tpl_file = 'escrow_confirm.html';
		return $oTemplate->compile($tpl_path, $tpl_file);
	}

	function dispEscrowDenyConfirm(&$order_info, &$payment_info, &$escrow_info)
	{
		Context::set('order_info', $order_info);
		Context::set('payment_info', $payment_info);
		Context::set('escrow_info', $escrow_info);
		$oTemplate = &TemplateHandler::getInstance();
		$tpl_path = _XE_PATH_."modules/svpg/plugins/iniescrow/tpl";
		$tpl_file = 'escrow_denyconfirm.html';
		return $oTemplate->compile($tpl_path, $tpl_file);
	}


	function procEscrowDelivery()
	{
		$inipayhome = sprintf(_XE_PATH_."files/svpg/%s", $this->plugin_info->plugin_srl);

		$vars = Context::getRequestVars();
		debugPrint($vars);
		extract(get_object_vars($vars));

		require("libs/INILib.php");
		$iniescrow = new INIpay50;
		$iniescrow->SetField("inipayhome", $inipayhome);
		$iniescrow->SetField("tid", Context::get('tid'));
		$iniescrow->SetField("mid", $this->plugin_info->inicis_id);
		$iniescrow->SetField("admin", $this->plugin_info->inicis_pass);
		$iniescrow->SetField("type", "escrow");
		$iniescrow->SetField("escrowtype", "dlv");
		$iniescrow->SetField("dlv_ip", getenv("REMOTE_ADDR"));
		$iniescrow->SetField("debug", "true");

		$iniescrow->SetField("oid",$oid);
		$iniescrow->SetField("soid","1");
		$iniescrow->SetField("dlv_date",$dlv_date);
		$iniescrow->SetField("dlv_time",$dlv_time);
		$iniescrow->SetField("dlv_report",$EscrowType);
		$iniescrow->SetField("dlv_invoice",$invoice);
		$iniescrow->SetField("dlv_name",$dlv_name);
		
		$iniescrow->SetField("dlv_excode",$dlv_exCode);
		$iniescrow->SetField("dlv_exname",$dlv_exName);
		$iniescrow->SetField("dlv_charge",$dlv_charge);
		
		$iniescrow->SetField("dlv_invoiceday",$dlv_invoiceday);
		$iniescrow->SetField("dlv_sendname",$sendName);
		$iniescrow->SetField("dlv_sendpost",$sendPost);
		$iniescrow->SetField("dlv_sendaddr1",$sendAddr1);
		$iniescrow->SetField("dlv_sendaddr2",$sendAddr2);
		$iniescrow->SetField("dlv_sendtel",$sendTel);

		$iniescrow->SetField("dlv_recvname",$recvName);
		$iniescrow->SetField("dlv_recvpost",$recvPost);
		$iniescrow->SetField("dlv_recvaddr",$recvAddr);
		$iniescrow->SetField("dlv_recvtel",$recvTel);
		
		$iniescrow->SetField("dlv_goodscode",$goodsCode);
		$iniescrow->SetField("dlv_goods",$goods);
		$iniescrow->SetField("dlv_goodscnt",$goodCnt);
		$iniescrow->SetField("price",$price);
		$iniescrow->SetField("dlv_reserved1",$reserved1);
		$iniescrow->SetField("dlv_reserved2",$reserved2);
		$iniescrow->SetField("dlv_reserved3",$reserved3);
		
		$iniescrow->SetField("pgn",$pgn);

		/*********************
		 * 3. ?????? ?????? ?????? *
		 *********************/
		$iniescrow->startAction();
		
		
		/**********************
		 * 4. ?????? ??????  ?????? *
		 **********************/
		 
		$tid        = $iniescrow->GetResult("tid"); 					// ????????????
		$resultCode = $iniescrow->GetResult("ResultCode");		// ???????????? ("00"?????? ?????? ??????)
		$resultMsg  = $iniescrow->GetResult("ResultMsg"); 			// ???????????? (??????????????? ?????? ??????)
		$dlv_date   = $iniescrow->GetResult("DLV_Date");
		$dlv_time   = $iniescrow->GetResult("DLV_Time");

		Context::set('tid', $tid);
		Context::set('resultCode', $resultCode);
		Context::set('resultMsg', $resultMsg);
		Context::set('dlv_date', $dlv_date);
		Context::set('dlv_time', $dlv_time);


		$oTemplate = &TemplateHandler::getInstance();
		$tpl_path = _XE_PATH_."modules/svpg/plugins/iniescrow/tpl";
		$tpl_file = 'escrow_delivery_result.html';
		$tpl = $oTemplate->compile($tpl_path, $tpl_file);
		$output = new BaseObject();
		if(!$resultCode != '00') $output->setError(-1);
		$output->add('order_srl', $order_srl);
		$output->add('pg_tid', $tid);
		$output->add('pg_oid', $oid);
		$output->add('invoice_no', $invoice);
		$output->add('registrant', $dlv_name);
		$output->add('deliverer_code', $dlv_exCode);
		$output->add('deliverer_name', $dlv_exName);
		$output->add('delivery_type', ($dlv_charge == 'SH' ? '1':'2'));
		$output->add('delivery_date', $dlv_invoiceday);
		$output->add('sender_name', $sendName);
		$output->add('sender_postcode', $sendPost);
		$output->add('sender_address1', $sendAddr1);
		$output->add('sender_address2', $sendAddr2);
		$output->add('sender_telnum', $sendTel);
		$output->add('recipient_name', $recvName);
		$output->add('recipient_postcode', $recvPost);
		$output->add('recipient_address', $recvAddr);
		$output->add('recipient_telnum', $recvTel);
		$output->add('product_code', $goodsCode);
		$output->add('product_name', $goods);
		$output->add('quantity', $goodCnt);
		$output->add('price', $price);
		$output->add('result_code', $resultCode);
		$output->add('result_message', iconv('EUC-KR','UTF-8',$resultMsg));
		$output->setMessage($tpl);
		return $output;
	}

	function procEscrowConfirm()
	{
		$inipayhome = sprintf(_XE_PATH_."files/svpg/%s", $this->plugin_info->plugin_srl);

		$vars = Context::getRequestVars();
		debugPrint('procEscrowConfirm');
		debugPrint($vars);

		extract(get_object_vars($vars));

		debugPrint('encrypted : ' . $encrypted);
		debugPrint('sessionkey : ' . $sessionkey);

		require("libs/INILib.php");
		$iniescrow = new INIpay50;
		$iniescrow->SetField("inipayhome", $inipayhome);
		$iniescrow->SetField("tid", Context::get('tid'));
		$iniescrow->SetField("mid", $this->plugin_info->inicis_id);
		$iniescrow->SetField("admin", $this->plugin_info->inicis_pass);
		$iniescrow->SetField("type", "escrow");
		$iniescrow->SetField("escrowtype", "confirm");
		$iniescrow->SetField("debug", "true");

		$iniescrow->SetField("encrypted",$encrypted);
		$iniescrow->SetField("sessionkey",$sessionkey);

		/*********************
		 * 3. ???????????? ?????? *
		 *********************/
		$iniescrow->startAction();
		
		
		/**********************
		 * 4. ????????????  ?????? *
		 **********************/
		 
		$tid          = $iniescrow->GetResult("tid"); 		// ????????????
		$resultCode   = $iniescrow->GetResult("ResultCode");	// ???????????? ("00"?????? ?????? ??????)
		$resultMsg    = $iniescrow->GetResult("ResultMsg");    // ???????????? (??????????????? ?????? ??????)
		$resultDate   = $iniescrow->GetResult("CNF_Date");    // ?????? ??????
		$resultTime   = $iniescrow->GetResult("CNF_Time");    // ?????? ??????

		if($resultDate=="" )
		{
			 $resultDate   = $iniescrow->GetResult("DNY_Date");    // ?????? ??????
			 $resultTime   = $iniescrow->GetResult("DNY_Time");    // ?????? ??????
		}

		Context::set('tid', $tid);
		Context::set('resultCode', $resultCode);
		Context::set('resultMsg', $resultMsg);
		Context::set('resultDate', $resultDate);
		Context::set('resultTime', $resultTime);


		debugPrint('$resultDate.$resultTime');
		debugPrint($resultDate.$resultTime);

		$oTemplate = &TemplateHandler::getInstance();
		$tpl_path = _XE_PATH_."modules/svpg/plugins/iniescrow/tpl";
		$tpl_file = 'escrow_confirm_result.html';
		$tpl = $oTemplate->compile($tpl_path, $tpl_file);
		$output = new BaseObject();
		if(!$resultCode != '00') $output->setError(-1);
		$output->add('order_srl', Context::get('order_srl'));
		$output->add('confirm_code', $resultCode);
		$output->add('confirm_message', iconv('EUC-KR','UTF-8',$resultMsg));
		$output->add('confirm_date', $resultDate.$resultTime);
		$output->setMessage($tpl);
		return $output;
	}

	function procEscrowDenyConfirm()
	{
		$inipayhome = sprintf(_XE_PATH_."files/svpg/%s", $this->plugin_info->plugin_srl);

		$vars = Context::getRequestVars();
		debugPrint('procEscrowDenyConfirm');
		debugPrint($vars);
		extract(get_object_vars($vars));

		/**************************
		 * 1. ??????????????? ???????????? *
		 **************************/
		require("libs/INILib.php");
		
		
		/***************************************
		 * 2. INIpay50 ???????????? ???????????? ?????? *
		 ***************************************/
		$iniescrow = new INIpay50;
		
		/*********************
		 * 3. ?????? ?????? ?????? *
		 *********************/
		$iniescrow->SetField("inipayhome", $inipayhome);
		$iniescrow->SetField("tid", Context::get('tid'));
		$iniescrow->SetField("mid", $this->plugin_info->inicis_id);
		$iniescrow->SetField("admin", $this->plugin_info->inicis_pass);
		$iniescrow->SetField("type", "escrow");
		$iniescrow->SetField("escrowtype", "dcnf"); 				                    // ?????? (?????? ?????? ??????)
		$iniescrow->SetField("dcnf_name",$dcnf_name);
		$iniescrow->SetField("debug", "true");

		/*********************
		 * 3. ???????????? ?????? *
		 *********************/
		$iniescrow->startAction();
		
		
		/**********************
		 * 4. ????????????  ?????? *
		 **********************/
		 
		debugPrint($iniescrow->m_Data);
		$tid          = $iniescrow->GetResult("tid"); 					// ????????????
		$resultCode   = $iniescrow->GetResult("ResultCode");		// ???????????? ("00"?????? ?????? ??????)
		$resultMsg    = $iniescrow->GetResult("ResultMsg");    // ???????????? (??????????????? ?????? ??????)
		$resultDate   = $iniescrow->GetResult("DCNF_Date");    // ?????? ??????
		$resultTime   = $iniescrow->GetResult("DCNF_Time");    // ?????? ??????

		Context::set('tid', $tid);
		Context::set('resultCode', $resultCode);
		Context::set('resultMsg', $resultMsg);
		Context::set('resultDate', $resultDate);
		Context::set('resultTime', $resultTime);
		
		$oTemplate = &TemplateHandler::getInstance();
		$tpl_path = _XE_PATH_."modules/svpg/plugins/iniescrow/tpl";
		$tpl_file = 'escrow_denyconfirm_result.html';
		$tpl = $oTemplate->compile($tpl_path, $tpl_file);
		$output = new BaseObject();
		if(!$resultCode != '00') $output->setError(-1);
		$output->add('order_srl', Context::get('order_srl'));
		$output->add('denyconfirm_code', $resultCode);
		$output->add('denyconfirm_message', iconv('EUC-KR','UTF-8',$resultMsg));
		$output->add('denyconfirm_date', $resultDate.$resultTime);
		$output->setMessage($tpl);
		return $output;

	}

	function getPaymethod($paymethod)
	{
		switch ($paymethod) {
			case 'VBank':
				return 'VA';
			case 'Card':
			case 'VCard':
				return 'CC';
			case 'HPP':
				return 'MP';
			case 'DirectBank':
				return 'IB';
			default:
				return '  ';
		}
	}

	function getBankName($code)
	{
	    switch($code) {
		case "03" : return "????????????"; break;
		case "04" : return "????????????"; break;
		case "05" : return "????????????"; break;
		case "07" : return "???????????????"; break;
		case "11" : return "???????????????"; break;
		case "20" : return "????????????"; break;
		case "23" : return "SC????????????"; break;
		case "31" : return "????????????"; break;
		case "32" : return "????????????"; break;
		case "34" : return "????????????"; break;
		case "37" : return "????????????"; break;
		case "39" : return "????????????"; break;
		case "53" : return "??????????????????"; break;
		case "71" : return "?????????"; break;
		case "81" : return "????????????"; break;
		case "88" : return "??????????????????(??????,????????????)"; break;
		case "D1" : return "????????????????????????"; break;
		case "D2" : return "????????????"; break;
		case "D3" : return "??????????????????"; break;
		case "D4" : return "??????????????????"; break;
		case "D5" : return "??????????????????"; break;
		case "D6" : return "??????????????????"; break;
		case "D7" : return "HMC????????????"; break;
		case "D8" : return "SK??????"; break;
		case "D9" : return "????????????"; break;
		case "DB" : return "?????????????????????"; break;
		case "DC" : return "????????????"; break;
		case "DD" : return "??????????????????"; break;
		case "DE" : return "???????????????"; break;
		case "DF" : return "????????????"; break;
		default   : return ""; break;
	    }
	}
}
/* End of file iniescrow.plugin.php */
/* Location: ./modules/svpg/plugins/iniescrow/iniescrow.plugin.php */
