function completeInsertPlugin(ret_obj) {
	alert(ret_obj['message']);
	location.replace( current_url.setQuery('act','dispSvpgAdminUpdatePlugin').setQuery('plugin_srl',ret_obj['plugin_srl']) );
}

function completeInsertSvpg(ret_obj) {
	alert(ret_obj['message']);
	location.replace( current_url.setQuery('act','dispSvpgAdminInsertSvpg').setQuery('module_srl',ret_obj['module_srl']) );
}
