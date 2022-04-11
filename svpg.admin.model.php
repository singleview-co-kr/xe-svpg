<?php
/**
 * @class  svpgAdminModel
 * @author singleview(root@singleview.co.kr)
 * @brief  svpg admin model 
 **/
class svpgAdminModel extends svpg
{
/**
 * @brief 
 */
	function init()
	{
	}
/**
 * @brief ./svpg.model.php::getTransactionByOrderSrl()과 통일성 유지
 */
	function getTransactionByOrderSrl($order_srl)
	{
		$args->order_srl = $order_srl;
		$args->state = array('1','2');
		$output = executeQuery('svpg.getTransactionByOrderSrl',$args);
		$payment_info = $output->data;
		if($payment_info)
		{
			$extra_vars = unserialize($payment_info->extra_vars);
			if($extra_vars) 
			{
				foreach ($extra_vars as $key=>$val) 
					$payment_info->{$key} = $val;
			}
		}
		return $payment_info;
	}
/**
 * @brief (this function will be removed in the future)
 */
	function getPluginInfo($plugin_srl)
	{
		// 일단 DB에서 정보를 가져옴
		$args->plugin_srl = $plugin_srl;
		$output = executeQuery('svpg.getPluginInfo', $args);
		if(!$output->data) 
			return;
		
		// plugin, extra_vars를 정리한 후 xml 파일 정보를 정리해서 return
		if($output->data->extra_vars)
		{
			$extra_vars = unserialize($output->data->extra_vars);
			if($extra_vars) 
			{
				foreach ($extra_vars as $key=>$val) 
					$output->data->{$key} = $val;
			}
			unset( $output->data->extra_vars );
		}
		return $output->data;
	}
/**
 * @brief 
 */	
	function getSvpgAdminDeletePlugin()
	{
		$args->plugin_srl = Context::get('plugin_srl');
		$output = executeQuery('svpg.getPluginInfo', $args);
		if($output->toBool() && $output->data)
		{
			$plugin_info = $output->data;
			Context::set('plugin_info', $output->data);
		}
		$oTemplate = &TemplateHandler::getInstance();
		$tpl = $oTemplate->compile($this->module_path.'tpl', 'form_delete_plugin');
		$this->add('tpl', str_replace("\n"," ",$tpl));
	}
/**
 * @brief 
 */
	function getSvpgAdminDeleteModInst()
	{
		$oModuleModel = &getModel('module');
		$module_srl = Context::get('module_srl');
		$module_info = $oModuleModel->getModuleInfoByModuleSrl($module_srl);
		Context::set('module_info', $module_info);
		$oTemplate = &TemplateHandler::getInstance();
		$tpl = $oTemplate->compile($this->module_path.'tpl', 'form_delete_modinst');
		$this->add('tpl', str_replace("\n"," ",$tpl));
	}
}
/* End of file svpg.admin.model.php */
/* Location: ./modules/svpg/svpg.admin.model.php */