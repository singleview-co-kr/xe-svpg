<?php
class cash extends svpgPlugin
{
	var $plugin = "cash";
	var $plugin_srl;
	var $inicis_id;
	var $inicis_pass;
	var $site_url;
	var $logo_image;
	var $skin;

	function cash()
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
		if (!$this->plugin_info->account_title) $this->plugin_info->account_title = '무통장입금';
		Context::set('plugin_info', $this->plugin_info);
	}

	function getFormData($args)
	{
		if (!$args->price) return new BaseObject(0,'No input of price');
		$oTemplate = &TemplateHandler::getInstance();
		$tpl_path = _XE_PATH_."modules/svpg/plugins/cash/tpl";
		$tpl_file = 'formdata.html';
		Context::set('module_srl', $args->module_srl);
		Context::set('svpg_module_srl', $args->svpg_module_srl);
		Context::set('plugin_srl', $this->plugin_info->plugin_srl);
		Context::set('item_name', $args->item_name);
		Context::set('purchaser_name', $args->purchaser_name);
		Context::set('purchaser_email', $args->purchaser_email);
		Context::set('purchaser_telnum', $args->purchaser_telnum);
		Context::set('script_call_before_submit', $args->script_call_before_submit);
		Context::set('join_form', $args->join_form);

		$account_list = array();
		if($this->plugin_info->bank_name && $this->plugin_info->account_number && $this->plugin_info->account_holder)
		{
			$obj = new StdClass();
			$obj->bank_name = $this->plugin_info->bank_name;
			$obj->account_number = $this->plugin_info->account_number;
			$obj->account_holder = $this->plugin_info->account_holder;
			$account_list[0] = $obj;
		}
		for($i = 1; $i < 6; $i++)
		{
			$bn = 'bank_name_x'.$i;
			$an = 'account_number_x'.$i;
			$ah = 'account_holder_x'.$i;
			if(isset($this->plugin_info->{$bn}))
			{

				$obj = new StdClass();
				$obj->bank_name = $this->plugin_info->{$bn};
				$obj->account_number = $this->plugin_info->{$an};
			   	$obj->account_holder = $this->plugin_info->{$ah};
				$account_list[$i] = $obj;
			}
		}
		Context::set('account_list', $account_list);

		$html = $oTemplate->compile($tpl_path, $tpl_file);
		$output = new BaseObject();
		$output->data = $html;
		return $output;
	}

	function processReview($args)
	{
		//if( $args->delivfee_inadvance == 'N' )
		//	$args->price -= $args->delivery_fee;

		Context::set('price', $args->price);
		$oTemplate = &TemplateHandler::getInstance();
		$tpl_path = _XE_PATH_."modules/svpg/plugins/cash/tpl";
		$tpl_file = 'review.html';
		$tpl_data = $oTemplate->compile($tpl_path, $tpl_file);

		$output = new BaseObject();
		$output->add('tpl_data', $tpl_data);
		return $output;
	}

	function processPayment($args)
	{
		$x = Context::get('select_account');
		$obj = new StdClass();
		if($x === '0')
		{
			$obj->bank_name = $this->plugin_info->bank_name;
			$obj->account_number = $this->plugin_info->account_number;
			$obj->account_holder = $this->plugin_info->account_holder;
		}
		else
		{
			$bn = 'bank_name_x'.$x;
			$an = 'account_number_x'.$x;
			$ah = 'account_holder_x'.$x;
			$obj->bank_name = $this->plugin_info->{$bn};
			$obj->account_number = $this->plugin_info->{$an};
			$obj->account_holder = $this->plugin_info->{$ah};
		}

		$output = new BaseObject();
		$output->add('state', '1'); // not completed
		$output->add('payment_method', 'BT');
		$output->add('payment_amount', $args->price);
		$output->add('result_code', '0');
		$output->add('result_message', 'success');
		$output->add('pg_tid', $this->keygen());
		$output->add('vact_bankname', $obj->bank_name);
		$output->add('vact_num', $obj->account_number);
		$output->add('vact_name', $obj->account_holder);
		$output->add('vact_inputname', $args->depositor_name);
		return $output;
	}

	function dispExtra1(&$svpgObj)
	{
		$svpgObj->setLayoutFile('default_layout');
		$vars = Context::getRequestVars();
		unset($vars->act);
		Context::set('request_vars', $vars);
		extract(get_object_vars($vars));

		$x = Context::get('select_account');
		if($x === '0')
		{
            $obj = new stdClass();
			$obj->bank_name = $this->plugin_info->bank_name;
			$obj->account_number = $this->plugin_info->account_number;
			$obj->account_holder = $this->plugin_info->account_holder;
			Context::set('account_info', $obj);
		}
		else
		{
			$bn = 'bank_name_x'.$x;
			$an = 'account_number_x'.$x;
			$ah = 'account_holder_x'.$x;
            $obj = new stdClass();
			$obj->bank_name = $this->plugin_info->{$bn};
			$obj->account_number = $this->plugin_info->{$an};
			$obj->account_holder = $this->plugin_info->{$ah};
			Context::set('account_info', $obj);
		}
		$oTemplate = &TemplateHandler::getInstance();
		$tpl_path = _XE_PATH_."modules/svpg/plugins/cash/tpl";
		$tpl_file = 'start.html';
		return $oTemplate->compile($tpl_path, $tpl_file);
	}

	/**
	 * @brief generate a key string.
	 * @return key string
	 **/
	function keygen()
	{
		$randval = rand(100000, 999999);
		$usec = explode(" ", microtime());
		$str_usec = str_replace(".", "", strval($usec[0]));
		$str_usec = substr($str_usec, 0, 6);
		return date("YmdHis") . $str_usec . $randval;
	}
}
/* End of file svpg.view.php */
/* Location: ./modules/svpg/svpg.view.php */
