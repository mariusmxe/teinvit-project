=== Advanced Product Fields Pro for WooCommerce ===
Contributors: studiowombat,maartenbelmans
Tags: woocommerce, custom fields, product, addon, acf
Requires at least: 6.0
Requires PHP: 7.0
Tested up to: 6.9
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
Stable tag: 3.1.6
Build: 25922

Customize WooCommerce product pages with extra form fields ( = product add-ons). This is the premium version.

== Description ==

The premium version of Advanced Product Fields for WooCommerce.

== Changelog ==

= version 3.1.6 =
 * New: cards with quantity inputs now have more conditional logic options.
 * Fix: fixed tax pricing counting twice on some "Select" elements.

= version 3.1.5 =
 * Improvement: minor performance improvement during pricing calculation on the backend.
 * Fix: fixed wrong tax calculation for variable products.
 * Fix: fixed a (long-standing) issue when using multiple field groups on 1 product, the image-switching feature sometimes didn't work correctly.

= version 3.1.4 =
 * Fix: fixed a (long-standing) issue where taxes were sometimes incorretly displayed when using multiple calculation fields referencing each other.
 
= version 3.1.3 =
 * Improvement: streamline and enhance compatibility with multi-currency plugins.
 * Fix: fixed an issue with the "image swatching" functionality on pageload. 
 * Fix: fixed an issue with taxes not always adding correctly on top of formula results.
 * Fix: Resolved an issue where repeater fields could send hidden field values to the server.
 * Other: increase minimum PHP version from 7.0 to 7.1.

= version 3.1.2 =
 * Improvement: remove "label" from the repeated field title for accessibility improvements. Note this means you may have to re-style the element if you've applied custom CSS styles.
 * Fix: fixed a repeater conflict that occurred when a button-repeater was followed by a quantity-repeater.
 * Fix: fixed an issue where the default value of the "URL" field required an @-symbol in the backend.
 * Other: upgrading the minimum PHP version from 7.0 to 7.3 a few releases from now.

= version 3.1.1 =
 * Fix: fixed an issue with addon pricing not always counting in the cart. 

= version 3.1 = 
 * New: cards can now also change the main product image.
 * Improvement: minor performance improvements on the "Shop" page.
 * Improvement: loading field groups is now faster for stores with an unusually large number of fields - up to 20% faster.
 * Improvement: improved integration with Yith Request a Quote.
 * Improvement: improved and simplified UI for field group conditional logic, field conditional logic, and the variable builder.
 * Fix: fixed an issue where some combinations of rules (regarding product variations) were not possible as field group conditions.
 * Fix: fixed an issue with the "lookuptable" formula in combination with repeating fields.
 * Fix: fixed an issue where clearing the default value on the number field would set it to zero.
 * Fix: fixed an issue where setting zero as the conditional logic value wouldn't properly save.
 * Other: removed unused "is_field_group_valid" function.
 * Other: removed legacy code from version 2.0 and less.

= version 3.0.9 = 
 * Improvement: mark "private" field groups appropriately in the backend list.
 * Improvement: you can now create global variables (to use locally) without needing any global fields.
 * Fix: fixed an issue with some files not uploading when a file upload field has the "repeater" setting enabled.
 * Fix: the "text color" design style for selected image swatches now works.

= version 3.0.8 = 
 * Improvement: improved accessibility for the repeater button.
 * Improvement: improved compatibility for plus-minus buttons on some opinionated themes.
 * Fix: fixed an issue where setting up a decimal number field would not save the "default" value when it was a decimal.
 * Fix: fixed a PHP 8 deprecation warning when saving design settings.
 
= version 3.0.7 = 
 * Fix: fixed an issue with importing fields from one product to another.
 * Fix: fixed a layout issue with "image swatches": selecting "label position" incorrectly showed inside/outside reversed.
 * Fix: fixed a validation issue when Horizontal/Vertical cards were set to allow min = 1 and max = 1.
  
= version 3.0.6 =
 * New: the file upload will now show a preview thumbnail when an image is uploaded.
 * Fix: fixed an issue where "zero" wouldn't save as default value of the number field.
 * Fix: fixed an error when clearing WP Super Cache in a non-multisite environment.
 * Other: added filter for developers 'wapf/products/query' to edit the child product query.

= version 3.0.5 =
 * Improvement: design change to hide the checkmark on cards when on mobile, as many themes don't provide enough space.
 * Fix: fix an issue with the new "vertical cards" field not having conditionals.

= version 3.0.4 =
 * Improvement: Disabled using security nonces for file upload. Too many caching plugins are mis-used, so nonces are discouraged.
 * Fix: fixed the product search for field group conditions.

= version 3.0.3 =
 * Improvement: improved cache clearing when saving design settings.
 * Fix: fixed an issue where the number's field "minimum" wasn't saving when set to zero.
 * Fix: fixed a row-gap spacing issue on the frontend.
 * Fix: fixed an issue with trailing zeros when using lookup tables.

= version 3.0.2 =
 * Fix: fixed an issue where some settings in the "appearance" tab were hidden.
 * Fix: fixed an issue with the minimum choices setting for fields with quantities.
 
= version 3.0.1 =
 * Fix: fixed an issue with the gallery images of some themes, improving the "image switching" capability.
 
= version 3.0 =
 * New: styling settings to customize form designs to match your brand (no CSS coding required).
 * New: number fields can now be rendered with plus & minus buttons.
 * New: [price.{field ID}] shortcode for formula based pricing.
 * Improvement: backend field settings are now orginized in tabs to improve UX.
 * Improvement: buttons in the formula builder now insert their formula at the cursor's position.
 * Improvement: reordered the conditions for conditional logic, reducing the number of clicks required during configuration.
 * Improvement: added Finnish translations.
 * Improvement: hardened sanitization further (for security) when saving backend settings.
 * Improvement: allow some HTML in the "you must log in" upload message.
 * Improvement: improved integration with Yith Request a Quote.
 * Improvement: improved integration for Aelia and FOX currency switchers when using formula-based pricing.
 * Improvement: minor code improvements when including other PHP files.
 * Fix: fixed an issue with coupon codes in combination with the "only discount base product price" setting.
 * Fix: fixed an issue where the "Download files" and "Delete files" buttons were gone on order admin pages with uploaded files.
 * Fix: fixed an issue where some "disabled" checkboxes could still be checked depending on conditional logic.
 * Fix: fixed an issue with formula calculation when select options contained a quote character.
 * Dev: deprecated Helper::thing_to_html_attribute_string in favor of Util::to_html_attribute_string.
 * Dev: removed Helper::wp_timezone in favor of WordPress' built-in function (since WP 5.3).
 * Dev: improved PHP file loading by improving the autoloader.
 * Other: increase minimum WP version to WP 6.0.
 
= version 2.7.25 =
 * Fix: fixed an issue where disabled options were still clickable when used in combination with the "maximum" setting.

= version 2.7.24 =
 * Fix: fixed an issue where the fields would disappear from the new Cart Block after changing the quantity.

= version 2.7.23 =
 * New: WooCommerce percentage coupons now have a setting to exclude pricing added by APF from the discount.
 * Improved: minor admin UI improvements.

= version 2.7.22 =
 * New: all options of multi-choice fields now have a "disabled" setting to mark the option as "out of stock" or "sold out".
 * Improvement: admin UI improvements when adding options to choice fields.
 * Fix: fixed an issue when trying to search for a product category in the backend.

= version 2.7.21 =
 * Fix: Restore is_choice_field().

= version 2.7.20 =
 * New: ability to duplicate variables in the backend.
 * Improvement: minor code execution improvements.
 * Dev: deprecated the parameter of the Field_Groups::get_all() function.
 * Dev: removed unused functions get_valid_field_groups, get_valid_rule_groups.

= version 2.7.19 =
 * Improvement: improved tax display in the pricing summary on product pages, especially for variable products.
 * Fix: fixed an error when searching for variations in the backend.
 
= version 2.7.18 =
 * Improvement: large accessibility improvements for all swatches (text, image, color).
 * Fix: lookuptable formula issue with new field ID's.
 = version 2.7.17 =
 * New: "import mode" option when importing fields in the backend.
 * Improvement: accessibility improvement for labels.
 * Improvement: field ID generation improvements.
 * Fix: fixed a PHP deprecation warning for PHP 8.2 and up.
 = version 2.7.16 =
 * Improvement: minor accessibility improvements (aria). This work will continue.
 * Improvement: order item meta data with pricing hints can now be edited in the WP order backend.
 * Other: test and verify upcoming WooCommerce 9.2 compatibility.

= version 2.7.15 =
 * Fix: fixed an issue with multi select image swatches not rendering.
 * Fix: fixed live preview not rendering in some cases.

= version 2.7.13 =
 * Improvement: admin UI now uses the color scheme set in the user profile.
 * Improvement: small admin UI improvements.
 * Improvement: cleanup some redundant HTML.
 * Fix: fixed an issue with the new tax suffix feature.

= version 2.7.12 =
 * New: the pricing summary now includes the tax suffix too.
 * Improvement: improved performance for simple products using lots of formula-based pricing.
 * Improvement: improved the admin "Field Groups" list to show scheduled field groups too.
 * Improvement: "edit cart" functionality now works with the new WooCommerce cart block too.
 * Other: added a way to change the "Choose an option" text for select lists via a code snippet.
 * Other: bump minimum WooCommerce version to 4.9.0, WP to 5.3, and PHP to 7.0.

= version 2.7.11 =

 * Fix: fixed an issue with iOS devices not scrolling and focusing checkboxes when validation happens.
 * Fix: undo pagination.

= version 2.7.10 =
 * Improvement: big performance improvements on the admin side when working with large forms.
 * Improvement: improved the formula parser when dealing with numbers.
 * Fix: fixed a bug where conditionals in the admin would sometimes be empty.

= version 2.7.9 =
 * Improvement: improved integration with the "Yith Request a Quote" plugin.
 * Improvement: improved support for the calendar in combination with Google-translated sites.
 * Improvement: small performance improvement when uninstalling the plugin.
 * Fix: fixed an issue where the date field "dates in past/future" settings were sometimes ignored.
 * Other: bump minimum required WooCommerce version to 4.1.

= version 2.7.8 =
 * New: integration with Aelia Currency Switcher.
 * Fix: fixed an issue with dynamic date calculation.
 * Fix: fixed an issue where pressing the "Add new rule group" button wouldn't work.

= version 2.7.7 =
 * Improvement: improved compatibility with the Woodmart theme's custom gallery.
 * Improvement: improved "order again" functionality.
 * Fix: resolved licensing issue preventing some users from activating the plugin.

= version 2.7.6 =
 * Improvement: compatibility with WooCommerce's "single product" block.
 * Fix: fixed an issue with not validating multi-select swatches on the front-end whose min and max attribute were set.
 * Fix: fixed an issue with invalid carts when ordering 2 specific variations at the same time.

= version 2.7.5 =
 * Fix: fixed a PHP warning.
 * Fix: fixed an edge case where repeated items were split in the cart.
 * Fix: fixed an issue with using [qty] in lookup tables.

= version 2.7.4 =
 * Improvement: performance improvements for the "select" field.
 * Fix: fixed an issue where the import functionality would only render fields after page refresh.
 * Fix: fixed an issue where the date field would return "invalid date format" in rare cases.
  = version 2.7.3 =
 * Fix: fixed an issue with file upload fields in combination with repeatable sections.
 * Fix: fixed a bug with WooCommerce's "Order Again" functionality when adding a 2nd product to cart without any options.
 = version 2.7.2 =
 * Fix: fixed an issue with some formulas not saving correctly.

= version 2.7.1 =
 * New: ability to use "[qty]" shortcode in lookuptable formulas.
 * Fix: duplicating field groups or products now correctly updates calculation field formulas.
 * Other: test and marked as compatible with the upcoming High Performance Order Storage (HPOS) update.
 * Other: added a filter for developers to change price including VAT: wapf/pricing/price_with_tax.

= version 2.7 =
 * New: redesigned the calendar/date field to be more user-friendly.
 * New: added a "delete files" button on every order that contains uploaded files.
 * Improvement: improved cart validation at checkout to take into account the fact that checkout can happen 48 hours after adding to cart.
 * Improvement: improved performance and code-footprint of the calendar.
 * Improvement: improved mobile UX of the calendar.
 * Improvement: improved the "remove trailing zeroes" function for pricing hints.
 * Improvement: improved the product admin page load time when you have a lot of fields (50+) attached to it.
 * Fix: fixed a niche issue where uploaded files were removed when adding a 2nd product to cart through ajax.
 * Fix: fixed an add-to-cart validation error when ordering the last unit of a product.
 * Other: filter "wapf/html/field_description" now also fires when the field description is empty.

= version 2.6.1 =
 * Fix: fixed a bug with some price hints wrongly updating in rare cases.
 * Other: if you are using the snippet to update the WooCommerce price label, please check our website and update your code snippet.


= version 2.6 =
 * New: setting to define the pricing hint (you can now remove brackets and plus symbol).
 * Improvement: "required" validation now also works with the modern file upload field.
 * Improvement: pricing hint format now follows WooCommerce standards to trim zeroes.
 * Improvement: improved admin styling for RTL displays.
 * Improvement: minor performance improvements in rendering the fields on your website.
 * Fix: fixed bug in "lookuptable" formula duplication in repeater field.
 * Other: verify compatibility with WooCommerce 7.5 (beta 1).

= version 2.5 =
 * New: improved and refreshed admin UI (less overwhelming and easier to read).
 * New: you can now easily import options in bulk to multiple choice fields like checkboxes, swatches, ...
 * New: formula-builder to help you write your formulas for complex pricing schemes.
 * Improvement: you can now easily copy a field ID from the admin.
 * Improvement: with formula-based pricing, you can define variables only within local field groups, eliminating the need for additional fields.
 * Fix: fixed an issue with formula based pricing showing incorrect currency when using WOOCS and a non-default currency.
 * Fix: fixed an issue with our plugin's "image switching" feature when switching back to a variation image.
 * Fix: fixed a few minor bugs in the admin settings screens.
 * Other: bump minimum required WooCommerce version to 3.6.

For earlier changelogs, check the website.