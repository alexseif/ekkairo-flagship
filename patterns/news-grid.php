<?php
/**
 * Title: News Grid (7 Posts + Archive CTA)
 * Slug: ekkairo-flagship/news-grid
 * Categories: posts, ekkairo
 * Description: Readdy 2x4 news grid displaying 7 latest posts and an integrated 8th archive CTA card.
 */
?>
<!-- wp:group {"tagName":"section","className":"news-grid-section","style":{"spacing":{"margin":{"top":"0","bottom":"var:preset|spacing|80"}}},"layout":{"type":"constrained"}} -->
<section class="wp-block-group news-grid-section" style="margin-top:0;margin-bottom:var(--wp--preset--spacing--80)">
	<!-- wp:heading {"className":"news-grid-title font-heading font-bold text-xl text-primary","style":{"spacing":{"margin":{"bottom":"var:preset|spacing|50"}}}} -->
	<h2 class="wp-block-heading news-grid-title font-heading font-bold text-xl text-primary" style="margin-bottom:var(--wp--preset--spacing--50)"><a
			href="/category/news/">Ειδήσεις</a></h2>
	<!-- /wp:heading -->

	<!-- wp:query {"queryId":2,"query":{"perPage":7,"pages":0,"offset":0,"postType":"post","order":"desc","orderBy":"date","author":"","search":"","exclude":[],"sticky":"","inherit":false},"className":"is-style-news-grid news-query-grid"} -->
	<div class="wp-block-query is-style-news-grid news-query-grid">
		<!-- wp:post-template {"className":"news-post-template","layout":{"type":"grid","columnCount":4}} -->
		<!-- wp:group {"className":"news-card-item","style":{"border":{"radius":"0.5rem","width":"1px"},"spacing":{"blockGap":"0"}}} -->
		<div class="wp-block-group news-card-item"
			style="border-width:1px;border-radius:0.5rem">
			<!-- wp:post-featured-image {"isLink":true,"aspectRatio":"16/9","className":"news-card-thumb"} /-->

			<!-- wp:group {"className":"news-card-body","style":{"spacing":{"padding":{"right":"var:preset|spacing|40","left":"var:preset|spacing|40","top":"var:preset|spacing|40","bottom":"var:preset|spacing|40"},"blockGap":"var:preset|spacing|20"}},"layout":{"type":"flex","orientation":"vertical"}} -->
			<div class="wp-block-group news-card-body"
				style="padding-top:var(--wp--preset--spacing--40);padding-right:var(--wp--preset--spacing--40);padding-bottom:var(--wp--preset--spacing--40);padding-left:var(--wp--preset--spacing--40)">
				<!-- wp:post-date {"metadata":{"bindings":{"datetime":{"source":"core/post-data","args":{"field":"date"}}}},"className":"news-card-date","style":{"elements":{"link":{"color":{"text":"var:preset|color|muted"}}}},"textColor":"muted","fontSize":"xs"} /-->

				<!-- wp:post-title {"isLink":true,"className":"news-card-title","fontSize":"small"} /-->

				<!-- wp:post-excerpt {"moreText":"Διαβάστε περισσότερα","excerptLength":18,"className":"news-card-excerpt","style":{"layout":{"selfStretch":"fixed","flexSize":""}},"fontSize":"xs"} /-->
			</div>
			<!-- /wp:group -->
		</div>
		<!-- /wp:group -->
		<!-- /wp:post-template -->

		<!-- wp:group {"className":"news-archive-cta-card text-center","style":{"border":{"radius":"0.5rem","width":"1px"},"spacing":{"margin":{"top":"0","bottom":"0"},"padding":{"top":"var:preset|spacing|70","bottom":"var:preset|spacing|70","left":"var:preset|spacing|60","right":"var:preset|spacing|60"}}},"backgroundColor":"primary-light"} -->
		<div class="wp-block-group news-archive-cta-card text-center has-primary-light-background-color has-background"
			style="border-width:1px;border-radius:0.5rem;margin-top:0;margin-bottom:0;padding-top:var(--wp--preset--spacing--70);padding-right:var(--wp--preset--spacing--60);padding-bottom:var(--wp--preset--spacing--70);padding-left:var(--wp--preset--spacing--60)">
			<!-- wp:html -->
			<div class="news-cta-icon-circle">
				<svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor"><path d="M12 4l-1.41 1.41L16.17 11H4v2h12.17l-5.58 5.59L12 20l8-8z"/></svg>
			</div>
			<!-- /wp:html -->

			<!-- wp:heading {"level":3,"className":"text-base text-primary font-bold","style":{"spacing":{"margin":{"bottom":"var:preset|spacing|20"}}}} -->
			<h3 class="wp-block-heading text-base text-primary font-bold" style="margin-bottom:var(--wp--preset--spacing--20)">Όλες οι Ειδήσεις</h3>
			<!-- /wp:heading -->

			<!-- wp:paragraph {"className":"text-xs text-muted","style":{"spacing":{"margin":{"bottom":"var:preset|spacing|40"}}}} -->
			<p class="text-xs text-muted" style="margin-bottom:var(--wp--preset--spacing--40)">Διαβάστε όλα τα άρθρα και τα νέα της Κοινότητας</p>
			<!-- /wp:paragraph -->

			<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"}} -->
			<div class="wp-block-buttons">
				<!-- wp:button {"className":"is-style-outline news-cta-link-btn"} -->
				<div class="wp-block-button is-style-outline news-cta-link-btn"><a class="wp-block-button__link wp-element-button"
						href="/category/news/">Προς τις ειδήσεις &rarr;</a></div>
				<!-- /wp:button -->
			</div>
			<!-- /wp:buttons -->
		</div>
		<!-- /wp:group -->
	</div>
	<!-- /wp:query -->
</section>
<!-- /wp:group -->