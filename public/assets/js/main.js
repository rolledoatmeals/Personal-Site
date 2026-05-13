// Minimal site script: loader fade-out and smooth anchor scroll
(function(){
	function onReady(fn){
		if(document.readyState !== 'loading') return fn();
		document.addEventListener('DOMContentLoaded', fn);
	}

	onReady(function(){
		var loader = document.querySelector('.site-loader');
		window.addEventListener('load', function(){
			if(!loader) return;
			loader.classList.add('hidden');
			setTimeout(function(){
				if(loader && loader.parentNode) loader.parentNode.removeChild(loader);
			}, 500);
		});

		document.body.addEventListener('click', function(e){
			var a = e.target.closest('a');
			if(!a) return;
			var href = a.getAttribute('href');
			if(!href || href.charAt(0) !== '#') return;
			var target = document.querySelector(href);
			if(!target) return;
			e.preventDefault();
			target.scrollIntoView({behavior:'smooth', block:'start'});
			history.replaceState(null, '', href);
		});
	});
})();

