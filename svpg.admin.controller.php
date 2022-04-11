<?php
/**
 * @class svpgAdminController
 * @author singleview(root@singleview.co.kr)
 * @brief svpg admin controller
 **/
class svpgAdminController extends svpg
{
/**
 * @brief
 **/
	public function procSvpgAdminCancelSettlement($nOrderSrl,$sCancelReason)
	{
		//$logged_info = Context::get('logged_info');
		//if( $logged_info->is_admin != 'Y' && $logged_info->group_list[1] != '관리그룹' )
		//	return new BaseObject( -1, 'msg_no_proper_permission' );

		if( (int)$nOrderSrl == 0 )
			return new BaseObject( -1, 'msg_invalid_sv_tr_id' );

		$oSvpgAdminModel = &getAdminModel('svpg');
		$oTransaction = $oSvpgAdminModel->getTransactionByOrderSrl($nOrderSrl);
		if( strlen($oTransaction->pg_tid) == 0 )
			return new BaseObject( -1, 'msg_invalid_pg_tr_id' );

		// 지정한 pg_tid로 결제 성공 기록이 있으면 작업 거부
		$args->original_pg_tid = $oTransaction->pg_tid;
		$args->state = 2;
		$output = executeQuery('svpg.getCancelCompletedLogByTrId',$args);

		if (!$output->toBool()) 
			return $output;
		
		if( count( $output->data ) )
			return new BaseObject( -1, 'msg_already_cancelled_pg_tr_id' );

		unset($args);

		$nPaygatePluginSrl = $oTransaction->plugin_srl;
		$oPaygatePlugin = $oSvpgAdminModel->getPluginInfo($nPaygatePluginSrl);
		$oSvpgModel = &getModel('svpg');
		$plugin = $oSvpgModel->getPlugin((int)$oPaygatePlugin->plugin_srl);

		if( !method_exists($plugin,'processCancel') )
			return new BaseObject( -1, 'msg_plugin_not_support_cancel_transaction' );

		$pc_ret = $plugin->processCancel($oTransaction->pg_tid);
		if (!$pc_ret->toBool()) 
			return $pc_ret;
		
		// 취소 성공
		// 취소자 정보 ID IP timestamp, reason, responsecode 기록
		//$args->transaction_srl=getNextSequence();
		$args->original_transaction_srl=$oTransaction->transaction_srl;
		$args->original_pg_tid=$oTransaction->pg_tid;
		$args->svpg_module_srl=$oTransaction->svpg_module_srl;
		$args->module_srl=$oTransaction->module_srl;
		$args->plugin_srl=$oTransaction->plugin_srl;
		//$args->target_module=$oTransaction->target_module;
		$args->order_srl=$oTransaction->order_srl;
		$args->order_title=$oTransaction->order_title;
		$args->payment_method=$oTransaction->payment_method;
		// 취소이므로 원래 결제금액의 역수
		$args->payment_amount=-$oTransaction->payment_amount;
		$args->p_member_srl=$oTransaction->p_member_srl;
		$args->p_user_id=$oTransaction->p_user_id;
		$args->p_name=$oTransaction->p_name;
		$args->p_email_address=$oTransaction->p_email_address;
		$args->result_code=$pc_ret->get('result_code');
		$args->result_message=$pc_ret->get('result_message');
		// 1: not completed, 2: completed(success), 3: completed(failure)
		switch( $pc_ret->get('status_msg') )
		{
			case 'completed(success)':
				$args->state=2;
				break;
			case 'completed(failure)':
				$args->state=3;
				break;
			case 'not_completed':
				$args->state=1;
				break;
			default:
				$args->state=0;
				break;
		}
		$args->admin_member_srl = $logged_info->member_srl;
		$args->admin_user_id = $logged_info->user_id;
		$args->admin_email_address = $logged_info->email_address;
		$args->admin_name = $logged_info->user_name;
		$args->cancel_reason = $sCancelReason;
		$args->regdate = date('YmdHis');
		$output = executeQuery('svpg.insertCancelLog',$args);
		if(!$output->toBool()) 
			return $output;
		
		// 처리요청 order 레코드에 취소일자 기록하기 위해 반환
		$sPluginRstMsg = sprintf( "%s", $pc_ret->get('result_message') );
		$Rst = new BaseObject( 0, $sPluginRstMsg );
		$Rst->add('regdate', $args->regdate);
		return $Rst;
	}
/**
 * @brief insert module instance info.
 **/
	public function procSvpgAdminInsertSvpg()
	{
		// module 모듈의 model/controller 객체 생성
		$oModuleController = &getController('module');
		$oModuleModel = &getModel('module');
		// 게시판 모듈의 정보 설정
		$args = Context::getRequestVars();
		$args->module = 'svpg';
		$args->browser_title = 'svpg_'.date('Ymd');

		if( $args->forbid_settlement_after )
		{
			$oDate = DateTime::createFromFormat('YmdHis', $args->forbid_settlement_after);
			if( !$oDate )
				return new BaseObject(-1, 'msg_invalid_forbid_settlement_date');
		}
		// module_srl이 넘어오면 원 모듈이 있는지 확인
		if($args->module_srl) 
		{
			$module_info = $oModuleModel->getModuleInfoByModuleSrl($args->module_srl);
			if($module_info->module_srl != $args->module_srl) unset($args->module_srl);
		}
		// module_srl의 값에 따라 insert/update
		if(!$args->module_srl) 
		{
			$output = $oModuleController->insertModule($args);
			$msg_code = 'success_registed';
		}
		else 
		{
			$output = $oModuleController->updateModule($args);
			$msg_code = 'success_updated';
		}

		if(!$output->toBool()) 
			return $output;
		$this->add('module_srl',$output->get('module_srl'));
		$this->setMessage($msg_code);
		$this->setRedirectUrl(getNotencodedUrl('','module',Context::get('module'),'act','dispSvpgAdminInsertSvpg','module_srl',$output->get('module_srl')));
	}
/**
 * @brief delete module instance.
 */
	public function procSvpgAdminDeleteModInst()
	{
		$module_srl = Context::get('module_srl');
		// 원본을 구해온다
		$oModuleController = &getController('module');
		$output = $oModuleController->deleteModule($module_srl);
		if(!$output->toBool())
			return $output;
		$this->add('module','svpg');
		$this->add('page',Context::get('page'));
		$this->setMessage('success_deleted');
		$this->setRedirectUrl(getNotencodedUrl('','module',Context::get('module'),'act','dispSvpgAdminSvpgList'));
	}
/**
 * @brief insert plugin info. (it will be deleted in the future)
 */
	public function procSvpgAdminInsertPlugin()
	{
		$plugin_srl = getNextSequence();
		$args = new stdClass();
		$args->plugin_srl = $plugin_srl;
		$args->plugin = Context::get('plugin');
		$args->title = Context::get('title');
		$output = executeQuery("svpg.insertPlugin", $args);
		if(!$output->toBool())
			return $output;
		require_once(_XE_PATH_.'modules/svpg/svpg.plugin.php');
		require_once(_XE_PATH_.'modules/svpg/plugins/'.$args->plugin.'/'.$args->plugin.'.plugin.php');

		//$tmp_fn = create_function('', "return new {$args->plugin}();");
		$oPlugin = eval("return new {$args->plugin}();"); //$tmp_fn();
		if (@method_exists($oPlugin,'pluginInstall'))
			$oPlugin->pluginInstall($args);
		// 결과 리턴
		$this->add('plugin_srl', $plugin_srl);
	}
/**
 * @brief update plugin info. (it will be deleted in the future)
 */
	public function procSvpgAdminUpdatePlugin()
	{
		$oSvpgModel = &getModel('svpg');
		// module, act, layout_srl, layout, title을 제외하면 확장변수로 판단.. 좀 구리다..
		$extra_vars = Context::getRequestVars();
		unset($extra_vars->module);
		unset($extra_vars->act);
		unset($extra_vars->plugin_srl);
		unset($extra_vars->plugin);
		unset($extra_vars->title);
		$args = Context::gets('plugin_srl','title');
		$plugin_info = $oSvpgModel->getPluginInfo($args->plugin_srl);

		// extra_vars의 type이 image일 경우 별도 처리를 해줌
		if($plugin_info->extra_var) 
		{
			foreach($plugin_info->extra_var as $name => $vars) 
			{
				if($vars->type!='image')
					continue;

				$image_obj = $extra_vars->{$name};
				$extra_vars->{$name} = $plugin_info->extra_var->{$name}->value;
				// 삭제 요청에 대한 변수를 구함
				$del_var = $extra_vars->{"del_".$name};
				unset($extra_vars->{"del_".$name});
				// 삭제 요청이 있거나, 새로운 파일이 업로드 되면, 기존 파일 삭제
				if($del_var == 'Y' || $image_obj['tmp_name']) 
				{
					FileHandler::removeFile($extra_vars->{$name});
					$extra_vars->{$name} = '';
					if($del_var == 'Y' && !$image_obj['tmp_name'])
						continue;
				}

				// 정상적으로 업로드된 파일이 아니면 무시
				if(!$image_obj['tmp_name'] || !is_uploaded_file($image_obj['tmp_name'])) 
					continue;

				// 이미지 파일이 아니어도 무시 (swf는 패스~)
				if(!preg_match("/\.(jpg|jpeg|gif|png|swf|enc|pem)$/i", $image_obj['name']))
					continue;

				// 경로를 정해서 업로드
				if ($vars->location) // 이 부분 type=file로 변환해야 함 svauth 참조
				{
					$location = $this->_mergeKeywords($vars->location,$extra_vars);
					$path = sprintf("./files/svpg/%s/%s/",$args->plugin_srl,$location);
				}
				else
					$path = sprintf("./files/attach/images/%s/", $args->plugin_srl);

				// 디렉토리 생성
				if(!FileHandler::makeDir($path))
					continue;
				$filename = $path.$image_obj['name'];
				// 파일 이동
				if(!move_uploaded_file($image_obj['tmp_name'], $filename))
					continue;
				$extra_vars->{$name} = $filename;
			}
		}
		// DB에 입력하기 위한 변수 설정
		$args->extra_vars = serialize($extra_vars);
		$output = executeQuery('svpg.updatePlugin', $args);
		if(!$output->toBool()) 
			return $output;
		$this->setLayoutPath('./common/tpl');
		$this->setLayoutFile('default_layout.html');
		$this->setTemplatePath($this->module_path.'tpl');
		$this->setTemplateFile("top_refresh.html");
	}
/**
 * delete plugin info. (it will be deleted in the future)
 */
	public function procSvpgAdminDeletePlugin()
	{
		$plugin_srl = Context::get('plugin_srl');
		if (!$plugin_srl) 
			return new BaseObject(-1, 'msg_invalid_request');
		$args->plugin_srl = $plugin_srl;
		$output = executeQuery('svpg.deletePlugin',$args);
		if (!$output->toBool())
			return $output;

		FileHandler::removeDir(sprintf(_XE_PATH_."files/svpg/%s",$plugin_srl));
		$this->setMessage('success_deleted');
		if(!in_array(Context::getRequestMethod(),array('XMLRPC','JSON'))) 
		{
			$returnUrl = Context::get('success_return_url') ? Context::get('success_return_url') : getNotEncodedUrl('', 'module', Context::get('module'), 'act', 'dispSvpgAdminPluginList','module_srl',Context::get('module_srl'));
			$this->setRedirectUrl($returnUrl);
			return;
		}
	}
/**
 * @brief /svorder/ext_class/npay/npay_api.class.php::resetOrderInfo()에서 호출
 **/
	public function deleteTransctionInfoByOrderSrl($nOrderSrl)
	{
		$oArgs->order_srl = $nOrderSrl;
		return executeQuery('svpg.deleteTransactionByOrderSrl', $oArgs );
	}
/**
 * 폐기 예정
 * @brief inipay plugin 키파일 업로드 경로 설정
 **/
	private function _mergeKeywords($text, &$obj)
	{
		if (!is_object($obj))
			return $text;
		foreach ($obj as $key => $val) 
		{
			if (is_array($val))
				$val = join($val);
			if (is_string($key) && is_string($val)) 
			{
				if (substr($key,0,10)=='extra_vars')
					$val = str_replace('|@|', '-', $val);
				$text = preg_replace("/%" . preg_quote($key) . "%/", $val, $text);
			}
		}
		return $text;
	}
}
/* End of file svpg.admin.controller.php */
/* Location: ./modules/svpg/svpg.admin.controller.php */