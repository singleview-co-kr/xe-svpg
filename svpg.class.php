<?php
/**
 * @class svpg
 * @author singleview(root@singleview.co.kr)
 * @brief svpg class
 **/
//define('STATE_NOTCOMPLETED', '1');
//define('STATE_COMPLETED', '2');
//define('STATE_FAILURE', '3');
class svpg extends ModuleObject 
{
	//const CREDIT_CART = 'CC';
	//const REALTIME_INTERNET_BANKING = 'IB';
	//const VIRTUAL_ACCOUNT = 'VA';
	//const MOBILE_PAY = 'MP';
	//const EASY_CREDIT_CART = 'EC'; // naver pay 신용카드 간편결제
	//const EASY_ACCOUNT = 'EA'; // naver pay 계좌 간편결제
	//const EASY_MOBILE_PAY = 'EM'; // naver pay 휴대폰 간편결제
	//const NAVER_POINT = 'NP'; // naver pay 포인트결제
	protected $_g_aPaymentMethod = array(
			'credit_card'=>'CC', 
			'internet_banking'=>'IB',
			'bank_transfer'=>'BT',
			'virtual_account'=>'VA', 
			'mobile_phone'=>'MP', 
			'easy_credit_card'=>'EC', // naver pay 신용카드 간편결제
			'easy_account'=>'EA', // naver pay 계좌 간편결제
			'easy_mobile_phone'=>'EM', // naver pay 휴대폰 간편결제
			'naver_point'=>'NP', // naver pay 포인트결제
			'paypal'=>'PP'
			); 
/**
 * @brief module uninstall
 */
	function moduleInstall() 
	{
	}
/**
 * @breif check to see if update is necessary
 */
	function checkUpdate() 
	{
		return false;
	}
/**
 * @brief module uninstall
 */
	function moduleUninstall()
	{
	}
/**
 * @brief recompile the cache after module install or update
 */
	function recompileCache() 
	{
	}
}
/* End of file svpg.class.php */
/* Location: ./modules/svpg/svpg.class.php */