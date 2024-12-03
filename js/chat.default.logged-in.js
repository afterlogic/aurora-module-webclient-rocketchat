if (window.top === window.self) {
	var notIframeitervalId = setInterval(() => {
		var html = window.document.getElementsByTagName('html')[0];
		if (html != undefined) {
			html.classList.add('not-iframe')
			clearInterval(notIframeitervalId)
		}
	}, 500);
}

window.addEventListener('message', function(oEvent) {
	if (oEvent && oEvent.data && oEvent.data.externalCommand === 'set-aurora-theme') {
		var itervalId = setInterval(() => {
			var html = window.document.getElementsByTagName('html')[0];
			if (html != undefined) {
				html.classList.add('aurora-theme-' + oEvent.data.theme);
				clearInterval(itervalId)
			}
		}, 500);
	}
});
