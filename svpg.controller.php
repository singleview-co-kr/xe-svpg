<?php
/**
 * @class  svpg System Controller
 * @author singleview(root@singleview.co.kr)
 * @brief  svpg Page Controller
 **/
class svpgController extends svpg
{
/**
 * @brief initialize this module.
 */
	public function init()
	{
	}
/**
 * @breif 주문서를 표시할 때 주문서 내용에 이상이 없는지 점검
 * procSvpgPrecheckOrder 로 이름 변경 예정
 */
	public function procSvpgReviewOrder()
	{
		$nModuleSrl = Context::get('module_srl');
		if (!$nModuleSrl) 
			return new BaseObject(-1, 'no module_srl');
		$nSvpgModuleSrl = Context::get('svpg_module_srl');
		if (!$nSvpgModuleSrl) 
			return new BaseObject(-1, 'no svpg_module_srl');
		$nPluginSrl = Context::get('plugin_srl');
		if (!$nPluginSrl) 
			return new BaseObject(-1, 'no plugin_srl');

		$args = Context::getRequestVars();
		$oSvorderController = &getController('svorder');
		$output = $oSvorderController->precheckOrder($args);
		if (!$output->toBool())
			return $output;
		
		$nOrderSrl = $output->get('nOrderSrl');
		$args->order_srl = $nOrderSrl;
		// to set final paying amnt for PG
		$args->price = $output->get('nTotalPriceForPg');
		// to set final payer info for PG
		$args->purchaser_cellphone = $output->get('sPurchaserCellphone');

		$args->svpg_module_srl = $nSvpgModuleSrl;
		$args->plugin_srl = $nPluginSrl;
		$oModuleModel = &getModel('module');
		$oModuleInfo = $oModuleModel->getModuleInfoByModuleSrl($nSvpgModuleSrl);
		$args->plugin_name = $oModuleInfo->plugin_name;
		
		// 주문 절차 완료 후 svcart의 관련 품목을 비활성화하기 위해 svorder_srl을 먼저 기록함
		$oSvcartController = &getController('svcart');
		$output = $oSvcartController->setEstiOrderSrl($args);
		if (!$output->toBool())
			return $output;
// $_SESSION 제거하고 디비에 필요 변수를 임시 저장해야 함
		$_SESSION['module_srl'] = $nModuleSrl;
		$_SESSION['svpg_module_srl'] = $nSvpgModuleSrl;
		$_SESSION['order_srl'] = $nOrderSrl; 
		$_SESSION['svpg_target_module'] = $args->target_module;
		$_SESSION['xe_mid'] = Context::get('xe_mid');

		$oSvpgModel = &getModel('svpg');
		$oPlugin = $oSvpgModel->getPlugin($nPluginSrl);
		$oReviewOutput = $oPlugin->processReview($args);
		if (!$oReviewOutput->toBool()) 
			return $oReviewOutput;

		if ($oReviewOutput->get('return_url'))
		{
			$this->add('return_url', $oReviewOutput->get('return_url'));
			$this->setRedirectUrl($oReviewOutput->get('return_url'));
		}
		//if ($oReviewOutput->get('return_url'))
		//	$this->setRedirectUrl($oReviewOutput->get('return_url'));
		if ($oReviewOutput->get('tpl_data'))
			$this->add('tpl', $oReviewOutput->get('tpl_data'));
		$this->add('order_srl', $nOrderSrl);
	}
/**
 * @breif 결제 완료를 위해 호출
 * procSvpgCompletePgProcess 로 이름 변경 예정
 */
	public function procSvpgDoPayment()
	{
		$p_user_id = Context::get('purchaser_name');
		$p_name = Context::get('purchaser_name');
		$p_email_address = Context::get('email_address');
		
		$module_srl = $_SESSION['module_srl']; // svorder module_srl
		if(!$module_srl)
			$module_srl = Context::get('module_srl');

		$svpg_module_srl = $_SESSION['svpg_module_srl'];
		if(!$svpg_module_srl)
			$svpg_module_srl = Context::get('svpg_module_srl');

		$order_srl = $_SESSION['order_srl'];
		if(!$order_srl)
			$order_srl = Context::get('order_srl');
		if (!$order_srl)
			return new BaseObject(-1, 'msg_invalid_request');

		$target_module = $_SESSION['svpg_target_module'];
		if(!$target_module)
			$target_module = Context::get('svpg_target_module');
		if (!$svpg_module_srl)
			return new BaseObject(-1, 'msg_invalid_request');
		
		$plugin_srl = Context::get('plugin_srl');
		$nSvcartModuleSrl = (int)Context::get('module_srl');
		$oModuleModel = &getModel('module');
		$oModuleInfo = $oModuleModel->getModuleInfoByModuleSrl($nSvcartModuleSrl);
		$mid = $oModuleInfo->mid;

		$oSvpgModel = &getModel('svpg');
		$plugin = $oSvpgModel->getPlugin($plugin_srl);
		$obj = $this->_setParams( $plugin->plugin_info->plugin );
		//if($obj->manorder_pid) 
		//	$manorder_pid = $obj->manorder_pid; // 결제대행 유저 아이디.
		$obj->module_srl = $module_srl;
		$obj->svpg_module_srl = $svpg_module_srl;
		$obj->plugin_srl = $plugin->plugin_info->plugin_srl;
		$obj->plugin_name = $plugin->plugin_info->plugin_title;
		$obj->order_srl = $order_srl;
		$obj->xe_mid = $mid;

		$pp_ret = $plugin->processPayment($obj);
		if(!$pp_ret->toBool())
		{
			$this->setError(-1);
			$this->setMessage($pp_ret->result_message);
			// save error transaction info begin.
			$args->result_code = $pp_ret->get('result_code');
			$args->result_message = $pp_ret->get('result_message');
			$args->pg_tid = $pp_ret->get('pg_tid');
			$args->state = $pp_ret->get('state');
			$extra_vars = $pp_ret->getVariables();
			unset($extra_vars['state']);
			unset($extra_vars['payment_method']);
			unset($extra_vars['payment_amount']);
			$args->extra_vars = serialize($extra_vars);
			$args->target_module = $target_module;
			$args->svpg_module_srl = $svpg_module_srl;
			$args->module_srl = $module_srl;
			$args->plugin_srl = $plugin->plugin_info->plugin_srl;
			$args->order_srl = $order_srl;
			$args->payment_method = $pp_ret->get('payment_method');
			$args->payment_amount = $pp_ret->get('payment_amount');
			
			$output = executeQuery('svpg.insertTransactionErr',$args); //$output = $this->logTransaction($args);
			// save error transaction info end.
			return new BaseObject(-1, 'msg_error_occured_while_payment');
		}
		
		$nState = $pp_ret->get('state');
		// check state
		if ($nState == '3') // failure
		{
			$this->setError(-1);
			$this->setMessage($args->result_message);
		}

		// save success transaction info begin.
        $args = new stdClass();
		$args->result_code = $pp_ret->get('result_code');
		$args->result_message = $pp_ret->get('result_message');
		$args->pg_tid = $pp_ret->get('pg_tid');
		$args->state = $nState;
		$extra_vars = $pp_ret->getVariables();
		unset($extra_vars['state']);
		unset($extra_vars['payment_method']);
		unset($extra_vars['payment_amount']);
		$args->extra_vars = serialize($extra_vars);
		$args->target_module = $target_module;
		$args->svpg_module_srl = $svpg_module_srl;
		$args->module_srl = $module_srl;
		$args->plugin_srl = $plugin->plugin_info->plugin_srl;
		$args->order_srl = $order_srl;
		$args->order_title = $obj->svpg_order_title;
		$args->payment_method = $pp_ret->get('payment_method');
		$args->payment_amount = $pp_ret->get('payment_amount');
		$logged_info = Context::get('logged_info');
		if($logged_info)
		{
			$args->p_member_srl = $logged_info->member_srl;
			$args->p_user_id = $logged_info->user_id;
			$args->p_name = $logged_info->nick_name;
			$args->p_email_address = $logged_info->email_address;
		}
		else
		{
			$args->p_member_srl = 0;
			$args->p_user_id = $p_user_id;
			$args->p_name = $p_name;
			$args->p_email_address = $p_email_address;
		}
		$output = $this->logTransaction($args);
		if(!$output->toBool())
			return $output;
		// save success transaction info end.

		//if($manorder_pid)
		//{
		//	$args->user_id = $manorder_pid;
///// member 모듈 메소드 사용해야 함
		//	$output = executeQuery('member.getMemberInfo', $args);
		//	$args->p_member_srl = $output->data->member_srl;
		//	$args->p_user_id = $output->data->user_id;
		//	$args->p_name = $output->data->nick_name;
		//	$args->p_email_address = $output->data->email_address;
		//}
		//if(!$manorder_pid && !$logged_info)
		//{
		//	$args->p_member_srl = 0;
		//	$args->p_user_id = $p_user_id;
		//	$args->p_name = $p_name;
		//	$args->p_email_address = $p_email_address;
		//}

		// after
		$args->use_escrow = $pp_ret->get('vact_use_escrow');
		$args->extra_vars = $extra_vars;
		$args->calling_method = 'svpg::procSvpgDoPayment()';
		$oSvorderController = &getController('svorder');
		$output = $oSvorderController->completePgProcess($args);
		if(!$output->toBool())
			return $output;

		$oSvcartController = &getController('svcart');
		$output = $oSvcartController->deactivateCart($args);
		if(!$output->toBool())
			return $output;
		//$return_url = $args->return_url;
		//if (!$return_url)
		//	$return_url = Context::get('return_url');
		
		$sReturnUrl = getNotEncodedUrl('','act','dispSvorderOrderComplete','order_srl',$order_srl,'mid',$mid);
		if(!in_array(Context::getRequestMethod(),array('XMLRPC','JSON')))  // xpay_smart에서 작동
			$this->setRedirectUrl($sReturnUrl);
		else // xpay에서 작동
		{
			$this->add('return_url', $sReturnUrl);
			$this->add('order_srl', $order_srl);
		}
	}
/**
 * @brief svorder/ext_class/npay/npay_order.class.php::_insertNpayOrder()에서도 호출
 */
	public function logTransaction($oArgs)
	{
		return executeQuery('svpg.insertTransaction',$oArgs);
	}
/**
 * @brief this will be called by PG server for virtual account payment
 * Reporting URL   http://mydomain.name/?module=svpg&act=procSvpgReport&pg=inipay5
 * http://127.0.0.1/index.php?module=svpg&act=procSvpgReport&pg=xpay_smart&LGD_OID=52271&LGD_TID=balan2017052222391237480&LGD_CASFLAG=I&LGD_CASTAMOUNT=39000
 */
	public function procSvpgReport()
	{
//debugPrint( 'procSvpgReport');
//debugPrint( $_SERVER[REQUEST_METHOD]);
		$oSvpgModel = &getModel('svpg');
		$plugin = $oSvpgModel->getPluginByName(Context::get('pg'));
		$report = $plugin->getReport();
		$transaction = $oSvpgModel->getTransactionByOrderSrl($report->order_srl);

		$pr_ret = $plugin->processReport($transaction);
		$sPgRstCode = $pr_ret->getMessage();
		if( !defined(svorder::ORDER_STATE_ON_DEPOSIT) )
			getClass('svorder');

		if( $pr_ret->toBool() ) 
		{
			switch( $sPgRstCode )
			{
				case 'SVPG_VAC': // virutal_account_confirmed
					$sRespCode = 'OK';
					break;
				case 'SVPG_RC': // receive_confirmed
					$sRespCode = $this->_updateTransaction($transaction, svorder::ORDER_STATE_PAID);
					break;
				case 'SVPG_CC': // cancel_confirmed
					$sRespCode = $this->_updateTransaction($transaction, svorder::ORDER_STATE_ON_DEPOSIT);
					break;
			}
		}
		else
		{
			switch( $sPgRstCode )
			{
				case 'SVPG_AME': // amount_mismatch_error
				case 'SVPG_UE': // unknown error
				default:
					$sRespCode = 'ERR';
					break;
			}
		}
		Context::setResponseMethod('JSON');
		echo $sRespCode;
		//exit(0);
	}
/**
 * @brief this function will be removed in the future
 */
	public function procSvpgExtra1()
	{
		$oSvpgModel = &getModel('svpg');
		$plugin = $oSvpgModel->getPlugin(Context::get('plugin_srl'));
		return $plugin->procExtra1();
	}
/**
 * @brief this function will be removed in the future
 */
	public function procSvpgExtra2()
	{
		$oSvpgModel = &getModel('svpg');
		$plugin = $oSvpgModel->getPlugin(Context::get('plugin_srl'));
		return $plugin->procExtra2();
	}
/**
 * @brief this function will be removed in the future
 */
	public function procSvpgExtra3()
	{
		$oSvpgModel = &getModel('svpg');
		$plugin = $oSvpgModel->getPlugin(Context::get('plugin_srl'));
		return $plugin->procExtra3();
	}
/**
 * @brief this function will be removed in the future
 */
	public function procSvpgEscrowDelivery()
	{
		$oSvpgModel = &getModel('svpg');
		$plugin = $oSvpgModel->getPlugin(Context::get('plugin_srl'));
		$escrow_output = $plugin->procEscrowDelivery();
		$output = ModuleHandler::triggerCall('svpg.escrowDelivery', 'after', $escrow_output);
		if(!$escrow_output->toBool())
			return $escrow_output;
		return $output;
	}
/**
 * @brief this function will be removed in the future
 */
	public function procSvpgEscrowConfirm()
	{
		$oSvpgModel = &getModel('svpg');
		$plugin = $oSvpgModel->getPlugin(Context::get('plugin_srl'));
		$escrow_output = $plugin->procEscrowConfirm();
		$output = ModuleHandler::triggerCall('svpg.escrowConfirm', 'after', $escrow_output);
		if(!$escrow_output->toBool())
			return $escrow_output;
		return $output;
	}
/**
 * @brief this function will be removed in the future
 */
	public function procSvpgEscrowDenyConfirm()
	{
		$oSvpgModel = &getModel('svpg');
		$plugin = $oSvpgModel->getPlugin(Context::get('plugin_srl'));
		$escrow_output = $plugin->procEscrowDenyConfirm();
		$output = ModuleHandler::triggerCall('svpg.escrowDenyConfirm', 'after', $escrow_output);
		if(!$escrow_output->toBool()) return $escrow_output;
		return $output;
	}
/**
 * @breif 
 */
	private function _setParams( $sPluginName )
	{
		if( $sPluginName == 'xpay_smart' )
		{
			$oTmp = Context::getRequestVars();
			if( $oTmp->LGD_RESPCODE == '0000')
			{
				$oTmpSession = $_SESSION['svpg_http_vars'];
				$oTmp = Context::getRequestVars();

				foreach( $oTmpSession as $key => $val )
				{
					$oTmp->$key = $val;
					Context::set( $key, $val );
				}
				return $oTmp;
			}
			else
			{
				//echo "LGD_RESPCODE:" + $LGD_RESPCODE + " ,LGD_RESPMSG:" + $LGD_RESPMSG; //인증 실패에 대한 처리 로직 추가
				return false;
			}
		}
		else
			return Context::getRequestVars();
	}
/**
 * @brief not used for now
 */
	private function _updateTransaction($oTr, $nOrderState)
	{
		$oTr->state = $nOrderState;
		$output = executeQuery('svpg.getTransactionByOrderState',$oTr);
		if (!$output->toBool())
			return 'failure:invalid transaction';
		//if( count($output->data) == 0 ) // 기존 상태와 다를 때만 처리
		{
			$output = executeQuery('svpg.updateTransaction',$oTr);
			if (!$output->toBool())
				return 'failure:transaction update';
			$oArgs->order_srl = $oTr->order_srl;
			$oArgs->state = $oTr->state;
			$oArgs->calling_method = 'svpg::procSvpgReport()';
			$oSvorderController = &getController('svorder');
			$output = $oSvorderController->completePgProcess($oArgs);
			if(!$output->toBool())
				return $output;
		}
		return 'OK';
	}
/**
 * @brief update the state of payments -> 용도 불명
 */
	/*function procSvpgUpdateState()
	{
		if(!Context::get('transaction_srl') || !Context::get('state')) 
			return;
		else
		{
			$args->transaction_srl = Context::get('transaction_srl');
			$args->state = Context::get('state');
			$output = executeQuery('svpg.updateTransaction', $args);
			if(!$output->toBool())
				return $output;
		}
	}*/
}
/* End of file svpg.controller.php */
/* Location: ./modules/svpg/svpg.controller.php */