---
name: set-up-shop
description: Use when the owner wants to sell products, services or subscriptions, add prices, set up a shop or online store, or asks about Stripe, checkout, shipping or stock.
---

# Set up a shop

Wire-Up sells the records of a content type through Stripe. Each record is either a one-off item, bought through a cart, or a monthly or yearly subscription.

## What you can do

1. **Make a sellable content type.** `create-content-type` with the `product` preset is sellable already. For anything else, pass `sellable: true`, or switch an existing type on with `update-content-type`. Sellable types always carry three fields:
    - `current_price`: a money amount in the site currency, such as `"24.00"`.
    - `billing`: exactly `One-time`, `Monthly` or `Yearly`. Monthly and Yearly turn the record into a subscription.
    - `shippable`: `true` for physical goods that need a shipping address.
2. **Add the products** with `create-record`, filling those fields plus the title, description and any gallery images. `regular_price` above `current_price` shows as a discount. Records start as drafts; publish them with `publish-record` when the owner asks.
3. **Show them on a page** with a collection block pointed at the content type, adding `current_price` to its fields so cards show the price.

## What the owner must do

Say these plainly, because you have no tool for them:

- **Connect Stripe** under Settings → App Integrations. Until then no buy button appears, even on published products.
- **Set shipping** under Settings → Shop: the countries they ship to and their flat rates. Items that ship cannot be bought until at least one country is chosen. Stripe Tax is switched on there too.
- **Set stock** in each product's editor. Leave it empty to sell without counting.
- **For subscriptions,** turn on the customer portal in their Stripe dashboard so members can manage billing, and allow sign-ups under Settings → General, because subscribing needs a member account.

Orders, refunds and fulfilment are handled by the owner in the admin's Orders screen; you cannot see or change orders.
