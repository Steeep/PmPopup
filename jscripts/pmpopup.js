var mpPopup = {

	refreshInterval: 30,
	uID: 0,
	
	refresh: function()
	{
		setTimeout("mpPopup.refresh();", mpPopup.refreshInterval * 1000);
		if (typeof Ajax == 'object')
		{
			new Ajax.Request('xmlhttp.php?action=checku_mps&uid=' + mpPopup.uID,
			{
				method: 'get',
				onComplete: function(request) {
					ShoutBox.shoutsLoaded(request);
				}
			});
		}
	}
};