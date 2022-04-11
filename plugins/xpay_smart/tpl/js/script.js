/*
* iframe으로 결제창을 호출하시기를 원하시면 iframe으로 설정 (변수명 수정 불가)
*/
var LGD_window_type = 'submit';
var frm = document.getElementById('LGD_PAYINFO');
/*
* 수정불가
*/
function launchCrossPlatform()
{
	copy_form(join_form, 'LGD_PAYINFO');
	procFilter(document.getElementById('LGD_PAYINFO'), submit_xpay_smart_review);
}

function completeXpaySmartReviewOrder(ret_obj) 
{
	var frm = document.getElementById('LGD_PAYINFO');
	var tpl = ret_obj.tpl.replace(/<enter>/g, '\n');
	jQuery('#xpayExtends').html(tpl);
	copy_form(join_form, 'LGD_PAYINFO');
	jQuery(frm).remove("input[name='module']").remove("input[name='act']").remove("input[name='mid']");
	lgdwin = open_paymentwindow(frm, cst_platform, LGD_window_type);
}

(function($) {
	jQuery(function($) {
		$('input[name=payment_method]','#LGD_PAYINFO').click(function() {
			var paymethod = $(this).val();
			switch(paymethod) {
				case 'CC':
					$('input[name=LGD_CUSTOM_FIRSTPAY]').val('SC0010');
					break;
				case 'IB':
					$('input[name=LGD_CUSTOM_FIRSTPAY]').val('SC0030');
					break;
				case 'VA':
					$('input[name=LGD_CUSTOM_FIRSTPAY]').val('SC0040');
					break;
				case 'MP':
					$('input[name=LGD_CUSTOM_FIRSTPAY]').val('SC0060');
					break;
			}

			var method = $(this).val();
			$('.payment_info','#LGD_PAYINFO').hide();
			$('#pm_'+method).show();
		});
	});
}) (jQuery);