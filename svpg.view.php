<?php
/**
 * @class svpgView
 * @author singleview(root@singleview.co.kr)
 * @brief svpg view
 **/
class svpgView extends svpg
{
	function init()
	{
		Context::set('admin_bar', 'false');
		Context::set('hide_trolley', 'true');

		if (!$this->module_info->skin) $this->module_info->skin = 'default';
		$this->setTemplatePath($this->module_path."skins/{$this->module_info->skin}");
	}
/**
 * $in_args->svpg_module_srl
 * $in_args->module_srl
 * $in_args->item_name
 * $in_args->price
 * $in_args->target_module
 * $in_args->join_form
 * $in_args->purchaser_name
 * $in_args->purchaser_email
 * $in_args->purchaser_telnum
 */
	function getPaymentForm($in_args)
	{
		$oModuleModel = &getModel('module');
		$oSvpgModel = &getModel('svpg');
		if (!$in_args->svpg_module_srl)
			return new BaseObject(-1, 'msg_invalid_svpg_module');

		$module_info = $oModuleModel->getModuleInfoByModuleSrl($in_args->svpg_module_srl);
		$fCurDatetime = (float)date('YmdHis');
		$fForbidDatetime = (float)$module_info->forbid_settlement_after;
		if( $fForbidDatetime && $fCurDatetime > $fForbidDatetime )
		{
			$date = DateTime::createFromFormat('YmdHis', $module_info->forbid_settlement_after);
			$date->modify('+1 day');
			$output = new BaseObject();
			$output->data = '결제 기능을 점검 중입니다.<BR>'.$date->format('Y-m-d').'부터 재개될 예정입니다.';
			return $output;
		}

		Context::set('svpg_module_srl', $in_args->svpg_module_srl);
		Context::set('module_srl', $in_args->module_srl);
		Context::set('item_name', $in_args->item_name);
		Context::set('price', $in_args->price);
		Context::set('target_module', $in_args->target_module);
		Context::set('join_form', $in_args->join_form);
		$logged_info = Context::get('logged_info');
        if($logged_info)
        {
            if(!$in_args->purchaser_name) $in_args->purchaser_name = $logged_info->nick_name;
            if(!$in_args->purchaser_email) $in_args->purchaser_email = $logged_info->email_address;
            if(!$in_args->purchaser_telnum) $in_args->purchaser_telnum = "010-0000-0000";
        }
        else if(!$logged_info)
        {
            if(!$in_args->purchaser_name) $in_args->purchaser_name = 'GUEST';
            if(!$in_args->purchaser_email) $in_args->purchaser_email = '';
            if(!$in_args->purchaser_telnum) $in_args->purchaser_telnum = "010-0000-0000";
        }
		Context::set('purchaser_name', $in_args->purchaser_name);
		Context::set('purchaser_email', $in_args->purchaser_email);
		Context::set('purchaser_telnum', $in_args->purchaser_telnum);

		$_SESSION['svpg_module_srl'] = $in_args->svpg_module_srl;
		$_SESSION['order_srl'] = $in_args->order_srl;

		$oModuleModel->syncSkinInfoToModuleInfo($module_info);
		if(!$module_info) return new BaseObject(-1, 'msg_invalid_svpg_module');
		if(!$module_info->skin) $module_info->skin = 'default';

		Context::set('svpg_module_info', $module_info);
///////////////////
		Context::set('payment_methods', $this->_g_aPaymentMethod);
///////////////////
		$form_data = '';
		if($_COOKIE['mobile'] != "true")
		{
			if ($module_info->plugin_srl)
			{
				$plugin = $oSvpgModel->getPlugin($module_info->plugin_srl);
				if( is_null( $plugin ) )
					return new BaseObject(-1, 'msg_invaild_plugin_srl');
				$output = $plugin->getFormData($in_args);
				if (!$output->toBool()) return $output;
				$form_data = $output->data;
			}

			if ($module_info->plugin2_srl)
			{
				$plugin2 = $oSvpgModel->getPlugin($module_info->plugin2_srl);
				if( is_null( $plugin2 ) )
					return new BaseObject(-1, 'msg_invaild_plugin_srl');
				$output = $plugin2->getFormData($in_args);
				if (!$output->toBool()) return $output;
				$form_data .= $output->data;
			}

			if ($module_info->plugin3_srl)
			{
				$plugin3 = $oSvpgModel->getPlugin($module_info->plugin3_srl);
				if( is_null( $plugin3 ) )
					return new BaseObject(-1, 'msg_invaild_plugin_srl');
				$output = $plugin3->getFormData($in_args);
				if (!$output->toBool()) return $output;
				$form_data .= $output->data;
			}

			if ($module_info->plugin4_srl)
			{
				$plugin4 = $oSvpgModel->getPlugin($module_info->plugin4_srl);
				if( is_null( $plugin4 ) )
					return new BaseObject(-1, 'msg_invaild_plugin_srl');
				$output = $plugin4->getFormData($in_args);
				if (!$output->toBool()) return $output;
				$form_data .= $output->data;
			}

			if ($module_info->plugin5_srl)
			{
				$plugin5 = $oSvpgModel->getPlugin($module_info->plugin5_srl);
				if( is_null( $plugin5 ) )
					return new BaseObject(-1, 'msg_invaild_plugin_srl');
				$output = $plugin5->getFormData($in_args);
				if (!$output->toBool()) return $output;
				$form_data .= $output->data;
			}
		}
		if($_COOKIE['mobile'] == "true")
		{
			if ($module_info->plugin_srl_mobile1)
			{
				$plugin = $oSvpgModel->getPlugin($module_info->plugin_srl_mobile1);
				// 실시간계좌이체에서 은행공동계좌이체PG 앱에서 이용 기관명 표시
				if( $plugin->plugin_info->plugin == 'inipaymobile' )
				{
					$sMerchantName = $plugin->plugin_info->extra_var->inicis_merchant_name->value;
					if( strlen( $sMerchantName ) > 0 )
						Context::set('merchant_name', $sMerchantName );
					else
						Context::set('merchant_name', '상점명' );
				}
				$output = $plugin->getFormData($in_args);
				if (!$output->toBool()) return $output;
				$form_data .= $output->data;
			}
			if ($module_info->plugin_srl_mobile2)
			{
				$plugin = $oSvpgModel->getPlugin($module_info->plugin_srl_mobile2);
				// 실시간계좌이체에서 은행공동계좌이체PG 앱에서 이용 기관명 표시
				if( $plugin->plugin_info->plugin == 'inipaymobile' )
				{
					$sMerchantName = $plugin->plugin_info->extra_var->inicis_merchant_name->value;
					if( strlen( $sMerchantName ) > 0 )
						Context::set('merchant_name', $sMerchantName );
					else
						Context::set('merchant_name', '상점명' );
				}
				$output = $plugin->getFormData($in_args);
				if (!$output->toBool()) return $output;
				$form_data .= $output->data;
			}
		}
		Context::set('form_data', $form_data);
		Context::set('order_srl', $in_args->order_srl);
		if($_COOKIE['mobile'] == "true")
		{
			$template_path = sprintf("%sm.skins/%s/",$this->module_path, $this->module_info->mskin);
			if(!is_dir($template_path)||!$this->module_info->mskin) {
					$this->module_info->mskin = 'default';
					$template_path = sprintf("%sm.skins/%s/",$this->module_path, $this->module_info->mskin);
			}
		}
		else
		{
			$template_path = $this->module_path."skins/{$module_info->skin}";
			if(!is_dir($template_path)||!$this->module_info->skin) 
            {
                if(is_null($this->module_info))
                    $this->module_info = new stdClass();
				$this->module_info->skin = 'default';
				$template_path = sprintf("%sskins/%s/",$this->module_path, $this->module_info->skin);
			}
		}
		$oTemplate = &TemplateHandler::getInstance();
		$payment_form = $oTemplate->compile($template_path, 'paymentform.html');
		$output = new BaseObject();
		$output->data = $payment_form;
		return $output;
	}
/**
 * @brief 
 **/
	function dispSvpgExtra1()
	{
		$oSvpgModel = &getModel('svpg');
		$plugin = $oSvpgModel->getPlugin(Context::get('plugin_srl'));
		$output = $plugin->dispExtra1($this);
		Context::set('content', $output);
		$this->setTemplatePath($this->module_path."tpl");
		$this->setTemplateFile('extra');
	}
/**
 * @brief 
 **/
	function dispSvpgExtra2()
	{
		$oSvpgModel = &getModel('svpg');
		$plugin = $oSvpgModel->getPlugin(Context::get('plugin_srl'));
		$output = $plugin->dispExtra2($this);
		Context::set('content', $output);
		$this->setTemplatePath($this->module_path."tpl");
		$this->setTemplateFile('extra');
	}
/**
 * @brief 
 **/
	function dispSvpgExtra3()
	{
		$oSvpgModel = &getModel('svpg');
		$plugin = $oSvpgModel->getPlugin(Context::get('plugin_srl'));
		$output = $plugin->dispExtra3($this);
		Context::set('content', $output);
		$this->setTemplatePath($this->module_path."tpl");
		$this->setTemplateFile('extra');
	}
/**
 * @brief 
 **/
	function dispSvpgExtra4()
	{
		$oSvpgModel = &getModel('svpg');
		$plugin = $oSvpgModel->getPlugin(Context::get('plugin_srl'));
		$output = $plugin->dispExtra4($this);
		Context::set('content', $output);
		$this->setTemplatePath($this->module_path."tpl");
		$this->setTemplateFile('extra');
	}
/**
 * @brief 
 **/
	function getRemoteResource($url, $body = null, $timeout = 3, $method = 'GET', $content_type = null, $headers = array(), $cookies = array(), $post_data = array())
	{
		try
		{
			requirePear();
			require_once('HTTP/Request.php');

			$parsed_url = parse_url(__PROXY_SERVER__);
			if($parsed_url["host"])
			{
				$oRequest = new HTTP_Request(__PROXY_SERVER__);
				$oRequest->setMethod('POST');
				$oRequest->_timeout = $timeout;
				$oRequest->addPostData('arg', serialize(array('Destination' => $url, 'method' => $method, 'body' => $body, 'content_type' => $content_type, "headers" => $headers, "post_data" => $post_data)));
			}
			else
			{
				$oRequest = new HTTP_Request($url);
				if(method_exists($oRequest,'setConfig')) $oRequest->setConfig(array('ssl_verify_peer' => FALSE, 'ssl_verify_host' => FALSE));

				if(count($headers))
				{
					foreach($headers as $key => $val)
					{
						$oRequest->addHeader($key, $val);
					}
				}
				if($cookies[$host])
				{
					foreach($cookies[$host] as $key => $val)
					{
						$oRequest->addCookie($key, $val);
					}
				}
				if(count($post_data))
				{
					foreach($post_data as $key => $val)
					{
//debugPrint('key : ' . $key);
//debugPrint('val : ' . $val);
						$oRequest->addPostData($key, $val);
					}
				}
				if(!$content_type)
					$oRequest->addHeader('Content-Type', 'text/html');
				else
					$oRequest->addHeader('Content-Type', $content_type);
				$oRequest->setMethod($method);
				if($body)
					$oRequest->setBody($body);

				$oRequest->_timeout = $timeout;
			}

			$oResponse = $oRequest->sendRequest();
			$code = $oRequest->getResponseCode();
			$header = $oRequest->getResponseHeader();
			$response = $oRequest->getResponseBody();
			if($c = $oRequest->getResponseCookies())
			{
				foreach($c as $k => $v)
				{
					$cookies[$host][$v['name']] = $v['value'];
				}
			}

			if($code > 300 && $code < 399 && $header['location'])
				return $this->getRemoteResource($header['location'], $body, $timeout, $method, $content_type, $headers, $cookies, $post_data);

			if($code != 200)
				return;

			return $response;
		}
		catch(Exception $e)
		{
			return NULL;
		}
	}
/**
 * @brief 
 **/
	function dispSvpgTransaction()
	{
		if($_COOKIE['mobile'] != "true")
		{
			if(!$this->module_info->skin) $this->module_info->skin = 'default';
			$skin = $this->module_info->skin;
			$this->setTemplatePath(sprintf('%sskins/%s', $this->module_path, $skin));
		}
		// * inipaymobile P_RETURN_URL 페이지 처리를 위한 코드
		// * ISP 결제시 r_page에 order_srl이 담겨져옴, 결제처리는 P_NOTI_URL이 호출되므로 여기서는 그냥 결과만 보여줌
		if(Context::get('r_page'))
		{
			$vars = Context::getRequestVars();
			$vars->P_RMESG1 = iconv('EUC-KR','UTF-8',$vars->P_RMESG1);
			$mid = $_SESSION['xe_mid'];
			Context::set('order_srl', Context::get('r_page'));
			$return_url = getNotEncodedUrl('','mid',$mid,'act','dispSvcartOrderComplete','order_srl',Context::get('order_srl'));
			$this->setRedirectUrl($return_url);
			return;
		}
		// * inipaymobile P_NEXT_URL 페이지 처리를 위한 코드
		// * 가상계좌, 안심클릭시 n_page에 order_srl이 담겨져옴, P_REQ_URL에 POST로 P_TID와 P_MID를 넘겨줘야 결제요청이 완료됨
		if(Context::get('n_page'))
		{
			$vars = Context::getRequestVars();
			$vars->P_RMESG1 = iconv('EUC-KR','UTF-8',$vars->P_RMESG1);
			$mid = $_SESSION['xe_mid'];

			// P_TID에 값이 없으면 취소되었음
			if(!$vars->P_TID)
			{
				Context::set('order_srl', Context::get('n_page'));
				$return_url = getNotEncodedUrl('','mid',$mid,'act','dispSvcartOrderComplete','order_srl',Context::get('order_srl'));
				$this->setRedirectUrl($return_url);
				return;
			}

			// P_NOTI에 plugin_srl, svpg_module_srl 등을 담고 있음
			parse_str($vars->P_NOTI, $output);
			foreach($output as $key=>$val)
			{
				Context::set($key, $val);
			}

			$nPluginSrl = (int)Context::get( 'plugin_srl' );
			$oSvpgModel = &getModel('svpg');
			$plugin = $oSvpgModel->getPlugin( $nPluginSrl );
			$sP_MID = $plugin->plugin_info->inicis_id;

			//$post_data = array('P_TID'=>$vars->P_TID,'P_MID'=>$vars->P_MID);
			$post_data = array('P_TID'=>$vars->P_TID,'P_MID'=>$sP_MID );

			$response = $this->getRemoteResource($vars->P_REQ_URL, null, 3, 'POST', 'application/x-www-form-urlencoded',  array(), array(), $post_data);
			parse_str($response, $output);
			$P_RMESG1 = iconv('EUC-KR','UTF-8',$output['P_RMESG1']);

			foreach($output as $key=>$val)
			{
				Context::set($key, $val);
			}

			// P_NOTI에 plugin_srl, svpg_module_srl 등을 담고 있음
			/*parse_str($vars->P_NOTI, $output);
			foreach($output as $key=>$val)
			{
				Context::set($key, $val);
			}*/
			// inipaymobile_pass = TRUE로 해주어서 inipaymobile에서 결제처리되도록 함
			$_SESSION['inipaymobile_pass'] = TRUE;

			$oSvpgController = &getController('svpg');
			$output = $oSvpgController->procSvpgDoPayment();
			if(is_object($output) && method_exists($output, 'toBool'))
			{
				if(!$output->toBool())
					return $output;
			}
			if($oSvpgController->get('return_url'))
				$this->setRedirectUrl($oSvpgController->get('return_url'));

			return;
		}
		$logged_info = Context::get('logged_info');
		if(!$logged_info) 
			return new BaseObject(-1, 'msg_login_required');

		if($logged_info)
		{
			if(Context::get('start_date'))
			{
				$start_date = date("Ymd", mktime(0, 0, 0, date("m") - Context::get('start_date'), date("d"), date("Y")));
				if(Context::get('start_date') == 'a')
					$start_date = null;
			}

			$args->member_srl = $logged_info->member_srl;
			$args->page = Context::get('page');
			$args->regdate_more = $start_date;
			$output = executeQueryArray('svpg.getTransactionByMemberSrl', $args);

			Context::set('total_count', $output->total_count);
			Context::set('total_page', $output->total_page);
			Context::set('page', $output->page);
			Context::set('page_navigation', $output->page_navigation);

			$svpg_user_info = $output->data;

			$today = date("Ymd",mktime(0,0,0,date("m"), date("d")-5, date("Y")));
			foreach($svpg_user_info as $k => $v)
			{
				if(substr($v->regdate,0,8) < $today && $v->state != 2)
				{
					$v->state = 3;
				}
				if($v->state == 1) $v->result_message = "결제진행중";
				else if($v->state == 2) $v->result_message = "결제성공";
				else $v->result_message = "결제실패";

				if(is_array($v->order_title)) $v->order_title = implode($v->order_title,',');
				if(!$v->order_title) $v->order_title = $v->target_module;

				switch($v->payment_method)
				{
					case "CC":
						$v->payment_method = "신용카드";
						break;
					case "BT":
						$v->payment_method = "무통장 입금";
						break;
					case "IB":
						$v->payment_method = "실시간계좌이체";
						break;
					case "VA":
						$v->payment_method = "가상계좌";
						break;
					case "MP":
						$v->payment_method = "휴대폰 결제";
						break;
					case "PP":
						$v->payment_method = "페이팔";
						break;
				}
				$v->extra_vars = unserialize($v->extra_vars);
			}
			Context::set("svpg_user_info", $output->data);
		}
		$this->setTemplateFile('transaction');
	}
/**
 * @brief 
 **/
	function dispSvpgError()
	{
		$oSvpgModel = &getModel('svpg');
		$transaction_info = $oSvpgModel->getTransactionInfo(Context::get('transaction_srl'));
		Context::set('transaction_info', $transaction_info);
		$this->setTemplatePath($this->module_path."tpl");
		$this->setTemplateFile('error');
	}
}
/* End of file svpg.view.php */
/* Location: ./modules/svpg/svpg.view.php */