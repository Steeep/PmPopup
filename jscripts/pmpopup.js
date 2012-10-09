var mpPopup = {

	refreshInterval: 30,
	uID: 0,
	
	refresh: function()
	{
		setTimeout("mpPopup.refresh();", mpPopup.refreshInterval * 1000);
	}
};