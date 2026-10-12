document.addEventListener('DOMContentLoaded', function () {
	document.querySelectorAll('input[type="password"]').forEach(function (input) {
		if (input.dataset.visibilityControl === 'ready') return;
		if (input.parentElement && input.parentElement.querySelector('.password-toggle')) {
			input.dataset.visibilityControl = 'ready';
			return;
		}
		input.dataset.visibilityControl = 'ready';

		const wrapper = document.createElement('span');
		wrapper.style.position = 'relative';
		wrapper.style.display = 'block';
		wrapper.style.width = '100%';
		input.parentNode.insertBefore(wrapper, input);
		wrapper.appendChild(input);

		const button = document.createElement('button');
		button.type = 'button';
	const eyeIcon = '<svg viewBox="0 0 24 24" width="19" height="19" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2.2 12s3.5-6.5 9.8-6.5 9.8 6.5 9.8 6.5-3.5 6.5-9.8 6.5S2.2 12 2.2 12Z"/><circle cx="12" cy="12" r="3"/></svg>';
	const eyeOffIcon = '<svg viewBox="0 0 24 24" width="19" height="19" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m3 3 18 18M10.6 5.7A10.9 10.9 0 0 1 12 5.5c6.3 0 9.8 6.5 9.8 6.5a17 17 0 0 1-3.1 3.8M6.2 6.3C3.6 8 2.2 12 2.2 12s3.5 6.5 9.8 6.5a10 10 0 0 0 4-.8"/><path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"/></svg>';
		button.innerHTML = eyeIcon;
		button.setAttribute('aria-label', 'Show password');
		button.title = 'Show password';
		button.setAttribute('aria-pressed', 'false');
		button.style.position = 'absolute';
		button.style.top = '50%';
		button.style.right = '9px';
		button.style.transform = 'translateY(-50%)';
		button.style.border = '0';
		button.style.background = 'transparent';
		button.style.color = '#526982';
		button.style.padding = '3px';
		button.style.font = 'inherit';
		button.style.fontSize = '0.75rem';
		button.style.fontWeight = '700';
		button.style.cursor = 'pointer';
		button.addEventListener('mousedown', function (event) {
			event.preventDefault();
		});
		button.addEventListener('click', function () {
			const showing = input.type === 'password';
			input.type = showing ? 'text' : 'password';
			button.innerHTML = showing ? eyeOffIcon : eyeIcon;
			button.setAttribute('aria-label', showing ? 'Hide password' : 'Show password');
			button.title = showing ? 'Hide password' : 'Show password';
			button.setAttribute('aria-pressed', showing ? 'true' : 'false');
			input.focus();
		});
		wrapper.appendChild(button);
		input.style.paddingRight = '3.8rem';
	});
});
