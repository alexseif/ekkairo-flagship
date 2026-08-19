# Shortcode & Content Element Migration Mapping Table

This document details the mapping of legacy shortcodes, sliders, and builder items in the `EKK Portal` (`backstage_ekk` database) to native, FSE-compliant Gutenberg blocks.

| Shortcode / Element Type | Source Syntax Example | Target Gutenberg Block Structure | Image / Asset Resolution Strategy |
| :--- | :--- | :--- | :--- |
| **LayerSlider** | `[layerslider id="14"]`<br>`[layerslider id="8"]` | `<!-- wp:gallery {"columns":1,"linkTo":"none","className":"layerslider-replaced ekk-carousel"} -->`<br>Contains nested `<!-- wp:image {"id":1289,"sizeSlug":"full"} -->` blocks for every slide. | Resolves slide background image URLs and attachment IDs directly from `detailed-sliders-inventory.json` (`all_database_layersliders` & `layerslider_page_usages`). |
| **Static Gallery Slideshow** | `[gallery type="slideshow" columns="2" size="large" ids="10066,10070"]` | `<!-- wp:gallery {"columns":1,"linkTo":"none","className":"static-slideshow-converted ekk-carousel"} -->`<br>Contains nested `<!-- wp:image -->` blocks. | Resolves attachment IDs and GUID URLs from database (`wp_posts` attachments) or static slider scoping index. |
| **Standard Gallery** | `[gallery ids="10054,10063"]` | `<!-- wp:gallery {"linkTo":"none"} -->`<br>Contains nested `<!-- wp:image -->` blocks. | Resolves attachment IDs to Gutenberg gallery block structure. |
| **PDF Embedder** | `[pdf-embedder url="http://ekkairo.org/.../1101.pdf"]` | `[pdf-embedder url="..."]` (Preserved intact) | Retained as native shortcode for runtime processing by the PDF Embedder plugin on the new site. |
| **BeTheme Button** | `[button title="Read" link="http://..." color="#21409A"]` | `<!-- wp:buttons --><div class="wp-block-buttons"><!-- wp:button --><div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="...">Read</a></div><!-- /wp:button --></div><!-- /wp:buttons -->` | Converts legacy button styling to native Gutenberg button element. |
| **Contact Box** | `[contact_box title="Office" address="..." telephone="..." email="..."]` | `<!-- wp:group --><div class="wp-block-group contact-box-card"><h4>Office</h4><p>Address...</p><p>Tel...</p></div><!-- /wp:group -->` | Extracts contact info fields and wraps them in clean card group markup. |
| **Display Posts** | `[display-posts category="..." posts_per_page="4"]` | `<!-- wp:query -->`<br>`<div class="wp-block-query"><!-- wp:post-template --><!-- wp:post-title /--><!-- wp:post-excerpt /--><!-- /wp:post-template --></div>`<br>`<!-- /wp:query -->` | Maps query parameters to native FSE Query Loop block. |
| **Google Maps** | `[map lat="30.0444" lng="31.2357"]` | `<!-- wp:html --><iframe src="https://maps.google.com/maps?q=30.0444,31.2357&output=embed" width="100%" height="400"></iframe><!-- /wp:html -->` | Converts latitude and longitude coordinates into Google Maps iframe embed. |
| **Media Embeds** | `[embed]https://youtube.com/watch?v=...[/embed]` | `<!-- wp:embed {"url":"..."} --><figure class="wp-block-embed"><div class="wp-block-embed__wrapper">...</div></figure><!-- /wp:embed -->` | Converts oEmbed shortcode to native Gutenberg embed block. |
| **Muffin Builder Items** | `mfn-page-items` postmeta (column, fancy_heading, contact_box, blog) | Recursively unwraps items into `core/heading`, `core/paragraph`, `core/group`, and `core/query` blocks. | Deserializes `mfn-page-items` array and transforms item fields into block markup, appending before page body. |

---

### LayerSlider Detailed Inventory Resolution Summary

| Slider ID | Slider Name | Resolved Slide Images Count | First Resolved Slide Image URL |
| :---: | :--- | :---: | :--- |
| **8** | Ακίνητης Περιουσίας | 21 | `http://ekkairo.org/wp-content/uploads/2017/11/IMG_0009.jpg` |
| **9** | Αρχείο - Νέο Φως Εκδόσεις | 5 | `http://ekkairo.org/wp-content/uploads/2017/11/pateras-sokratis.jpg` |
| **10** | Ναών και Συγχωνευθεισών Κοινοτήτων | 7 | `http://ekkairo.org/wp-content/uploads/2017/11/IMG_0017.jpg` |
| **11** | Νεολαίας Αθλητισμού και Υποτροφιών | 9 | `http://ekkairo.org/wp-content/uploads/2017/11/pitta-proskopon-paok-kai-senek-1.jpg` |
| **12** | Παιδείας | 7 | `http://ekkairo.org/wp-content/uploads/2017/11/03280010.jpg` |
| **13** | Πολιτιστικών | 7 | `http://ekkairo.org/wp-content/uploads/2017/11/1524973_609000045835387_1629088366_n.jpg` |
| **14** | Υγείας | 6 | `http://ekkairo.org/wp-content/uploads/2017/11/1505816452461.jpg` |
| **20** | Παναγία Θεοτόκος 10-9-2018 | 4 | `https://ekkairo.org/wp-content/uploads/2018/09/DSC_0031-2.jpg` |
| **23** | Αγιασμός στα Ελληνικά Σχολεία Καΐρου για το 2018-19 | 11 | `https://ekkairo.org/wp-content/uploads/2018/09/DSC_0083.jpg` |
| **26** | Πατριάρχης Αλεξανδρείας-Πολωνία - 2018 | 14 | `https://ekkairo.org/wp-content/uploads/2018/09/3.jpg` |
| **31** | katia | 5 | `https://ekkairo.org/wp-content/uploads/2018/12/γουινεασ4.jpg` |
| **32** | Homepage news | 3 | `https://backstage.ekkairo.org/wp-content/uploads/...` |
| **34** | (Untitled Slider) | 8 | `https://ekkairo.org/wp-content/uploads/2019/01/20181127_103645.jpg` |
