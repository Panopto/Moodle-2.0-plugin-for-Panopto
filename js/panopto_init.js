M.local_panopto_tac = {
    Y : null,
    transaction : [],
    init : function(Y, courseid) {
		$('#panopto_ts_button').panoptoTac({
			"courseid": courseid
		});
    }
};
