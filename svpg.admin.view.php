<?php
/**
 * @class svpgAdminView
 * @author singleview(root@singleview.co.kr)
 * @brief svpg admin view
 **/
class svpgAdminView extends svpg
{
/**
 * @brief initialize this module.
 */
	function init()
	{
		// module이 svshopmaster일때 관리자 레이아웃으로
		if(Context::get('module') == 'svshopmaster')
		{
			$sClassPath = _XE_PATH_ . 'modules/svshopmaster/svshopmaster.class.php';
			if(file_exists($sClassPath))
			{
				require_once($sClassPath);
				$oSvshopmaster = new svshopmaster;
				$oSvshopmaster->init($this);
			}
		}

		$template_path = sprintf("%stpl/",$this->module_path);
		$this->setTemplatePath($template_path);	
		// module model 객체 생성
		$oModuleModel = &getModel('module');

		// 모듈 카테고리 목록을 구함
		$module_category = $oModuleModel->getModuleCategories();
		Context::set('module_category', $module_category);

		// module_srl이 있으면 미리 체크하여 존재하는 모듈이면 module_info 세팅
		$module_srl = Context::get('module_srl');
		if(!$module_srl && $this->module_srl)
		{
			$module_srl = $this->module_srl;
			Context::set('module_srl', $module_srl);
		}

		$oModuleModel = &getModel('module');

		//if(Context::get('module')=='svshopmaster')
		//{
		//	$this->setLayoutPath('');
		//	$this->setLayoutFile('common_layout');
		//}

		// module_srl이 넘어오면 해당 모듈의 정보를 미리 구해 놓음
		if( $module_srl ) 
		{
			$module_info = $oModuleModel->getModuleInfoByModuleSrl($module_srl);
			if( !$module_info ) 
			{
				Context::set('module_srl','');
				$this->act = 'list';
			}
			else
			{
				ModuleModel::syncModuleToSite($module_info);
				$this->module_info = $module_info;
				Context::set('module_info',$module_info);
			}
		}
	}
/**
 * @brief list module instances.
 **/
	function dispSvpgAdminSvpgList()
	{
		// load svpg module instances
		$args = new stdClass();
		$args->sort_index = "module_srl";
		$args->page = Context::get('page');
		$args->list_count = 20;
		$args->page_count = 10;
		$args->s_module_category_srl = Context::get('module_category_srl');
		$output = executeQueryArray('svpg.getSvpgList', $args);
		ModuleModel::syncModuleToSite($output->data);
		// set variables for template
		Context::set('total_count', $output->total_count);
		Context::set('total_page', $output->total_page);
		Context::set('page', $output->page);
		Context::set('svpg_list', $output->data);
		Context::set('page_navigation', $output->page_navigation);
		// set template file
		$this->setTemplateFile('svpglist');
	}
/**
 * @brief module instance creation form
 */
	function dispSvpgAdminInsertSvpg()
	{
		$module_srl = Context::get('module_srl');
		if(!$module_srl && $this->module_srl)
		{
			$module_srl = $this->module_srl;
			Context::set('module_srl', $module_srl);
		}
		// module_srl이 넘어오면 해당 모듈의 정보를 미리 구해 놓음
		$oModuleModel = &getModel('module');
		if($module_srl)
		{
			$module_info = $oModuleModel->getModuleInfoByModuleSrl($module_srl);
			if(!$module_info)
				return new BaseObject(-1, 'msg_invalid_request');
			else
			{
				ModuleModel::syncModuleToSite($module_info);
				Context::set('module_info',$module_info);
			}
		}
		// 스킨 목록을 구해옴
		$skin_list = $oModuleModel->getSkins($this->module_path);
		Context::set('skin_list',$skin_list);
		$mskin_list = $oModuleModel->getSkins($this->module_path, "m.skins");
		Context::set('mskin_list', $mskin_list);
		// 레이아웃 목록을 구해옴
		$oLayoutModel = &getModel('layout');
		$layout_list = $oLayoutModel->getLayoutList();
		Context::set('layout_list', $layout_list);
		$mobile_layout_list = $oLayoutModel->getLayoutList(0,"M");
		Context::set('mlayout_list', $mobile_layout_list);
		// plugins
		$oSvpgModel = &getModel('svpg');
		$plugins = $oSvpgModel->getPluginList();
		Context::set('plugins', $plugins);
		$this->setTemplateFile('insertsvpg');
	}
/**
 * @brief list plugins.
 */
	function dispSvpgAdminPluginList()
	{
		$args = new stdClass();
		$args->page = Context::get('page');
		$output = executeQueryArray('svpg.getPluginList', $args);
		if (!$output->toBool())
			return $output;
		Context::set('plugins', $output->data);
		Context::set('total_count', $output->total_count);
		Context::set('total_page', $output->total_page);
		Context::set('page', $output->page);
		Context::set('page_navigation', $output->page_navigation);
		$this->setTemplateFile('pluginlist');
	}
/**
 * @brief plugin creation form.
 */
	function dispSvpgAdminInsertPlugin()
	{
		// plugins
		$oSvpgModel = &getModel('svpg');
		$plugins = $oSvpgModel->getPluginsXmlInfo();
		Context::set('plugins', $plugins);
		$this->setTemplateFile('insertplugin');
	}
/**
 * @brief plugin update form.
 */
	function dispSvpgAdminUpdatePlugin()
	{
		$oSvpgModel = &getModel('svpg');
		$plugin_srl = Context::get('plugin_srl');
		// plugin info
		$plugin_info = $oSvpgModel->getPluginInfo($plugin_srl);
		Context::set('plugin_info', $plugin_info);
		$this->setTemplateFile('updateplugin');
	}
/**
 * @brief list transactions
 */
	function dispSvpgAdminTransactions()
	{
		// transactions
		$args = new stdClass();
		$args->page = Context::get('page');
		$output = executeQueryArray('svpg.getTransactionList',$args);
		if(!$output->toBool()) 
			return $output;
		$list = $output->data;
		//ModuleHandler::triggerCall('svpg.getTransactionList', 'after', $list);
		foreach($list as $key=>$val)
		{
			if($val->target_module == 'svcart')
				$list[$key]->target_act = 'getSvcartAdminOrderDetails';
		}
		Context::set('list', $list);
		Context::set('total_count', $output->total_count);
		Context::set('total_page', $output->total_page);
		Context::set('page', $output->page);
		Context::set('page_navigation', $output->page_navigation);
		// module instances
		$output = executeQueryArray('svpg.getAllModInstList');
		$modinst_list = array();
		$list = $output->data;
		if(!is_array($list)) 
			$list = array();
		foreach($list as $key=>$modinfo)
			$modinst_list[$modinfo->module_srl] = $modinfo;
		Context::set('modinst_list',$modinst_list);
		$this->setTemplateFile('transactions');
	}
/**
 * @brief 스킨 정보 보여줌
 **/
	function dispSvpgAdminSkinInfo() 
	{
		// 공통 모듈 권한 설정 페이지 호출
		$oModuleAdminModel = &getAdminModel('module');
		$skin_content = $oModuleAdminModel->getModuleSkinHTML($this->module_info->module_srl);
		Context::set('skin_content', $skin_content);
		$this->setTemplateFile('skininfo');
	}
/**
 * @brief 스킨 정보 보여줌
 **/
	function dispSvpgAdminMobileSkinInfo() 
	{
		// 공통 모듈 권한 설정 페이지 호출
		$oModuleAdminModel = &getAdminModel('module');
		$skin_content = $oModuleAdminModel->getModuleMobileSkinHTML($this->module_info->module_srl);
		Context::set('skin_content', $skin_content);
		$this->setTemplateFile('skininfo');
	}
}
/* End of file svpg.admin.view.php */
/* Location: ./modules/svpg/svpg.admin.view.php */