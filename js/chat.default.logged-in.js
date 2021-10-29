if (window.top === window.self) {
  $('html').addClass('not-iframe');
}

window.addEventListener('message', function(oEvent) {
	if (oEvent && oEvent.data && oEvent.data.externalCommand === 'set-aurora-theme') {
		$('html').addClass('aurora-theme-' + oEvent.data.theme);
	}
});
