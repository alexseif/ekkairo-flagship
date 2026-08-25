/**
 * Main Theme Asset Entrypoint
 * Handles sticky header scroll dynamics and native FSE Query Loop Carousel transitions
 */

import './index.scss';

document.addEventListener('DOMContentLoaded', () => {
	// 1. Sticky Header Scroll Listener
	const header = document.querySelector('.site-header');
	if (header) {
		const handleScroll = () => {
			if (window.scrollY > 40) {
				header.classList.add('is-scrolled');
			} else {
				header.classList.remove('is-scrolled');
			}
		};
		handleScroll();
		window.addEventListener('scroll', handleScroll, { passive: true });
	}

	// 2. Native FSE Query Loop Hero Carousel Controller
	const carouselQueries = document.querySelectorAll('.is-style-news-carousel, .hero-slider-section');
	carouselQueries.forEach((carouselQuery) => {
		const container = carouselQuery.querySelector('.wp-block-post-template');
		if (container) {
			const slides = Array.from(container.children).filter((el) => el.nodeType === 1);
			if (slides.length > 1) {
				carouselQuery.classList.add('is-initialized-carousel');

				let currentIndex = 0;
				let autoPlayTimer = null;

				slides.forEach((slide, idx) => {
					slide.classList.add('hero-carousel-slide');
					if (idx === 0) {
						slide.classList.add('is-active-slide');
					} else {
						slide.classList.add('is-inactive-slide');
					}
				});

				// Create Navigation Controls Group
				const controlsWrapper = document.createElement('div');
				controlsWrapper.className = 'hero-carousel-controls';

				// Prev Button
				const prevBtn = document.createElement('button');
				prevBtn.type = 'button';
				prevBtn.className = 'hero-carousel-prev';
				prevBtn.setAttribute('aria-label', 'Previous Slide');
				prevBtn.innerHTML = '<svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24"><path d="M15.41 7.41L14 6l-6 6 6 6 1.41-1.41L10.83 12z"/></svg>';

				// Next Button
				const nextBtn = document.createElement('button');
				nextBtn.type = 'button';
				nextBtn.className = 'hero-carousel-next';
				nextBtn.setAttribute('aria-label', 'Next Slide');
				nextBtn.innerHTML = '<svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24"><path d="M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z"/></svg>';

				// Dots Container
				const dotsWrapper = document.createElement('div');
				dotsWrapper.className = 'hero-carousel-dots';

				const dots = slides.map((_, idx) => {
					const dot = document.createElement('button');
					dot.type = 'button';
					dot.className = `hero-carousel-dot ${idx === 0 ? 'is-active' : ''}`;
					dot.setAttribute('aria-label', `Go to Slide ${idx + 1}`);
					dot.addEventListener('click', () => {
						goToSlide(idx);
						startAutoPlay();
					});
					dotsWrapper.appendChild(dot);
					return dot;
				});

				controlsWrapper.appendChild(prevBtn);
				controlsWrapper.appendChild(nextBtn);
				controlsWrapper.appendChild(dotsWrapper);
				carouselQuery.appendChild(controlsWrapper);

				function goToSlide(targetIndex) {
					if (targetIndex < 0) {
						targetIndex = slides.length - 1;
					}
					if (targetIndex >= slides.length) {
						targetIndex = 0;
					}

					currentIndex = targetIndex;

					slides.forEach((slide, idx) => {
						if (idx === currentIndex) {
							slide.classList.remove('is-inactive-slide');
							slide.classList.add('is-active-slide');
						} else {
							slide.classList.remove('is-active-slide');
							slide.classList.add('is-inactive-slide');
						}
					});

					dots.forEach((dot, idx) => {
						if (idx === currentIndex) {
							dot.classList.add('is-active');
						} else {
							dot.classList.remove('is-active');
						}
					});
				}

				function startAutoPlay() {
					stopAutoPlay();
					autoPlayTimer = setInterval(() => {
						goToSlide(currentIndex + 1);
					}, 6000);
				}

				function stopAutoPlay() {
					if (autoPlayTimer) {
						clearInterval(autoPlayTimer);
					}
				}

				prevBtn.addEventListener('click', () => {
					goToSlide(currentIndex - 1);
					startAutoPlay();
				});

				nextBtn.addEventListener('click', () => {
					goToSlide(currentIndex + 1);
					startAutoPlay();
				});

				carouselQuery.addEventListener('mouseenter', stopAutoPlay);
				carouselQuery.addEventListener('mouseleave', startAutoPlay);

				startAutoPlay();
			}
		}
	});

	// 3. Header Expandable Search Controller
	const searchForm = document.querySelector('.header-search-expandable');
	if (searchForm) {
		const searchBtn = searchForm.querySelector('.wp-block-search__button');
		const searchInput = searchForm.querySelector('.wp-block-search__input');

		if (searchBtn && searchInput) {
			searchBtn.addEventListener('click', (e) => {
				if (!searchForm.classList.contains('is-open')) {
					e.preventDefault();
					searchForm.classList.add('is-open');
					searchInput.focus();
				} else if (searchInput.value.trim() === '') {
					e.preventDefault();
					searchForm.classList.remove('is-open');
				}
			});

			document.addEventListener('click', (e) => {
				if (!searchForm.contains(e.target) && searchForm.classList.contains('is-open')) {
					if (searchInput.value.trim() === '') {
						searchForm.classList.remove('is-open');
					}
				}
			});

			document.addEventListener('keydown', (e) => {
				if (e.key === 'Escape' && searchForm.classList.contains('is-open')) {
					searchForm.classList.remove('is-open');
					searchBtn.focus();
				}
			});
		}
	}

	// 4. Publications Book Cover Carousel Controller
	const bookCarousels = document.querySelectorAll('.publications-book-carousel');
	bookCarousels.forEach((carousel) => {
		const slides = Array.from(carousel.querySelectorAll('.wp-block-image, .pub-book-slide'));
		const targetSlides = slides.length > 0 ? slides : Array.from(carousel.querySelectorAll('img'));

		if (targetSlides.length > 1) {
			let currentBookIdx = 0;
			targetSlides.forEach((slide, idx) => {
				if (idx === 0) {
					slide.classList.add('is-active-slide');
				} else {
					slide.classList.remove('is-active-slide');
				}
			});

			setInterval(() => {
				targetSlides[currentBookIdx].classList.remove('is-active-slide');
				currentBookIdx = (currentBookIdx + 1) % targetSlides.length;
				targetSlides[currentBookIdx].classList.add('is-active-slide');
			}, 4000);
		}
	});
});
