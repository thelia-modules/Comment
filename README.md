# Comment

Lets customers post comments on products and on content pages. A comment has a title, a message
and a rating, and it belongs to a customer when the visitor is signed in.

Version 2.0 is a rewrite for Thelia 3. The front office is a Symfony UX LiveComponent rendered in
Twig, the back office runs on the `default-twig` theme, and the Smarty templates, the `comment`
loop and the front controller are gone.

## Requirements

Thelia 3.0 or greater, PHP 8.3.

## Installation

With composer, from the Thelia root:

```
composer require thelia/comment-module
```

Manually, copy the module into `<thelia_root>/local/modules/` under the name `Comment`, then
activate it in the back office.

## Configuration

The configuration page is on the module list. It has seven settings:

| Setting | Effect |
|---|---|
| Activated | Turns comments on for the whole shop |
| Moderate | New comments stay pending until an administrator accepts them |
| Allowed references | Which element types accept comments, `product,content` by default |
| Only customers | Only signed-in customers may post |
| Only verified | Only customers who bought the product may comment on it |
| Request delay | Days after an order before a customer is asked for a comment, 15 by default |
| Notify administrators | Sends an email to the shop managers when a comment is posted |

The rating scale has no field on that page. It is the `comment_max_rating` configuration
variable, 5 by default.

## Front office

The module answers the `product.bottom` theme hook, so a theme that calls that hook shows the
comment block with no further work. The block disappears on its own when comments are off for the
shop or for the product.

To place it somewhere else, render the component directly:

```twig
{{ component('Comment', {ref: 'product', refId: product.id}) }}
```

`ref` is the element type and `refId` its id. The component reads the module settings itself, so
it renders the form only to a visitor who is allowed to post, and shows the reason otherwise. It
brings no stylesheet of its own, so add the module's own one next to it:

```twig
<link rel="stylesheet" href="{{ module_asset('Comment', 'assets/comment.css') }}">
```

## Back office

A comment management page sits in the tools menu. Comments are listed with a count per status,
filtered by status or by the element they belong to, and can be accepted, refused, edited, deleted
or written by hand. The same page carries the button that sends the comment requests to customers
who ordered more than the configured delay ago.

## Emails

Two messages, both editable in the back office:

- `comment_request_customer` asks a customer for a comment after an order.
- `new_comment_notification_admin` tells the shop managers about a new comment.

Their templates are in `templates/email/default/`, and the strings they use are translated in
`I18n/email/default/`.

## Ratings

The average rating of an element is stored in the `meta_data` table under the key
`COMMENT_RATING`. It is recomputed when a comment is posted, when its status changes and when it is
deleted. Inside the comment block the component exposes it as `this.averageRating`. Elsewhere, read
it back:

```php
Thelia\Model\MetaDataQuery::getVal('COMMENT_RATING', 'product', $productId);
```
