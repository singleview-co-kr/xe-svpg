<?php
/**
 * @class svpg
 * @author singleview(root@singleview.co.kr)
 * @brief svpg class
 **/
require_once(_XE_PATH_.'modules/svpg/svpg.view.php');
class svpgMobile extends svpgView 
{
	function init()
	{
		Context::set('admin_bar', 'false');
		Context::set('hide_trolley', 'true');
		$template_path = sprintf("%sm.skins/%s/",$this->module_path, $this->module_info->mskin);
		if(!is_dir($template_path)||!$this->module_info->mskin) 
		{
			$this->module_info->mskin = 'default';
			$template_path = sprintf("%sm.skins/%s/",$this->module_path, $this->module_info->mskin);
		}
		$this->setTemplatePath($template_path);
		Context::addJsFile('common/js/jquery.min.js');
		Context::addJsFile('common/js/xe.min.js');
	}
}
/* End of file svpg.mobile.php */
/* Location: ./modules/svpg/svpg.mobile.php */