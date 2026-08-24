/**
 * Main Theme Asset Entrypoint
 * Handles sticky transparent-to-white header scroll transitions
 */

import './index.scss';

document.addEventListener('DOMContentLoaded', () => {
	const header = document.querySelector('.site-header');

	if (!header) {
		return;
	}

	const handleScroll = () => {
		if (window.scrollY > 40) {
			header.classList.add('is-scrolled');
		} else {
			header.classList.remove('is-scrolled');
		}
	};

	handleScroll();
	window.addEventListener('scroll', handleScroll, { passive: true });
});
