<?php
define('INIPAY_HOME', _XE_PATH_.'files/svpg/inipay5');
define('INIPAY_LOGDIR', _XE_PATH_.'files/svpg/inipay5/log');
define('INIPAY_KEYDIR', _XE_PATH_.'files/svpg/inipay5/key');

class inipay5 extends SvpgPlugin 
{
	var $plugin_info;

	function pluginInstall($args) 
	{
		// mkdir
		FileHandler::makeDir(sprintf(_XE_PATH_."files/svpg/%s/key",$args->plugin_srl));
		FileHandler::makeDir(sprintf(_XE_PATH_."files/svpg/%s/log",$args->plugin_srl));
		// copy files
		FileHandler::copyFile(_XE_PATH_.'modules/svpg/plugins/inipay5/.htaccess',sprintf(_XE_PATH_."files/svpg/%s/.htaccess",$args->plugin_srl));
		FileHandler::copyFile(_XE_PATH_.'modules/svpg/plugins/inipay5/readme.txt',sprintf(_XE_PATH_."files/svpg/%s/readme.txt",$args->plugin_srl));
		FileHandler::copyFile(_XE_PATH_.'modules/svpg/plugins/inipay5/key/pgcert.pem',sprintf(_XE_PATH_."files/svpg/%s/key/pgcert.pem",$args->plugin_srl));
	}

	function inipay5() 
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
		$tpl_path = _XE_PATH_."modules/svpg/plugins/inipay5/tpl";
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
		$inipay->SetField("quotabase", iconv('UTF-8', 'EUC-KR', '??????:?????????:2??????:3??????:4??????:5??????'));

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
		$tpl_path = _XE_PATH_."modules/svpg/plugins/inipay5/tpl";
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
			//$output->setError(0);
			//$output->state = '2'; // successfully completed
			$output->setError(0); // successfully completed
			$output->setMessage('SVPG_RC'); // receive_confirmed
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
		Context::set('paymethod', $paymethod);
		$oTemplate = &TemplateHandler::getInstance();
		$tpl_path = _XE_PATH_."modules/svpg/plugins/inipay5/tpl";
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
/* End of file inipay5.plugin.php */
/* Location: ./modules/svpg/plugins/inipay5/inipay5.plugin.php */
