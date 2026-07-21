# Redirect or 410

Redirect or 410 extends the Drupal Redirect module with support for HTTP **410 Gone** responses.

It is for sites that already use Redirect to manage changed, removed, or invalid URLs, and want permanent removals to be handled in the same place.

The module adds **410 Gone** as a status option in the Redirect UI. Editors and site administrators can then choose whether an old URL should redirect, stay unresolved, or be marked as permanently gone.

## Purpose

A `410 Gone` response tells clients that a URL is gone and is not expected to come back.

Use it when:

* content was deleted and has no useful replacement;
* a URL should not redirect to unrelated content;
* old campaign, landing, product, or service pages should be removed from indexes;
* a `404 Not Found` response is not clear enough;
* URL cleanup should stay part of the Redirect workflow.

Use a redirect when there is a relevant replacement URL. Use `410 Gone` when the old URL should simply be gone.

## Redirect integration

Redirect or 410 works inside the normal Redirect administration flow.

Site administrators can manage `410 Gone` responses in the same place where they manage redirects, using the Redirect module’s existing path matching behavior.

The module does not replace Redirect. It adds permanent removal as another possible outcome.

## Redirect 404 integration

When the Redirect 404 submodule is enabled, missing URLs can be reviewed from the 404 report.

Redirect or 410 adds another action to that review: mark the URL as `410 Gone` when it should stay unavailable.

This is useful during migrations, redesigns, content cleanup, and SEO reviews where many old URLs need a clear decision.

## Fast 410 responses

Redirect or 410 includes a `fast_410` option.

When enabled, the module returns a lightweight `410 Gone` response with a configurable message. This avoids rendering a full Drupal page and can be useful for bots, crawlers, high-volume gone URLs, or old URLs that are requested often.

When disabled, the module renders the site’s configured not-found page but sends it with HTTP status `410 Gone`. This keeps the normal error-page look while returning the right status code.

* **Fast 410 enabled:** lightweight configurable response.
* **Fast 410 disabled:** themed not-found page with status `410 Gone`.

## Difference from Redirect 410

There is another contributed module named `redirect_410`.

Redirect or 410 takes a different approach. Instead of using a separate status-code workflow through the HTTP Status Code module, it works directly in the Redirect module UI and workflow.

Main differences:

* `410 Gone` is added alongside the existing Redirect status options;
* gone URLs are managed in the same place as redirects;
* Redirect 404 review can lead to either a redirect or a `410 Gone` response;
* no separate status-code management workflow is needed.

Redirect or 410 is best suited for teams that already use Redirect as the main place for URL changes, 404 review, and permanent URL removals.

## Requirements

* Drupal 10 or Drupal 11
* Redirect module

Optional:

* Redirect 404 submodule, included with Redirect.
