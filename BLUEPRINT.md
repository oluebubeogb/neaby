# NEABY
## Master Product, Technical & Business Blueprint

**Product:** Neaby  
**Tagline:** Buy. Sell. Resell. Closer.  
**Primary market:** Nigeria  
**Product type:** Social commerce, e-commerce, and supplier-linked resale marketplace  
**Platform:** Responsive web application and Progressive Web App (PWA)  
**Brand direction:** Premium, modern, minimal, product-first, community-driven

---

# 1. EXECUTIVE SUMMARY

Neaby is a social-commerce marketplace that enables people to discover, buy, sell, share, and resell products.

The platform combines:

- A personalized, endless product-discovery feed.
- Searchable public product listings and storefronts.
- AI-assisted product listing creation.
- High-resolution product-sharing images with QR codes.
- Seller dashboards and business analytics.
- Bulk product import and editing.
- Standard e-commerce checkout for eligible purchases.
- A controlled resale ecosystem connecting original suppliers to independent resellers.
- Paid membership for users who want to resell products.
- Paid supplier access for sellers who want to make their products available for resale.
- A PWA that works in the browser without requiring users to install an application.

Neaby should be easy for an ordinary customer to use while providing serious commercial tools for sellers, suppliers, and resellers.

## 1.1 The three commercial models

Neaby must distinguish three different activities.

### Model A: Ordinary marketplace commerce

A seller lists products and sells them to customers. Eligible transactions can use Neaby's supported checkout and payment integrations.

### Model B: Independent reselling

An authorized reseller promotes a product owned by an original supplier. The reseller sets their own selling price, manages their own customer, receives payment independently, and coordinates supply directly with the original supplier.

Neaby does not collect and distribute payments for reseller-to-customer transactions under this model.

### Model C: Paid resale access

Neaby charges for access to the specialized resale ecosystem.

There are two distinct memberships:

1. **Reseller Access:** For users who want to resell eligible products belonging to other suppliers.
2. **Supplier Resale Access:** For sellers who want to enable resale on their own eligible products and use supplier-resale management tools.

These memberships are separate from ordinary marketplace selling.

## 1.2 Non-negotiable principles

1. Browsing and ordinary marketplace participation should remain accessible.
2. Resale-specific supplier prices are protected information.
3. Only authorized, eligible resellers can access protected resale information.
4. Only suppliers with the appropriate active entitlement can enable new products for resale.
5. Every reseller offer points directly to the original product and supplier.
6. Inventory belongs to the original supplier and is never multiplied by reseller listings.
7. Reseller-to-customer payments remain outside Neaby.
8. AI suggestions never silently become verified product facts.
9. Membership expiry must not silently cancel accepted commitments.
10. All permissions are enforced server-side.
11. Paid promotions must be identifiable as sponsored.
12. The interface must be mobile-first, responsive, fast, and accessible.

---

# 2. USERS AND ACCOUNT ROLES

A user can hold several roles under one account.

## 2.1 Visitor

Can:

- Browse public products.
- Explore categories.
- Visit public storefronts.
- View public product information.
- Share public product links.
- See public prices when the supplier chooses to disclose them.

Cannot access private reseller pricing or protected supplier information.

## 2.2 Customer

Can:

- Save products.
- Contact sellers.
- Manage a shopping cart.
- Purchase eligible products through supported checkout.
- View their order history.
- Leave eligible reviews.
- Share products.

## 2.3 Ordinary seller

Can:

- Open a storefront.
- Upload products.
- Use AI-assisted listing creation.
- Manage public product prices and stock.
- Respond to customer enquiries.
- View basic analytics.
- Import and edit products according to the applicable plan.

An ordinary seller does not need Supplier Resale Access to sell products normally.

## 2.4 Authorized reseller

Must have an active Reseller Access entitlement.

Can:

- Discover products marked as eligible for resale.
- Access protected supplier resale prices.
- Calculate estimated margins.
- Create independent reseller offers.
- Set their own selling prices.
- Customize permitted marketing copy.
- Share their offers.
- Contact the original supplier.
- Request stock.
- Coordinate fulfilment.
- View reseller-specific analytics.

Membership does not guarantee that any particular product will remain eligible or in stock.

## 2.5 Supplier with resale access

Must have an active Supplier Resale Access entitlement to enable new products for resale and use the associated premium management tools.

Can:

- Enable resale on eligible products.
- Set supplier resale terms.
- Control master inventory.
- Respond to supplier requests.
- Define available fulfilment methods.
- Review reseller activity.
- Pause future resale eligibility.
- Manage accepted commitments.
- View supplier-resale analytics.

## 2.6 Administrator and moderator

Can access only the administrative functions permitted by their assigned roles.

Responsibilities include:

- User and storefront moderation.
- Product review.
- Category management.
- Subscription support.
- Abuse prevention.
- Reports and appeals.
- Platform settings.
- Operational monitoring.

Administrative access must be role-based and auditable.

---

# 3. NAVIGATION AND APPLICATION STRUCTURE

## 3.1 Primary navigation

Mobile navigation:

1. Home
2. Search
3. Sell
4. Inbox
5. Profile

The Sell destination should adapt to the user's permissions and account state.

For example:

- A new user sees an option to open a store.
- An ordinary seller sees product management.
- An authorized reseller sees their reseller workspace.
- A supplier with resale access sees supplier-resale management.

Desktop navigation may use a sidebar or top navigation with wider content areas.

## 3.2 Public pages

- Home feed.
- Search results.
- Category pages.
- Product details.
- Public storefronts.
- Public seller profiles where appropriate.
- Public collection pages where useful.
- Membership information and pricing.
- Authentication and account recovery pages.

## 3.3 Authenticated pages

- Customer dashboard.
- Seller workspace.
- Product editor.
- Bulk import.
- Storefront customization.
- Shopping cart and checkout.
- Orders.
- Inbox.
- Saved products.
- AI assistant.
- Reseller workspace.
- Supplier Connect.
- Supplier request management.
- Membership and billing.
- Account settings.

## 3.4 Administrative pages

- Admin dashboard.
- Users.
- Stores.
- Products.
- Categories.
- Reports.
- Memberships.
- Supplier requests.
- Sponsored placements.
- Platform settings.
- Audit logs.
- Background jobs.

---

# 4. PRODUCT DISCOVERY: THE FOR YOU FEED

The For You feed is a core part of Neaby.

It should provide an engaging, vertically scrollable product-discovery experience inspired by modern social applications, while retaining the clarity and trust expected from a marketplace.

## 4.1 Product cards

Each card can include:

- Product image.
- AI-generated or seller-reviewed title.
- Public selling price.
- Currency.
- Seller or storefront identity.
- Location where relevant.
- Availability indicator.
- Save action.
- Share action.
- Product-detail action.
- Quick-buy action where supported.
- Resell action only for eligible users and eligible products.

Never display private reseller prices to an unauthorized user.

## 4.2 Feed behaviour

Support:

- Infinite or cursor-based scrolling.
- Personalized recommendations.
- Category discovery.
- Recently published products.
- Popular products.
- Products from followed stores.
- Local discovery where relevant.
- New-seller discovery.
- Resale-eligible product discovery for authorized resellers.

Do not force account registration before basic browsing.

## 4.3 Ranking signals

The recommendation system may use:

- Product relevance.
- Meaningful product interactions.
- Saves and shares.
- Purchase intent and completed transactions.
- Seller reliability.
- Fulfilment history.
- Responsiveness.
- Listing quality.
- Product freshness.
- Stock confidence.
- User feedback.
- Category and seller diversity.
- Controlled exposure for new sellers.

Do not rank products primarily by raw time spent on a page.

Long-open tabs, idle sessions, artificial clicks, and repetitive automated activity should not be treated as strong positive signals.

## 4.4 Fairness and quality

- Avoid excessive repetition from one seller.
- Detect suspicious engagement.
- Separate sponsored ranking from organic ranking.
- Label paid placements.
- Provide new sellers with reasonable discovery opportunities.
- Avoid promoting unavailable products in purchase-focused contexts.
- Monitor the concentration of feed impressions and successful sales.

---

# 5. SEARCH AND CATALOGUE DISCOVERY

Users must be able to find products even when they do not encounter them in the feed.

## 5.1 Search targets

Search should cover:

- Products.
- Storefronts.
- Categories.
- Sellers.
- Brands when known.
- Product attributes.
- Locations.
- Eligible products for resale, for authorized users.

## 5.2 Search features

- Suggestions.
- Recent searches.
- Popular categories.
- Price filters.
- Category filters.
- Location filters.
- Availability filters.
- Seller filters.
- Condition filters where relevant.
- Attribute filters.
- Resale eligibility filters for authorized resellers.
- Sorting and pagination.

## 5.3 Technical implementation

Start with PostgreSQL full-text search and indexed fields.

Add typo tolerance, synonyms, and semantic search when justified by actual search demand.

Search results must be grounded in actual catalogue records.

---

# 6. PRODUCT CREATION AND AI-ASSISTED LISTINGS

Neaby should make product publishing simple enough for informal vendors and powerful enough for established businesses.

## 6.1 Seller workflow

1. Upload product images.
2. Enter a short description.
3. Enter the public selling price.
4. Enter relevant stock information.
5. Select or confirm the category.
6. Review AI-generated product details.
7. Fill in any required missing information.
8. Preview the listing.
9. Publish.

The primary listing form must not require a manually entered product-name field.

Neaby generates a proposed product title from the description and available images. Sellers can review or correct it.

## 6.2 AI-generated fields

The AI may suggest:

- Product title.
- Category.
- Search tags.
- Short description.
- Detailed description.
- SEO title.
- SEO description.
- Product attributes.
- Image ordering.
- Missing-information prompts.
- Promotional copy.

The AI must not invent brand names, specifications, certifications, warranty terms, authenticity, availability, or other unverified facts.

Unknown values must remain unknown.

## 6.3 Listing validation

Before publication, validate:

- Required images.
- Description.
- Valid price.
- Category.
- Required attributes.
- Stock settings.
- Resale settings if applicable.
- Content policy.
- Image processing status.

Give sellers clear feedback about missing or invalid information.

## 6.4 Listing status

Support:

- Draft.
- Published.
- Paused.
- Out of stock.
- Archived.
- Removed.

Server-side rules must prevent paused, archived, or removed products from being purchased.

---

# 7. PRODUCT DETAIL PAGES

Every published product should have a stable, public URL.

## 7.1 Page structure

1. Product gallery.
2. Product title.
3. Public price.
4. Seller or storefront identity.
5. Availability.
6. Description.
7. Attributes and variants.
8. Purchase or contact actions.
9. Fulfilment information.
10. Eligible reviews.
11. Related products.
12. Similar products.

## 7.2 Actions

Depending on the product and the user's permissions:

- Buy now.
- Add to cart.
- Contact seller.
- Share.
- Check availability.
- Resell this product.

The Resell action must only appear when the user has active Reseller Access and the original supplier has enabled resale for that product.

The protected supplier price must be fetched through an authorized server-side endpoint, not embedded in the public product page.

---

# 8. HIGH-RESOLUTION PRODUCT SHARING

Product sharing should be one of Neaby's strongest features.

## 8.1 Share-card content

Create a clean, premium share image containing:

- Main product image.
- Product title.
- Public selling price or reseller's own price, depending on the share context.
- Brief description.
- Storefront or reseller identity.
- Neaby branding.
- QR code.
- Short product URL.

The product photo and QR code should remain visually prominent.

Never include a private supplier acquisition price in a public share card.

## 8.2 Export sizes

Recommended formats:

- 1080 × 1080.
- 1080 × 1350.
- 1200 × 630 where useful.

Support compatible image output such as PNG, JPEG, or WebP.

## 8.3 Sharing workflow

1. User selects Share.
2. Neaby generates a high-resolution preview.
3. User chooses a format.
4. User downloads the image or uses native sharing where supported.
5. User shares the product link or image.
6. Neaby measures attributable visits where technically and legally appropriate.

QR codes must be tested for readability, and the product URL must remain available as a readable fallback.

---

# 9. STOREFRONTS AND SELLER WORKSPACE

## 9.1 Storefront features

- Store name and unique public handle.
- Logo and cover image.
- Description.
- Accent colour.
- Product catalogue.
- Featured products.
- Categories.
- Contact options.
- Location or service area.
- Fulfilment information.
- Store status.
- Eligible reviews.
- Shareable store URL.

## 9.2 Seller dashboard

Show:

- Published products.
- Drafts.
- Incomplete listings.
- Out-of-stock products.
- Orders where checkout is supported.
- Customer enquiries.
- Product views.
- Shares.
- Storefront visits.
- Confirmed sales.
- Bulk-import progress.
- AI suggestions.
- Important alerts.

Keep confirmed revenue separate from estimates and potential sales.

## 9.3 Product management

Sellers can:

- Create and edit listings.
- Duplicate products.
- Pause or archive listings.
- Update price and stock.
- Manage variants.
- Review AI suggestions.
- Enable or disable resale when authorized.
- View share and listing performance.

---

# 10. BULK IMPORT AND BULK EDITING

Support catalogue management through CSV and spreadsheet imports.

## 10.1 Import workflow

1. Upload a file.
2. Map columns.
3. Preview the data.
4. Validate records.
5. Identify missing information.
6. Optionally generate AI suggestions.
7. Confirm the import.
8. Process through a background queue.
9. Show row-level results.
10. Allow failed rows to be retried.

Support appropriate spreadsheet formats such as XLSX and XLS using compatible parsers.

## 10.2 Bulk operations

- Update prices.
- Update stock.
- Change categories.
- Change publication status.
- Change selected attributes.
- Update resale eligibility where permitted.
- Duplicate products.

Require confirmation for destructive operations and large changes.

---

# 11. AI BUSINESS ASSISTANT

## 11.1 Listing assistant

- Improve product descriptions.
- Generate titles and tags.
- Identify missing information.
- Suggest product categories.
- Produce marketing copy.
- Improve share-card descriptions.
- Suggest relevant product attributes.

## 11.2 Pricing assistant

Provide:

- Suggested selling-price ranges.
- Comparable public listing prices.
- Gross margin calculations.
- Pricing scenarios.
- Unusual-price warnings.
- Cost-aware suggestions.

Clearly distinguish public asking prices from verified completed-sale prices.

Never claim that an estimated price guarantees sales or profit.

## 11.3 Business insights

Help sellers understand:

- Products receiving attention but few enquiries.
- Incomplete listings.
- Share-to-visit conversion.
- Products generating enquiries.
- Stock requiring attention.
- Reseller interest.
- Supplier response patterns.
- Margin estimates.

## 11.4 AI safety

- Protect private data.
- Validate outputs.
- Require confirmation for consequential changes.
- Keep user-entered data when jobs fail.
- Queue long-running tasks.
- Rate-limit expensive requests.
- Record applied changes.
- Clearly label estimates and suggestions.

---

# 12. THE RESALE SYSTEM

The resale system must be architected around one principle:

**Every reseller offer points directly to the original supplier's product.**

Resellers do not become inventory owners merely because they promote, refer, or introduce other resellers.

## 12.1 Example

Supplier A owns a product.

- Reseller B creates an offer.
- Reseller C creates an offer.
- Reseller B introduces Reseller D to Neaby.
- Reseller D creates an offer.

All three offers point directly to Supplier A's original product.

Reseller B does not become Reseller D's supplier.

Referral attribution, if enabled, is stored separately.

## 12.2 Supplier controls

The original supplier controls:

- Product identity.
- Verified product facts.
- Master stock.
- Supplier resale price.
- Resale eligibility.
- Availability.
- Supplier terms.
- Permitted fulfilment options.
- Responses to stock requests.

## 12.3 Reseller controls

The reseller controls:

- Their selling price.
- Their margin.
- Permitted marketing copy.
- Their public offer.
- Their customer relationship.
- Customer payment arrangements.
- Their own fulfilment coordination.
- Their own reseller analytics.

The reseller cannot falsify supplier-confirmed facts.

## 12.4 Supplier Connect

Provide:

- Check availability.
- Request stock.
- Request a quantity.
- Contact supplier.
- Ask about delivery.
- Review supplier terms.

Requests may use Neaby messaging or an approved external channel such as a prefilled WhatsApp message.

The public product page need not expose all supplier details, but an authorized reseller must have an approved way to identify and contact the original supplier.

## 12.5 Fulfilment models

Support both:

**Model A:** The reseller pays the supplier directly, collects the product or arranges delivery, and fulfils the customer order.

**Model B:** The reseller pays the supplier according to their agreement, and the supplier dispatches directly to the reseller's customer.

The reseller manages their own customer relationship and receives the customer's payment independently.

## 12.6 Reseller margin

Let:

- `P` = reseller selling price.
- `S` = supplier acquisition price.
- `C` = additional reseller costs.

Calculate:

`Gross margin amount = P - S`

`Contribution after stated costs = P - S - C`

`Gross margin percentage = ((P - S) / P) * 100`

The percentage is only valid when `P > 0`.

Display the currency and assumptions. Do not label an incomplete estimate as net profit.

---

# 13. PAID MEMBERSHIPS AND RESALE ENTITLEMENTS

This is a central part of the improved Neaby business model.

Neaby has two separate resale-related capabilities, each with its own entitlement and lifecycle.

## 13.1 Membership A: Reseller Access

**Purpose:** Permit users to access protected reseller information and operate reseller offers.

### Without active access

Users can still:

- Browse the public marketplace.
- Purchase eligible products.
- Operate an ordinary storefront.
- Publish ordinary listings.
- Share public product pages.

They cannot:

- Access protected supplier resale prices.
- Access protected supplier information.
- Create new reseller offers.
- Use restricted reseller margin tools.
- Use paid Supplier Connect capabilities.
- Make new protected stock requests.

Public product information must remain public where appropriate. The membership protects the resale capability and confidential supplier data, not ordinary browsing.

### With active access

Users can:

- Discover eligible products.
- View protected supplier resale prices.
- Calculate potential margins.
- Create reseller offers.
- Set their own selling prices.
- Manage reseller listings.
- Contact original suppliers.
- Request stock.
- View reseller analytics.

Access is conditional on the supplier allowing resale and the relevant product being eligible.

## 13.2 Membership B: Supplier Resale Access

**Purpose:** Permit a seller to enable resale on their own eligible products and use the associated supplier-management tools.

### Without active access

The seller can still operate a normal storefront and sell products.

They cannot enable new products for resale or use restricted supplier-resale management features.

### With active access

The seller can:

- Enable resale on eligible products.
- Set supplier resale terms.
- Manage resale eligibility.
- Receive stock requests.
- Approve or decline requests.
- Specify fulfilment arrangements.
- View reseller interest.
- Review supplier-resale analytics.

A paid entitlement does not guarantee product approval, product demand, or a particular number of resellers.

## 13.3 Membership plans

Launch with recurring subscriptions first.

Illustrative pricing hypotheses:

| Plan | Illustrative price |
|---|---:|
| Reseller Monthly | ₦2,000/month |
| Reseller Annual | ₦20,000/year |
| Supplier Resale Monthly | ₦3,000/month |
| Supplier Resale Annual | ₦30,000/year |

These prices are starting hypotheses, not final market-validated prices.

Allow administrators to configure prices, currencies, billing periods, and plan availability without editing application code.

Consider one-time or lifetime access only after testing the recurring model. Any lifetime plan must clearly define the scope and duration of the entitlement.

## 13.4 Membership is not product eligibility

A user with an active Reseller Access membership still cannot resell every product.

The product must also satisfy all applicable conditions:

- The original supplier has enabled resale.
- The product is eligible for resale.
- The product is not removed or restricted.
- The supplier's terms permit the intended offer.
- The user has the required entitlement.

Similarly, a supplier's paid access does not bypass moderation or product eligibility rules.

---

# 14. SUBSCRIPTION PURCHASE AND LIFECYCLE

## 14.1 Purchase flow

1. User opens the relevant membership page.
2. Neaby explains the plan and its benefits.
3. User selects monthly or annual billing.
4. User reviews the price and renewal terms.
5. User completes payment through the configured payment provider.
6. Neaby verifies payment server-side.
7. The entitlement becomes active.
8. The user sees a confirmation and the newly available features.

Do not activate paid access solely because the browser reports a successful redirect.

## 14.2 Entitlement model

Membership access should be represented by explicit entitlements rather than scattered checks for a subscription name.

Suggested entitlement keys:

- `reseller.access`
- `supplier_resale.access`
- `supplier_resale.manage`
- `reseller.analytics`
- `supplier_resale.analytics`

Additional limits can be introduced later if necessary.

The authorization layer should check the relevant entitlement, product eligibility, account status, and resource ownership before returning protected data or performing restricted actions.

## 14.3 Subscription states

Support:

- Pending payment.
- Active.
- Past due, if recurring billing is supported.
- Grace period.
- Cancelled but active until period end.
- Expired.
- Suspended.
- Refunded or revoked where appropriate.

The exact states must match the selected payment provider's capabilities and the subscription policy.

## 14.4 Cancellation and renewal

Users should be able to view:

- Current plan.
- Price.
- Billing period.
- Next renewal date.
- Payment status.
- Cancellation status.
- Available upgrade or downgrade options.

If automatic renewal is enabled, explain it before purchase and provide a clear cancellation process.

A cancelled subscription may remain active until its paid period ends, according to the disclosed plan terms.

## 14.5 Failed payment

A failed renewal must not create an inconsistent entitlement.

Apply the documented grace-period policy, notify the user, and update access according to the subscription state.

Payment events must be idempotent so that duplicate callbacks do not create duplicate subscriptions or extend access incorrectly.

---

# 15. MEMBERSHIP EXPIRY AND EXISTING COMMITMENTS

Membership expiry must not destroy legitimate business relationships or customer commitments.

## 15.1 Reseller membership expires

The user retains:

- Their account.
- Their ordinary storefront.
- Their ordinary product listings.
- Their customer and order history where applicable.
- Access to accepted commitments and relevant records.

The user loses access to protected reseller features according to the membership policy.

Recommended treatment:

- Block new reseller offers.
- Block new protected supplier-price access.
- Block new restricted stock requests.
- Preserve existing accepted supply requests.
- Preserve records needed to complete existing commitments.
- Mark reseller offers as inactive for new activity after the defined grace period.
- Do not delete customer or supplier records merely because the subscription expires.

## 15.2 Supplier resale membership expires

The supplier retains their ordinary store and product listings.

Recommended treatment:

- Prevent new resale activation.
- Preserve accepted commitments.
- Keep existing supply requests and their histories accessible.
- Stop new restricted supplier-resale operations.
- Apply a disclosed transition policy to existing reseller offers.
- Ensure availability is not falsely represented.
- Preserve stock ownership and inventory records.

## 15.3 Supplier disables resale

Disabling resale must:

- Block new reseller offers.
- Prevent new unauthorized resale activity.
- Update eligibility status.
- Notify affected resellers where appropriate.
- Preserve accepted commitments.
- Avoid silently deleting existing records.

The system must distinguish between disabling future resale, pausing a product, exhausting stock, and cancelling an existing commitment.

---

# 16. PROTECTING PRIVATE RESELLER PRICES

This requirement is essential.

Hiding the Resell button is not sufficient. Unauthorized users must not be able to retrieve private supplier prices through browser inspection or direct API requests.

## 16.1 Public product response

A public product endpoint may return:

- Product title.
- Public description.
- Public images.
- Public price, if supplied for public display.
- Public seller information.
- Public availability status.
- Public category and attributes.

It must not return:

- Private supplier acquisition price.
- Protected reseller terms.
- Restricted supplier contact details.
- Internal supplier request information.
- Private inventory data beyond what the user is authorized to see.

## 16.2 Authorized reseller response

After checking account status, membership entitlement, product eligibility, and supplier permissions, the server may return the protected reseller information required for that operation.

## 16.3 Security requirements

- Enforce authorization server-side.
- Keep protected fields out of public API serializers.
- Avoid embedding private prices in public HTML.
- Use appropriate private cache controls.
- Do not include protected prices in public SEO metadata.
- Do not include private supplier prices in share cards.
- Validate every request to create or edit a reseller offer.
- Enforce resource ownership.
- Test direct API access by unauthorized users.
- Avoid relying on client-side role flags.
- Keep sensitive operations in audit logs where appropriate.

A public supplier's retail price may be discoverable elsewhere. The security boundary is the protected supplier resale information, not a promise that no one can ever discover a public price.

---

# 17. INVENTORY AND STOCK INTEGRITY

All reseller offers for an original product share one authoritative inventory source.

## 17.1 Example

Supplier A owns 20 units.

Three authorized resellers advertise the product.

The platform still has 20 units—not 60.

Each reseller offer references the original product and original supplier.

## 17.2 Stock model

Maintain a stock model that supports:

- Quantity on hand.
- Quantity reserved.
- Quantity available.
- Stock update timestamps.
- Variant-specific stock where necessary.
- Reservation expiry.
- External stock updates.
- Stock reconciliation.

Available stock should be calculated consistently from the authoritative stock and active reservations.

## 17.3 Stock requests versus reservations

A reseller request is not automatically a reservation.

The supplier must explicitly confirm availability and, where required, establish a valid reservation or commercial commitment.

For off-platform transactions, Neaby cannot guarantee that stock remains available unless it has a reliable and current mechanism for controlling or reconciling that inventory.

## 17.4 Overselling prevention

- Use atomic database operations for platform-controlled reservations.
- Validate stock at the time of reservation.
- Use idempotency keys for retried requests.
- Release expired reservations safely.
- Update availability after accepted stock changes.
- Preserve order snapshots.
- Record external stock adjustments.

---

# 18. ORDINARY E-COMMERCE CHECKOUT

Standard marketplace checkout remains separate from reseller transactions.

## 18.1 Checkout features

- Cart.
- Checkout.
- Customer details.
- Delivery or collection options.
- Payment integration.
- Order confirmation.
- Order history.
- Seller order management.
- Status tracking.
- Cancellation and refund workflow.
- Notifications.

## 18.2 Payment boundary

For ordinary purchases, Neaby can process eligible transactions through its configured payment integration.

For reseller-to-customer sales, the reseller receives payment independently.

Neaby must not accidentally create a platform checkout or settlement flow for a reseller sale merely because a reseller listing exists on the platform.

## 18.3 Order integrity

- Calculate totals server-side.
- Verify payment status server-side.
- Protect against duplicate orders.
- Use stock reservations where appropriate.
- Store price snapshots.
- Do not mark an unpaid order as paid based on a client request.
- Make order status transitions explicit.

---

# 19. SUPPLIER CONNECT AND MESSAGING

## 19.1 Communication types

Support:

- Customer-to-seller conversations.
- Reseller-to-original-supplier conversations.
- Reseller stock requests.
- Supplier availability responses.
- Relevant order communication.

## 19.2 Supplier request workflow

Suggested statuses:

- Draft.
- Sent.
- Awaiting response.
- Availability confirmed.
- Availability declined.
- Terms proposed.
- Accepted.
- Fulfilled.
- Cancelled.
- Expired.

Store the product, supplier, reseller, requested quantity, requested fulfilment method, message, status, timestamps, and relevant responses.

## 19.3 Notifications

Support notifications for:

- New messages.
- Stock requests.
- Supplier responses.
- Membership purchase.
- Subscription renewal.
- Failed renewal.
- Membership expiry.
- Product status changes.
- Stock updates.
- Bulk import completion.
- AI job completion.
- Relevant order changes.

Use in-app notifications first. Add email and push notifications with appropriate privacy and consent controls.

---

# 20. PWA AND MOBILE EXPERIENCE

Neaby should work as a normal website and be installable as a PWA on supported devices.

## 20.1 Requirements

- Responsive mobile, tablet, and desktop UI.
- Web app manifest.
- App icons.
- Service worker.
- Controlled caching.
- Installability where supported.
- Accessible touch targets.
- Safe-area support.
- Mobile-friendly forms.
- Keyboard accessibility.
- Reduced-motion support.

## 20.2 Offline behaviour

Cache appropriate static assets and support drafts where feasible.

Never claim that a purchase, stock update, payment, subscription activation, or supplier request has succeeded until the server confirms it.

Queued actions must show their real status and retry safely.

---

# 21. SEO AND AI SEARCH DISCOVERABILITY

## 21.1 Public pages

Support indexable pages for:

- Products.
- Storefronts.
- Categories.
- Useful public collections.

Use server-rendered or statically generated content with suitable revalidation.

## 21.2 Metadata

Where accurate, provide:

- Canonical URLs.
- Page titles.
- Meta descriptions.
- Open Graph data.
- Product images.
- Suitable structured data.
- Public price and availability where intended.

Do not expose protected reseller prices through structured data.

## 21.3 Technical SEO

- XML sitemaps.
- Robots directives.
- Canonical tags.
- Meaningful URL slugs.
- Correct status codes.
- Crawlable internal links.
- Duplicate-content controls.
- Responsive image delivery.
- Accurate removed-product handling.

Private account pages, messages, and protected supplier information must not be indexed.

---

# 22. DATABASE ARCHITECTURE

Use PostgreSQL with explicit constraints, indexes, migrations, and server-side authorization.

The following is the logical starting schema.

## 22.1 Identity and stores

### `users`

- `id`
- `email`
- `phone`
- `password_hash` or authentication-provider reference
- `status`
- `email_verified_at`
- `created_at`
- `updated_at`

### `user_profiles`

- `user_id`
- `display_name`
- `avatar_url`
- `public_location`
- `preferences`

### `stores`

- `id`
- `owner_id`
- `slug`
- `name`
- `description`
- `logo_url`
- `cover_url`
- `accent_colour`
- `status`
- `created_at`
- `updated_at`

## 22.2 Catalogue

### `categories`

- `id`
- `parent_id`
- `name`
- `slug`
- `attributes_schema`
- `status`

### `products`

- `id`
- `owner_id`
- `store_id`
- `title`
- `description`
- `category_id`
- `brand`
- `currency`
- `public_price`
- `supplier_resale_price`
- `resale_enabled`
- `status`
- `published_at`
- `created_at`
- `updated_at`

Implementation note: protect `supplier_resale_price` using server-side access controls and separate public/private data access paths. Consider isolating protected commercial terms into a dedicated table if that simplifies security.

The public listing form does not need a product-name field, but the database stores the generated or reviewed title.

### `product_images`

- `id`
- `product_id`
- `storage_key`
- `alt_text`
- `position`
- `width`
- `height`
- `processing_status`

### `product_attributes`

- `id`
- `product_id`
- `attribute_key`
- `attribute_value`
- `source`
- `verified_at`

### `product_variants`

- `id`
- `product_id`
- `sku`
- `attributes`
- `status`

## 22.3 Inventory

### `inventory`

- `id`
- `product_id`
- `variant_id`
- `quantity_on_hand`
- `quantity_reserved`
- `updated_at`

### `inventory_reservations`

- `id`
- `inventory_id`
- `quantity`
- `status`
- `reference_type`
- `reference_id`
- `expires_at`
- `created_at`

## 22.4 Memberships and billing

### `membership_plans`

- `id`
- `name`
- `membership_type`
- `billing_interval`
- `price`
- `currency`
- `duration_days`, where applicable
- `features`
- `is_active`
- `created_at`
- `updated_at`

Membership types should distinguish at least:

- `reseller`
- `supplier_resale`

### `subscriptions`

- `id`
- `user_id`
- `membership_plan_id`
- `provider`
- `provider_customer_reference`
- `provider_subscription_reference`
- `status`
- `current_period_start`
- `current_period_end`
- `cancel_at_period_end`
- `created_at`
- `updated_at`

### `entitlements`

- `id`
- `user_id`
- `entitlement_key`
- `source_type`
- `source_id`
- `starts_at`
- `expires_at`
- `status`
- `created_at`
- `updated_at`

Use entitlements to control features. Do not rely exclusively on a frontend subscription label.

### `membership_payments`

- `id`
- `user_id`
- `subscription_id`
- `provider`
- `provider_reference`
- `amount`
- `currency`
- `status`
- `created_at`

Verify all payment events server-side and prevent duplicate processing.

## 22.5 Reseller offers

### `reseller_listings`

- `id`
- `original_product_id`
- `original_supplier_id`
- `reseller_id`
- `selling_price`
- `currency`
- `custom_title`
- `custom_description`
- `status`
- `created_at`
- `updated_at`

The original supplier and product references must be validated against actual ownership.

A reseller referral must never change either reference.

## 22.6 Supplier relationships and requests

### `supplier_relationships`

- `id`
- `original_supplier_id`
- `reseller_id`
- `status`
- `created_at`

### `supplier_requests`

- `id`
- `original_product_id`
- `original_supplier_id`
- `requesting_reseller_id`
- `requested_quantity`
- `request_type`
- `fulfilment_method`
- `message`
- `status`
- `supplier_response`
- `created_at`
- `updated_at`

## 22.7 Referral attribution

### `reseller_referrals`

- `id`
- `referrer_reseller_id`
- `new_reseller_id`
- `created_at`

This records attribution only. It does not establish inventory ownership or an automatic commission.

## 22.8 Commerce

Suggested tables:

- `carts`
- `cart_items`
- `orders`
- `order_items`
- `payments`
- `refunds`
- `delivery_records`, if needed

Keep order-price snapshots so historical orders remain accurate when public product prices change.

## 22.9 Discovery and sharing

Suggested tables:

- `product_events`
- `saved_products`
- `product_shares`
- `reviews`

Use suitable retention policies and privacy protections.

## 22.10 Messaging and AI

Suggested tables:

- `conversations`
- `conversation_members`
- `messages`
- `notifications`
- `ai_jobs`
- `ai_suggestions`

## 22.11 Administration

Suggested tables:

- `reports`
- `moderation_actions`
- `audit_logs`
- `sponsored_placements`
- `user_verifications`

---

# 23. BACKEND API CONTRACTS

Use consistent, versioned API routes and predictable error responses.

The following are proposed routes, not an instruction to implement every route in the first release.

## 23.1 Public marketplace

- `GET /api/v1/feed`
- `GET /api/v1/search`
- `GET /api/v1/categories`
- `GET /api/v1/products/:slug`
- `GET /api/v1/stores/:slug`

These routes return only public fields.

## 23.2 Seller products

- `POST /api/v1/seller/products`
- `PATCH /api/v1/seller/products/:id`
- `POST /api/v1/seller/products/:id/publish`
- `POST /api/v1/seller/products/:id/pause`
- `POST /api/v1/seller/products/:id/images`
- `POST /api/v1/seller/products/bulk-import`
- `PATCH /api/v1/seller/products/bulk-edit`

## 23.3 Memberships

- `GET /api/v1/memberships/plans`
- `GET /api/v1/memberships/me`
- `POST /api/v1/memberships/checkout`
- `POST /api/v1/memberships/cancel`
- `POST /api/v1/webhooks/payments/:provider`

The payment webhook must verify the provider's signature or other documented authenticity mechanism.

## 23.4 Resale

- `GET /api/v1/resale/eligible-products`
- `GET /api/v1/resale/products/:id`
- `POST /api/v1/resale/listings`
- `PATCH /api/v1/resale/listings/:id`
- `POST /api/v1/resale/listings/:id/pause`
- `GET /api/v1/resale/listings/mine`

The server must check Reseller Access, original supplier eligibility, product status, and relevant permissions.

## 23.5 Supplier Connect

- `POST /api/v1/supplier-requests`
- `GET /api/v1/supplier-requests/mine`
- `GET /api/v1/supplier-requests/received`
- `PATCH /api/v1/supplier-requests/:id`
- `POST /api/v1/supplier-requests/:id/respond`

Only the original supplier or appropriately authorized users may respond to a supplier request.

## 23.6 Sharing

- `POST /api/v1/share-cards`
- `GET /api/v1/share-cards/:id/status`

Long-running image generation can use background jobs.

## 23.7 AI and imports

- `POST /api/v1/ai/listing-suggestions`
- `POST /api/v1/ai/pricing-analysis`
- `POST /api/v1/ai/business-insights`
- `POST /api/v1/imports`
- `GET /api/v1/jobs/:id`

Long-running tasks must report pending, running, completed, or failed status.

---

# 24. AUTHORIZATION AND SECURITY

## 24.1 Central authorization service

Implement reusable server-side checks such as:

- `canViewPublicProduct`
- `canViewResellerTerms`
- `canCreateResellerListing`
- `canManageSupplierResale`
- `canAccessSupplierContact`
- `canManageInventory`
- `canManageStore`
- `canManageSubscription`

These are conceptual names; implement them according to the selected framework.

## 24.2 Authorization conditions

A protected reseller operation should check:

1. The user is authenticated.
2. The account is permitted to operate.
3. The user has an active `reseller.access` entitlement.
4. The original product exists and is eligible.
5. The supplier permits resale.
6. The product's status permits the requested action.
7. The requested resource belongs to or is accessible by the user.
8. Any applicable supplier terms are satisfied.

Supplier management must similarly check the `supplier_resale` entitlements and actual ownership of the product.

## 24.3 Additional security requirements

- Rate limiting.
- Input validation.
- CSRF protection where applicable.
- Secure session cookies.
- Secure password storage.
- Authorization on every private API.
- Audit logs for sensitive actions.
- Payment-event verification.
- Idempotent mutations.
- Protection against mass assignment.
- Safe upload handling.
- Access-controlled object storage.
- Secrets kept outside source control.
- Database backups and restoration tests.

---

# 25. REVENUE MODEL

## 25.1 Ordinary marketplace revenue

Potential revenue sources:

- Commission on eligible Neaby-processed transactions.
- Sponsored listings.
- Optional seller tools.
- Future business plans.

## 25.2 Resale revenue

- Reseller monthly membership.
- Reseller annual membership.
- Supplier resale monthly membership.
- Supplier resale annual membership.
- Optional future one-time access plans.

## 25.3 Pricing safeguards

- Ordinary selling should not require Supplier Resale Access.
- Public product browsing should remain available without a reseller membership.
- Subscription benefits and renewal terms must be explicit.
- No automatic multilevel reseller commissions.
- No hidden reseller transaction commission.
- Membership must not imply guaranteed sales or stock.
- Admins should be able to change plan prices without a code deployment.

---

# 26. VISUAL DESIGN SYSTEM

## 26.1 Brand direction

Use the established Neaby concept:

- Lowercase `neaby` wordmark.
- Fresh green accent.
- Clean light surfaces.
- Dark, readable typography.
- Product imagery as the visual priority.
- Restrained shadows.
- Consistent rounded corners.
- Minimal clutter.
- Premium but approachable copy.

## 26.2 Reusable components

Build reusable components for:

- Product cards.
- Product grids.
- Search.
- Price display.
- Store identity.
- Availability indicators.
- Membership cards.
- Subscription status.
- Share sheets.
- QR cards.
- Forms.
- Dialogs.
- Loading skeletons.
- Toast notifications.
- Margin calculator.
- Supplier request status.
- Analytics.
- Bulk editing.
- Admin tables.

## 26.3 UX requirements

- Mobile-first layouts.
- Accessible controls.
- Clear loading and error states.
- Responsive image layouts.
- No unnecessary modal stacking.
- Confirmation for destructive actions.
- Preserve entered data after recoverable failures.
- Support keyboard navigation.
- Respect reduced-motion preferences.

---

# 27. DEPLOYMENT ARCHITECTURE

Recommended baseline:

- Next.js and TypeScript.
- PostgreSQL.
- A mature ORM or typed query layer.
- Secure session authentication.
- S3-compatible object storage.
- Background job processing.
- PostgreSQL search initially.
- An AI-provider abstraction.
- A configured payment provider for ordinary checkout and membership billing.
- HTTPS.
- Environment-based configuration.
- Database migrations.
- Centralized application logging.
- Error monitoring.
- Automated backups.

Use a modular monolith at the beginning. Separate services only when measurable operational needs justify the additional complexity.

## Deployment requirements

- Separate staging and production.
- Run migrations safely.
- Keep secrets outside the repository.
- Persist uploaded files outside ephemeral storage.
- Monitor background jobs.
- Verify payment webhooks.
- Test database restoration.
- Provide health checks.
- Document rollback procedures.

---

# 28. IMPLEMENTATION ROADMAP

## Phase 1: Marketplace foundation

Build:

- Authentication.
- Profiles.
- Storefronts.
- Product catalogue.
- Image uploads.
- AI-generated product titles.
- Product pages.
- Categories.
- Search.
- For You feed.
- Public SEO.
- Basic moderation.

**Acceptance test:** A seller can publish a product and another user can discover it through search or the feed.

## Phase 2: Social sharing

Build:

- High-resolution share cards.
- QR codes.
- Public product links.
- Store sharing.
- Saved products.
- Basic engagement analytics.

**Acceptance test:** A seller can generate a share image and QR code that lead to the correct public product.

## Phase 3: Membership infrastructure

Build:

- Membership plans.
- Payment integration.
- Subscription records.
- Entitlements.
- Membership purchase.
- Cancellation and expiry.
- Server-side permission enforcement.
- Protected reseller data.
- Membership management UI.

**Acceptance test:** An ordinary user cannot access protected reseller information, even through direct API requests.

## Phase 4: Ordinary checkout

Build:

- Cart.
- Checkout.
- Payment verification.
- Orders.
- Inventory reservations.
- Fulfilment status.
- Cancellation and refund workflows.

**Acceptance test:** An eligible product can be purchased, and stock remains consistent when payment succeeds or fails.

## Phase 5: Seller productivity

Build:

- Bulk import.
- Bulk editing.
- AI descriptions.
- Pricing assistance.
- Business insights.
- Background job queue.
- Import reports.

**Acceptance test:** Sellers can manage a larger catalogue with clear validation and recoverable failures.

## Phase 6: Resale and Supplier Connect

Build:

- Supplier Resale Access.
- Reseller Access.
- Reseller listings.
- Independent reseller prices.
- Margin calculations.
- Direct supplier links.
- Supplier requests.
- Both fulfilment models.
- Shared inventory.
- Reseller analytics.
- Membership-expiry handling.

**Acceptance test:** Multiple resellers can create offers for one supplier product without duplicating stock, and no reseller can become another reseller's inventory source.

## Phase 7: Growth

Build:

- Personalized ranking.
- Seller-quality scoring.
- Sponsored listings.
- Advanced analytics.
- Optional paid seller tools.
- Recommendation abuse prevention.

**Acceptance test:** Recommendations improve useful discovery without being dominated by artificial engagement or paid placement.

---

# 29. REQUIRED TEST SCENARIOS

## Scenario A: Unauthorized reseller

1. A visitor opens a product.
2. The product is eligible for resale.
3. The visitor has no reseller membership.
4. The public product page works normally.
5. The protected reseller price is not included in the public response.
6. A direct request to a protected endpoint is rejected.
7. The user can view the membership offer.

Expected: No protected price or supplier terms leak.

## Scenario B: Authorized reseller

1. A user activates Reseller Access.
2. The payment provider confirms the payment.
3. The entitlement becomes active.
4. The reseller opens an eligible product.
5. The server verifies access.
6. The reseller sees the supplier resale price and relevant terms.
7. The reseller creates an offer with their own price.

Expected: The offer points directly to the original supplier product.

## Scenario C: Supplier without resale access

1. A seller publishes an ordinary product.
2. The product can be sold normally.
3. The seller tries to enable resale without the required entitlement.

Expected: Ordinary selling continues, but new resale activation is blocked.

## Scenario D: Paid supplier enables resale

1. The supplier activates Supplier Resale Access.
2. The payment is verified.
3. The entitlement becomes active.
4. The supplier enables resale on an eligible product.
5. An authorized reseller accesses the product.
6. The protected supplier price becomes available to that reseller.

Expected: Resale is enabled only after the supplier's entitlement and product eligibility are validated.

## Scenario E: Shared stock

1. Supplier A has 20 units.
2. Resellers B, C, and D create offers.
3. All offers point to Supplier A's original product.
4. The inventory remains 20 units.
5. A valid reservation changes available stock.
6. Every reseller offer reflects the same authoritative stock state.

Expected: No stock multiplication or reseller-to-reseller inventory chain.

## Scenario F: Membership expiry

1. A reseller has active offers.
2. Their membership expires.
3. Restricted resale operations are disabled according to policy.
4. Existing accepted commitments remain accessible.
5. Customer and supplier records remain intact.
6. The user retains ordinary account functionality.

Expected: Expiry does not delete legitimate business records or silently cancel commitments.

## Scenario G: Referral attribution

1. Reseller B invites Reseller D.
2. D activates their own membership.
3. D lists a product owned by Supplier A.

Expected: B may be recorded as D's referral source, but D's offer still points directly to Supplier A. No automatic multilevel commission is created.

## Scenario H: Subscription payment integrity

1. A user initiates membership checkout.
2. The browser returns from the payment page.
3. The payment is not yet verified.

Expected: The protected entitlement is not activated until the server confirms the payment.

## Scenario I: Reseller payment boundary

1. A reseller sells a product to a customer.
2. The customer pays the reseller outside Neaby.
3. The reseller coordinates supply with the original supplier.

Expected: Neaby does not create a platform payment or settlement transaction for that resale.

---

# 30. SUCCESS METRICS

## Marketplace

- Search-to-product-view rate.
- Feed-to-product-view rate.
- Product enquiry rate.
- Checkout completion.
- Repeat purchases.
- Product shares.
- Reports and complaints.

## Sellers

- Time to publish.
- Listing completeness.
- Active sellers.
- Store visits.
- Enquiries.
- Confirmed sales.
- Stock accuracy.
- Bulk-import success.

## Resellers

- Active paid resellers.
- Reseller membership conversion.
- Reseller membership renewal.
- Reseller offer publication.
- Supplier response time.
- Availability confirmation.
- Estimated margin per offer.
- Fulfilment success.
- Reseller retention.

## Suppliers

- Supplier resale membership conversion.
- Supplier membership renewal.
- Products enabled for resale.
- Stock requests.
- Request fulfilment.
- Active resellers per supplier.
- Stock accuracy.
- Supplier response quality.

## Platform

- Payment verification failures.
- Subscription reconciliation failures.
- Protected-data access violations.
- AI job completion.
- Image processing failures.
- Search latency.
- Page performance.
- Moderation response time.
- Fraud reports.
- Seller concentration.

Track the value users receive, not just time spent on the platform.

---

# 31. FINAL PRODUCT DEFINITION

Neaby is a Nigerian-first social-commerce and e-commerce marketplace where users discover products, shop, sell, share, and participate in an independently operated resale network.

Its distinguishing features are:

1. Personalized product discovery.
2. AI-assisted listing creation.
3. High-resolution product-sharing images with QR codes.
4. Public storefronts and SEO-friendly product pages.
5. Bulk catalogue tools and seller analytics.
6. Paid Reseller Access.
7. Paid Supplier Resale Access.
8. Protected supplier resale prices.
9. Direct links from every reseller offer to the original supplier.
10. Independent reseller pricing and customer relationships.
11. Shared authoritative inventory.
12. Direct supplier communication and flexible fulfilment.
13. Ordinary marketplace checkout kept separate from resale payments.
14. Responsive PWA access without mandatory installation.

**The defining rule of Neaby resale is this: membership grants access to resale capabilities, supplier permission determines product eligibility, and the original supplier remains the authoritative source of stock.**

All three conditions must be enforced independently.