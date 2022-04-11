<?php
/**
 * @class  svpgModel
 * @author singleview(root@singleview.co.kr)
 * @brief  svpgModel class
 **/
class svpgModel extends svpg
{
/**
 * @brief parse xml, retrieve plugin info.
 * (this function will be removed in the future)
 **/
	function getPluginInfoXml($plugin, $vars=array())
	{
		$plugin_path = _XE_PATH_."modules/svpg/plugins/".$plugin;
		$xml_file = sprintf(_XE_PATH_."modules/svpg/plugins/%s/info.xml", $plugin);
		if(!file_exists($xml_file)) 
			return;

		$oXmlParser = new XeXmlParser();
		$tmp_xml_obj = $oXmlParser->loadXmlFile($xml_file);
		$xml_obj = $tmp_xml_obj->plugin;

		if(!$xml_obj)
			return;
		$plugin_info = new stdClass();
		$plugin_info->title = $xml_obj->title->body;
		$plugin_info->description = $xml_obj->description->body;
		$plugin_info->version = $xml_obj->version->body;
		$date_obj = new stdClass();
		sscanf($xml_obj->date->body, '%d-%d-%d', $date_obj->y, $date_obj->m, $date_obj->d);
		$plugin_info->date = sprintf('%04d%02d%02d', $date_obj->y, $date_obj->m, $date_obj->d);
		$plugin_info->license = $xml_obj->license->body;
		$plugin_info->license_link = $xml_obj->license->attrs->link;

		if(!is_array($xml_obj->author)) $author_list[] = $xml_obj->author;
		else $author_list = $xml_obj->author;

		foreach($author_list as $author)
		{
			$author_obj = new stdClass();
			$author_obj->name = $author->name->body;
			$author_obj->email_address = $author->attrs->email_address;
			$author_obj->homepage = $author->attrs->link;
			$plugin_info->author[] = $author_obj;
		}
		$buff = '';
		$buff .= sprintf('$plugin_info->site_srl = "%s";', $site_srl);

		// 추가 변수 (템플릿에서 사용할 제작자 정의 변수)
		$extra_var_groups = $xml_obj->extra_vars->group;
		if(!$extra_var_groups)
			$extra_var_groups = $xml_obj->extra_vars;
		if(!is_array($extra_var_groups)) $extra_var_groups = array($extra_var_groups);
		foreach($extra_var_groups as $group)
		{
			$extra_vars = $group->var;
			if($extra_vars)
			{
				if(!is_array($extra_vars))
					$extra_vars = array($extra_vars);
				$extra_var_count = count($extra_vars);
				$buff .= sprintf('$plugin_info->extra_var_count = "%s";', $extra_var_count);
				$buff .= sprintf('$plugin_info->extra_var = new stdClass();');
				for($i=0;$i<$extra_var_count;$i++)
				{
					unset($var);
					unset($options);
					$var = $extra_vars[$i];
					$name = $var->attrs->name;
					$buff .= sprintf('$plugin_info->extra_var->%s = new stdClass();', $name);
					$buff .= sprintf('$plugin_info->extra_var->%s->group = "%s";', $name, $group->title->body);
					$buff .= sprintf('$plugin_info->extra_var->%s->title = "%s";', $name, $var->title->body);
					$buff .= sprintf('$plugin_info->extra_var->%s->type = "%s";', $name, $var->attrs->type);
					$buff .= sprintf('$plugin_info->extra_var->%s->default = "%s";', $name, $var->attrs->default);
					if ($var->attrs->type=='image'&&$var->attrs->location)
						$buff .= sprintf('$plugin_info->extra_var->%s->location = "%s";', $name, $var->attrs->location);
					$buff .= sprintf('$plugin_info->extra_var->%s->value = $vars->%s;', $name, $name);
					$buff .= sprintf('$plugin_info->extra_var->%s->description = "%s";', $name, str_replace('"','\"',$var->description->body));
					$options = $var->options;
					if(!$options)
						continue;

					if(!is_array($options)) $options = array($options);
					$options_count = count($options);
					$thumbnail_exist = false;
					for($j=0; $j < $options_count; $j++)
					{
						$buff .= sprintf('$plugin_info->extra_var->%s->options["%s"] = new stdClass();', $var->attrs->name, $options[$j]->attrs->value);
						$thumbnail = $options[$j]->attrs->src;
						if($thumbnail)
						{
							$thumbnail = $plugin_path.$thumbnail;
							if(file_exists($thumbnail))
							{
								$buff .= sprintf('$plugin_info->extra_var->%s->options["%s"]->thumbnail = new stdClass();', $var->attrs->name, $options[$j]->attrs->value, $thumbnail);
								$buff .= sprintf('$plugin_info->extra_var->%s->options["%s"]->thumbnail = "%s";', $var->attrs->name, $options[$j]->attrs->value, $thumbnail);
								if(!$thumbnail_exist)
								{
									$buff .= sprintf('$plugin_info->extra_var->%s->thumbnail_exist = new stdClass();', $var->attrs->name);
									$buff .= sprintf('$plugin_info->extra_var->%s->thumbnail_exist = true;', $var->attrs->name);
									$thumbnail_exist = true;
								}
							}
						}
						$buff .= sprintf('$plugin_info->extra_var->%s->options["%s"]->val = "%s";', $var->attrs->name, $options[$j]->attrs->value, $options[$j]->title->body);
					}
				}
			}
		}
		if($buff)
			eval($buff);
		return $plugin_info;
	}
/**
 * @brief read pg plugin xml files.
 * (this function will be removed in the future)
 **/
	function getPluginsXmlInfo()
	{
		// read PG plugins
		$searched_list = FileHandler::readDir(_XE_PATH_.'modules/svpg/plugins');
		$searched_count = count($searched_list);
		if(!$searched_count)
			return;
		sort($searched_list);

		$list = array();
		for($i=0;$i<$searched_count;$i++)
		{
			$plugin_name = $searched_list[$i];
			$info = $this->getPluginInfoXml($plugin_name);
			$info->plugin = $plugin_name;
			$list[] = $info;
		}
		return $list;
	}
/**
 * @brief (this function will be removed in the future)
 */
	function getPluginInfoEx($info)
	{
		$plugin_title = $info->title;
		$plugin = $info->plugin;
		$plugin_srl = $info->plugin_srl;
		$vars = unserialize($info->extra_vars);
		$output = $this->getPluginInfoXml($plugin, $vars);
		$output->plugin_title = $plugin_title;
		$output->plugin = $plugin;
		$output->plugin_srl = $plugin_srl;
		return $output;
	}
/**
 * @brief (this function will be removed in the future)
 */
	function getPluginInfo($plugin_srl)
	{
		// 일단 DB에서 정보를 가져옴
		$args = new stdClass();
		$args->plugin_srl = $plugin_srl;
		$output = executeQuery('svpg.getPluginInfo', $args);
		if(!$output->data)
			return;
		// plugin, extra_vars를 정리한 후 xml 파일 정보를 정리해서 return
		$plugin_info = $this->getPluginInfoEx($output->data);
		return $plugin_info;
	}
/**
 * @brief (this function will be removed in the future)
 */
	function getPluginList()
	{
		$output = executeQueryArray('svpg.getPluginList');
		return $output->data;
	}
/**
 * @brief return svpg module instances.
 */
	function getSvpgList()
	{
		$args = new stdClass();
		$args->sort_index = "module_srl";
		$args->list_count = 99;
		$output = executeQueryArray('svpg.getSvpgList', $args);
		return $output->data;
	}
/**
 * @brief (this function will be removed in the future)
 */
	function getPlugin($plugin_srl)
	{
		$plugin_info = $this->getPluginInfo($plugin_srl);
		if(is_null($plugin_info))
			return null;
		require_once(sprintf("%ssvpg.plugin.php",$this->module_path));
		require_once(sprintf("%splugins/%s/%s.plugin.php",$this->module_path, $plugin_info->plugin, $plugin_info->plugin));

		// $tmpFn = create_function('', "return new {$plugin_info->plugin}();");
		$pluginObj = eval("return new {$plugin_info->plugin}();");//$tmpFn();
		$pluginObj->init($plugin_info);
		return $pluginObj;
	}
/**
 * @brief return transaction info.
 */
	function getTransactionInfo($transaction_srl)
	{
		$args->transaction_srl = $transaction_srl;
		$output = executeQuery('svpg.getTransactionInfo',$args);
		$payment_info = $output->data;
		if ($payment_info) 
		{
			$extra_vars = unserialize($payment_info->extra_vars);
			if ($extra_vars) 
			{
				foreach ($extra_vars as $key=>$val) 
					$payment_info->{$key} = $val;
			}
		}
		return $payment_info;
	}
/**
 * @brief ./svpg.admin.model.php::getTransactionByOrderSrl()과 통일성 유지
 */
	function getTransactionByOrderSrl($order_srl)
	{
		$args = new stdClass();
		$args->order_srl = $order_srl;
		$args->state = array('1','2');
		
		$output = executeQuery('svpg.getTransactionByOrderSrl',$args);
		$payment_info = $output->data;
		if ($payment_info) 
		{
			$extra_vars = unserialize($payment_info->extra_vars);
			if ($extra_vars) 
			{
				foreach ($extra_vars as $key=>$val) 
					$payment_info->{$key} = $val;
			}
		}
		$aTranslation = Context::getLang('payment_method');
		$payment_info->payment_method_translated = $aTranslation[$payment_info->payment_method];
		return $payment_info;
	}
/**
 * @brief 
 */
	function getTransactionCountByMemberSrl($member_srl)
	{
		$args->member_srl = $member_srl;
		$args->state = '2';
		$output = executeQuery('svpg.getTransactionCountByMemberSrl', $args);
		if(!$output->toBool())
			return $output;
		$count = $output->data->count;
		return $count;
	}
/**
 * @brief 
 */
	function getPluginByName($plugin_name)
	{
		if (!$plugin_name)
			return;
		require_once(sprintf("%ssvpg.plugin.php",$this->module_path));
		require_once(sprintf("%splugins/%s/%s.plugin.php",$this->module_path, $plugin_name, $plugin_name));
		$tmpFn = create_function('', "return new {$plugin_name}();");
		$pluginObj = $tmpFn();
		return $pluginObj;
	}
/**
 * @brief svorder/tpl/skin.js/script.js에서 호출
 */
	function getSvpgReceipt()
	{
		$oModuleModel = &getModel('module');
		$nOrderSrl = Context::get('order_srl');
		if (!$nOrderSrl)
		{
			$this->add('tpl', Context::getLang('no_data'));
			return;
		}
		$oTransaction = $this->getTransactionByOrderSrl($nOrderSrl);
		if (!$oTransaction || !$oTransaction->plugin_srl)
		{
			$this->add('tpl', Context::getLang('no_data'));
			return;
		}
		$oPlugin = $this->getPlugin($oTransaction->plugin_srl);
		if( !$oPlugin )
		{
			$this->add('tpl', Context::getLang('msg_invaild_plugin_srl'));
			return;
		}
		if(method_exists($oPlugin, 'getReceipt')) 
			$tpl = str_replace("\n", " ", $oPlugin->getReceipt($oTransaction->pg_tid, $oTransaction->payment_method));
		$this->add('tpl', $tpl);
	}
/**
 * @brief 
 */
	function getSvpgCheckUserId()
	{
		$logged_info = Context::get('logged_info');
		if($logged_info->is_admin != 'Y')
			return new BaseObject(-1, 'msg_invaild_request');
		
		if(!Context::get('manorder_pid'))
			return;
		else 
		{
			$oMemberModel = &getModel('member');
			$columnList = array('email_address', 'user_id', 'nick_name');
			$output = $oMemberModel->getMemberInfoByUserID(Context::get('manorder_pid'), $columnList);
			if($output)
			{
				unset($output->password);
				$this->add('data', $output);
				$this->add('manorder_pid_message', '존재하는 아이디 입니다.');
				return $output;
			}
			else 
			{
				$this->add('manorder_pid_message', '아이디가 존재하지 않습니다.');
				return;
			}
		}
	}
/**
 * @brief svorder.view.php::dispSvorderOrderDetail()에서 호출
 * svorder.admin.view.php::dispSvorderAdminOrderDetail()에서 호출
 */
	public function getPaymentMethodLabel($sCode)
	{
		$aPaymentMethod = array_flip($this->_g_aPaymentMethod);
		//$aPaymentMethod = array(
		//	'CC'=>'credit_card',
		//	'BT'=>'bank_transfer',
		//	'IB'=>'internet_banking',
		//	'VA'=>'virtual_account',
		//	'MP'=>'mobile_phone',
		//	'PP'=>'paypal'
		//);
		static $bFlag = FALSE;
		$aTranslation = Context::getLang('payment_method');
		if($bFlag) 
			return $aPaymentMethod;
		foreach($aPaymentMethod as $key => $val)
		{
			if ($aTranslation[$key])
				$aPaymentMethod[$key] = $aTranslation[$key];
		}
		$bFlag = TRUE;
		return $aPaymentMethod;
	}
/**
 * @brief 
 */
	/*function getPaymentMethodName($code)
	{
		$payment_method = '신용카드';
		switch($code)
		{
			case 'CC':
				$payment_method = '신용카드';
				break;
			case 'BT':
				$payment_method = '무통장 입금';
				break;
			case 'IB':
				$payment_method = '실시간계좌이체';
				break;
			case 'VA':
				$payment_method = '가상계좌';
				break;
			case 'MP':
				$payment_method = '휴대폰 결제';
				break;
			case 'PP':
				$payment_method = '페이팔';
				break;
		}
		return $payment_method;
	}*/
/**
 * @brief
 */
	/*function getPaymentMethods($module_srl)
	{
debugPrint('getPaymentMethods');
debugPrint($module_srl);
		$oModuleModel = &getModel('module');
		$module_info = $oModuleModel->getModuleInfoByModuleSrl($module_srl);

		$method_list = array();
		$oPgModuleModel = &getModel($module_info->module);
		if(method_exists($oPgModuleModel, 'getPaymentMethods'))
			$method_list = $oPgModuleModel->getPaymentMethods($module_srl);

		return $method_list;
	}*/
	/*function getSvpgSalesData()
	{
		$tran_date = Context::get('tran_date');
		$args->start_date = $tran_date . '000000';
		$args->end_date = $tran_date . '235959';
		$output = executeQueryArray('svpg.getSalesData', $args);
		if (!$output->toBool()) return $output;
		$list = array();
		foreach ($output->data as $no => $val)
		{
			$obj = new StdClass();
			$obj->tran_date = $val->regdate;
			$obj->item = $val->order_title;
			$obj->customer = $val->p_name;
			if ($val->company_name)
				$obj->customer . '[' . $val->p_email_address . ']';
			$obj->amount = $val->payment_amount;
			$obj->tax = 0; 
			switch ($val->payment_method)
			{
				case 'CC': // card
					$obj->paymethod = '3';
					break;
				case 'BT': // direct banking
					$obj->paymethod = '4';
					break;
				case 'IB': // internet banking
					$obj->paymethod = '2';
					break;
				case 'VA': // internet banking
					$obj->paymethod = '5';
					break;
				case 'MP': // internet banking
					$obj->paymethod = '1';
					break;
				case 'PP': // paypal
					$obj->paymethod = '6';
					break;
			}
			switch ($val->payment_method) 
			{
				case 'CC':
					// card
					$method = 'B';
					break;
				default:
					$method = 'C';
			}
			$obj->method = $method;
			$obj->taxinvoice_id = '';
			$list[] = $obj;
		}
		$this->add('list', $list);
	}*/
}
/* End of file svpg.model.php */
/* Location: ./modules/svpg/svpg.model.php */