# McGreen Acres Square Sync

Shows in-person Square sales next to the Drupal orders that record them, so
Drupal can be trusted as the reporting source of record.

**Current scope:** fetch, link, map, import and ignore. Nothing is written to
Square. Importing creates new orders; existing orders are never changed.

## Setup

1. Create an application at <https://developer.squareup.com/apps> and copy its
   **production** access token.
2. Save the token, alone, in `private/keys/square_access_token.key`. `private/`
   is not in git, so every environment (including production) needs its own copy.
3. Enable the module, then go to **Commerce > Square sales** and select
   **Fetch from Square**, or run `drush square-sync:fetch`.

A personal access token cannot be limited to reading: it has full access to the
Square account. This module only reads. The application ID is not needed.

To use the sandbox, add a Key for the sandbox token and select it, along with the
sandbox environment, on the Settings tab. Sandbox accounts hold no real sales.

## What it does

- Fetches every completed payment since the **first sale date**, with the line
  items of each order, into the `mcgreen_square_sale` table (amounts in cents).
  Each fetch refreshes everything. There is no cron run yet.
- Suggests a Drupal order for each **card** sale, from orders paid with the
  `square` gateway only. Suggestions are worked out when the page loads, so edits
  to Drupal orders show up immediately.
- Lists `square` payments that no Square sale accounts for.
- Gives no suggestion for **cash** sales, which are reconciled by hand: in
  Square, cash also stands for Zelle.

Suggestions are made from most to least certain, and nothing is used twice:

1. One sale, same day, same amount.
2. Several sales from the same day that add up to the order's payment.
3. One sale, same amount, within the match window (5 days by default).
4. Several sales from one day within the window that add up.

Dates compare the sale with the order's **placed** date, in site time. A
suggestion is only a suggestion: two sales of the same amount on one day cannot
be told apart.

## Linking sales to orders

Linking records which Drupal order covers a sale. A sale has one order; an order
can cover several sales.

- On the list, tick sales that have a suggestion and select **Link selected**.
  Ticking any sale of a combined suggestion links the whole group.
- On a sale's page (**Open**), enter any order number to link it by hand. This is
  how cash sales are linked, whatever the order's payment gateway.
- An order with a linked sale is no longer suggested for other sales. If you
  unlink one sale of a group, relink it by number, or unlink the whole group to
  get the suggestion back.
- If an order's total differs from the sum of its linked sales, both the list and
  the sale page say so.

## Mapping catalog items

The **Items** tab lists every Square catalog item sold since the first sale date.
A mapping is set once per item and applies to all of its sales.

| Record as | Becomes |
| --- | --- |
| A product, by weight | One package of the smallest size whose **Max weight** holds the line's weight. Only for items sold by the pound; a weight above the largest size is reported, not rounded down |
| A product variation | That variation, with either the quantity sold in Square or **1 per line** (for example weighed produce recorded as one grab bag) |
| No product | The Square name, with no stock change |
| Create a new product | On save, a new **unpublished** `physical` product, which the item is then mapped to |

A created product takes the Square item's name and latest unit price. Variations
of one Square item created in the same save share a product; a variation Square
calls "Regular" is titled "Each", or "Per lb" if sold by the pound. Creating is
refused if a product with that name exists. Afterwards set the product's
department, weight and stock (stock starts at zero), and publish it if it should
be sold online. By-weight products are still set up by hand.

By-weight package sizes are not offered one by one here, and subscription
variations are never offered. Square items have no SKUs, so nothing is matched
automatically.

## Mapping custom amounts

On a sale's page, each custom amount can be recorded as a **product variation**
(with a quantity) or as an **adjustment** (money only, such as an overpayment).
One custom amount maps to one thing. Subscription variations are not offered. The
choice is stored for the future import; linked sales need none.

Links (`mcgreen_square_link`) and mappings (`mcgreen_square_item_map`,
`mcgreen_square_line_map`) are kept apart from the fetched sales, so fetching
again never loses them. They live in the database only: make them on the
production site, since pulling its database down replaces local ones.

## Importing

A sale can be imported once every line is mapped. Import from the list (tick,
**Import selected**, then confirm) or from the sale's page. A sale with a
suggested order is left out of list imports; from its page you must confirm it
is a different sale.

The new order:

- is `default` type, **completed**, anonymous, dated when the sale was made, with
  the next order number;
- uses what Square charged. Lines with no product become **Square item** order
  items, adjustments become order adjustments, and any amount on no line (such as
  a tip) is its own adjustment. The import is refused if the order total would
  differ from Square's;
- has one payment per Square payment, carrying Square's payment ID: cards under
  `square`, anything else under the gateway you pick (`cash`, `zelle`, `check`);
- records the weight of by-weight packages. A custom amount counted as a
  by-weight size has no weight, so it is added as an ordinary product line;
- gets sales report entries.

It is created completed instead of being "placed", so **no receipt, order email
or conversion event is sent**. Stock is deducted only for sales dated on or after
**Deduct stock for sales from** on the Settings tab. That starts as the day the
module was installed and is kept per environment, not in config.

Sales with a refund cannot be imported. An imported sale cannot be unlinked. To
redo one, delete its order: its payments and report entries are removed with it
(Commerce alone would leave them), but **stock is not put back**.

## Ignoring

**Ignore** takes a sale off the to-do list, with an optional reason from its
page. Ignored sales account for no Drupal payment. Undo from the sale's page or
with **Unlink or restore selected**.

## Not yet built

Refunds, and fetching on cron.
