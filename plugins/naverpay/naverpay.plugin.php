<?php
class naverpay extends SvpgPlugin 
{
	var $plugin_info;
	const _g_sPluginLogHome = 'files/svpg/naverpay';
/**
 * @brief
 */
	function pluginInstall($args) 
	{
		// mkdir
		//FileHandler::makeDir(_XE_PATH_.self::_g_sPluginLogHome.'/'.$args->plugin_srl.'/log');
		// copy files
		//FileHandler::copyFile(_XE_PATH_.'modules/svpg/plugins/naverpay/.htaccess', _XE_PATH_.self::_g_sPluginLogHome.'/'.$args->plugin_srl.'/.htaccess');
		//FileHandler::copyFile(_XE_PATH_.'modules/svpg/plugins/naverpay/readme.txt', _XE_PATH_.self::_g_sPluginLogHome.'/'.$args->plugin_srl.'/readme.txt');
	}
/**
 * @brief
 */
	function naverpay() 
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
			$this->plugin_info->{$key} = $val;
		foreach ($args->extra_var as $key=>$val)
			$this->plugin_info->{$key} = $val->value;
		Context::set('plugin_info', $this->plugin_info);
	}
/**
 * @brief
 */
	function getConfig()
	{
		$xpay_home = _XE_PATH_.self::_g_sPluginLogHome.'/'.$this->plugin_info->plugin_srl;//sprintf(_XE_PATH_."files/svpg/xpay/%s", $this->plugin_info->plugin_srl);
		$config = array();
		
		$config['auto_rollback'] = '1';
		$config['log_dir'] = $xpay_home.'/log';
		$config[(('test' == $this->plugin_info->cst_platform) ? 't' : '' ).$this->plugin_info->mert_id] = $this->plugin_info->mert_key;		
		$config['aux_url'] = 'http://xpayclient.lgdacom.net:7080/xpay/Gateway.do';
		return $config;
	}
/**
 * @brief
 */
	function getPaymethod($sPayMethod)
	{
		switch ($sPayMethod) 
		{
			case '신용카드':
				return $this->_g_aPaymentMethod['credit_card'];
			case '신용카드 간편결제':
				return $this->_g_aPaymentMethod['easy_credit_card'];
			case '실시간계좌이체':
				return $this->_g_aPaymentMethod['internet_banking'];
			case '계좌 간편결제':
				return $this->_g_aPaymentMethod['easy_account'];
			case '무통장입금':
				return $this->_g_aPaymentMethod['virtual_account'];
			case '휴대폰':
				return $this->_g_aPaymentMethod['mobile_phone'];
			case '휴대폰 간편결제':
				return $this->_g_aPaymentMethod['easy_mobile_phone'];
			case '포인트결제':
				return $this->_g_aPaymentMethod['naver_point'];
			default:
				return '  ';
		}
	}
}
/* End of file xpay.plugin.php */
/* Location: ./modules/svpg/plugins/xpay/xpay.plugin.php */