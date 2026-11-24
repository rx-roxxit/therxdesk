# Welcome and checkout

A new Owner answers the Welcome steps, sees the Plan step, pays on Stripe Checkout (on a Trial), waits on Processing
until the webhook gives the Account access, and lands on the Schedule.

## Sub-features

- Welcome steps: persona (`@welcome-persona-<persona>`, `@welcome-persona-continue`), goals (`@welcome-goal-<goal>`,
  several, `@welcome-goals-continue`), current tool (`@welcome-software-<value>`, `@welcome-software-continue`).
- Plan step (`/welcome/plan`): "Choose your plan" and the Monthly/Yearly switch (`@plan-interval-monthly`,
  `@plan-interval-yearly`, `@plan-save-two-months`), then four cards in Catalog order (`@plan-card-<slug>`):
  Mini, Standard, Plus and Max (one column on phones, two from `sm`, four from 1081px). Each card shows the price for
  the picked interval (`@plan-price-<slug>`, `@plan-price-unit-<slug>`), its storage
  (`@plan-storage-<slug>`), its monthly AI credits (`@plan-ai-credits-<slug>`), the unlimited line
  (`@plan-unlimited-<slug>`), its button (`@plan-select-<slug>`) and the terms under it (`@plan-terms-<slug>`).
  Prices come from `plan_prices` (see `selling-mode.md`), labels from the Plan row, and the Trial length from the
  Catalog (`catalog_settings.trial_days`).
- An Owner eligible for a Trial sees "Start 7-day free trial" and "Then $11.90/month from <date>. Cancel anytime.
  One free trial per card."; one who is not sees "Subscribe". Eligibility also excludes prior payment, prior
  Complimentary access and a billable subscription without a Trial, not only "had a Trial". A trial always needs a
  card and always goes through Stripe Checkout: there is no no-card trial path any more.
- The Plan step has other states after the steps above: a Complimentary Plan replaces the cards with
  `@welcome-plan-complimentary*` and Continue (`complimentary.md`), a Lapsed Owner gets the
  `@welcome-plan-lapsed-deletion` alert above the cards (`lifecycle-deletion.md`), and a never-paid Owner whose
  first charge failed gets `PaymentRetryState` instead of cards (`trial-and-payment.md`).
- A card that already started a Trial is charged at once: the webhook ends the Trial, sets `trial_skipped_at` and
  `trial_ended_at`, and the charge that follows sets `paid_without_trial_at`. `fixture.sh up`, `down` and `guests`
  delete the Account's `trial_cards` and `trial_accounts` rows, so `4242` starts a fresh Trial on the next run;
  `trial-and-payment.md` proves the repeat-card path. Known live gap (November 23, 2026): the sandbox's invoice
  payload has `subscription: null` (the field moved under `parent`), so `paid_without_trial_at` stays null even
  though `first_paid_at` is set; that is a product bug to fix, not a doc change.
- An Offer created through `/internal/v1/offers` changes the step: the banner
  (`@welcome-plan-offer`, `@welcome-plan-offer-name`, `@welcome-plan-offer-headline`) when `show_banner` is on, and,
  on the Plans and intervals it covers, the discount badge (`@plan-offer-badge-<slug>`), the struck regular price
  (`@plan-offer-regular-<slug>`), the discounted price (`@plan-price-<slug>`) and the Offer's line
  (`@plan-terms-<slug>`). An uncovered card keeps its regular price and shows no badge. The server computes every
  covered Plan × interval, rounded as Stripe rounds; the browser does no money math.
- A link Offer (`?offer=<slug>`) is claimed by the Owner; a general Offer needs no claim and the latest one to start
  wins. A link Offer beats a general one. The line comes from `rxdesk.offer.line.*` templates in all 16 locales, so
  there is no per-Offer lang key. The Offer's longer trial applies only on the Plans and intervals it covers; an
  Account that already had a Trial gets only the discount.
- `/register?plan=<slug>&interval=monthly|yearly` is kept for the guest and preselects that card and interval; a
  junk value is dropped silently. The preselected card carries `data-selected="true"`.
- Pressing a card button before the email is verified opens `@verify-email-dialog` with `@verify-email-close`
  ("Not now") and `@verify-email-resend`, which sends the existing verification email (Mailpit). The link returns to
  `/welcome/plan`.
- The full-price journeys (`welcome-plan`, `welcome-checkout`) use the fixture with no Offer. The Offer journey
  creates its own two link Offers with `offer.sh up` and drives both banner states; `OfferSeeder` only seeds
  trial-only local rows, because a discount Offer must create its Stripe coupon through `/internal/v1/offers`.
- Stripe Checkout (hosted, sandbox): the Owner's language (never `auto`), a required billing address, a required
  Terms consent checkbox, no promotion code field, and `subscription_data.trial_end` on a first checkout.
- The billing country must be allowed: the completed-checkout webhook cancels a subscription billed to an
  EU/UK/EEA/Crown-dependency country at once and the plan step then shows "Not available in the EU/UK yet". A
  blocked Owner country never opens Checkout at all.
- Processing (`/billing/processing?session_id=...`) waits until the webhook has recorded this Checkout Session's
  facts (`accounts.checkout_session_id` equals the `session_id`), then opens the Schedule. When
  `accounts.billing_country` is `TH` and a current Operator exists it first renders the Thai purchase document (seller
  fields from the Operator, dates in Buddhist Era, the 7-day cancellation right) and waits for Continue; the same
  document is emailed once per Session. With no current Operator, the document is omitted and Processing proceeds.
  It also polls `countryBlocked` and returns to `/welcome/plan` when the webhook cancelled the checkout.
- The webhooks: Cashier's handlers write the `subscriptions` row, and `customer.subscription.created` / `updated`
  writes the Account's Plan (`accounts.plan_id`). `checkout.session.completed` (subscription mode) cancels a
  blocked billing country, records `blocked_country`, `blocked_country_at` and
  `blocked_country_payment_status` on the Account (first fact wins), and always records `checkout_completed_at`,
  `checkout_session_id`, `billing_country` and `billing_name`.

## How to get to it (user POV)

In selling mode, sign in as a new Owner: Welcome opens. Pick a persona, a goal, a current tool, then press a card's
button on the Plan step. A guest can also arrive with `/register?plan=plus&interval=yearly` already on that card. On
Stripe's page tick Terms, fill the billing name and address, type the test card and press "Start trial".

## Driving it with Playwright

- Journeys: `$V/drive.sh welcome-plan` (registers a guest through `/register?plan=plus&interval=yearly`, completes
  Welcome, checks the four cards and the switch, the verify dialog and Mailpit, then pays) and
  `$V/drive.sh welcome-checkout` (the fixture Owner, Mini monthly). Both guest journeys need `new_signups` enabled
  and restored to off afterward. `welcome-plan` makes its own guest. `welcome-checkout` needs `fixture.sh up` and a
  fixture Owner who has not answered Welcome: a second run lands on the Schedule and times out waiting for the persona
  step. A current Operator is required to prove the Thai document. `payment-retry`, `past-due` and `lapsed` are the
  Trial and payment paths in `trial-and-payment.md`; they need a Stripe test clock.
- Offers: `$V/offer.sh up` creates two sandbox link Offers through `/internal/v1/offers` (the app creates each
  Stripe coupon itself) and writes `~/.cache/verify-therxdesk/state/offers.env`; `drive.sh` passes their slugs to the
  journey. Both cover Mini monthly with 20% off the first 3 months and a 14-day trial; one shows the banner and one
  does not. The fixed slugs and the stable coupon id (`rxdesk-offer-<hash of the slug>`) make a rerun reuse the same
  Offers and coupon. `$V/drive.sh welcome-offer` registers a guest through `/register?offer=<banner>`, checks the
  banner, the 20% badge, the struck $11.90, the discounted $9.52 and the uncovered Standard card, then opens
  `/welcome/plan?offer=<no-banner>` and checks the same prices with no banner, verifies the email, and pays on the
  second Offer. `offer.sh down` removes the env; Offers are never deleted, so the sandbox rows stay.
- Stripe's page: `payOnStripeCheckout()` in `drive.ts` ticks the Terms consent checkbox when the session shows one,
  then fills card `4242 4242 4242 4242` (or `VERIFY_TEST_CARD`, which `drive.sh` passes through) with an expiry two
  years ahead, CVC `123` and a name in the Elements frame.
  When the session collects a billing address, it fills the billing name, address line 1, city and postal code in the
  Checkout frame and selects Thailand in the country field, asserting `TH`, then presses
  `[data-testid="payment-form-submit-button"]`. The country is picked explicitly, never left to the IP: an EU/UK
  billing country would make the new webhook cancel the checkout. A US card form adds `#payment-postalCodeInput`,
  which the helper fills when shown.
- Mailpit: `welcome-plan` waits for the signup email through Mailpit's HTTP API (`VERIFY_MAILPIT_URL`, default
  `http://localhost:8025`), clicks Resend, waits for a different message, and opens its `/verify-email/...` link.
- End state: the path is `/schedule...` and `auth.hasActiveSubscription` is `true`; `auth.plan.name` is `Mini` for
  `welcome-checkout` and `welcome-offer`, and `Plus` for `welcome-plan`.
  Before that, Processing shows a spinner until this Session's facts land; a TH-billed
  buyer sees `@thai-purchase-document` and the journey clicks `@purchase-document-continue`. The subscription is
  `trialing` for the Catalog's trial days (14 on the Offer path), not
  `active`, unless a repeat card skipped the Trial: then it is `active` and `first_paid_at` / `first_invoice_id` are
  set. On the Offer path, `db.sh "select o.slug, ao.redeemed_at from account_offer ao join offers o on o.id =
  ao.offer_id where ao.account_id = '<accountId>'"` shows the no-banner Offer redeemed, and
  `stripe.sh get /v1/subscriptions/<sub id> -d 'expand[]=discounts'` shows
  `discounts[0].source.coupon` (the app-created `rxdesk-offer-...`). The subscription's `trial_end` is 14 days
  out, the Offer's longer trial.
- Side effects, from the host:
  `$V/db.sh "select p.slug, s.stripe_status, s.stripe_price from accounts a left join plans p on p.id = a.plan_id left join subscriptions s on s.account_id = a.id where a.id = '<accountId>'"`
  shows `mini | trialing | <the Mini monthly Price id in plan_prices>` after `welcome-checkout`, and
  `plus | trialing | <the Plus yearly Price id>` after `welcome-plan`. `$V/selling.sh logs` shows every event
  answered `<-- [200]` (a wrong signing secret answers 403).

## Gotchas

- Stripe's page loads in about 5 to 15 seconds and its card form in a second iframe: the helper waits up to 60.
- The Terms consent checkbox belongs to the session (`consent_collection.terms_of_service`), not to one billing
  journey; `payOnStripeCheckout` ticks it whenever it is on screen.
- The fixture Owner's `users.country` is `TH` (`fixture.php`) and `payOnStripeCheckout` selects `TH` again on
  Stripe's page. If Stripe's country field moves, the journey fails its `TH` check rather than being silently
  cancelled by the blocked-country webhook.
- An hCaptcha frame loads with the page; with the test card it has not challenged so far. If it starts to, the
  journey cannot pass it: report it.
- Each run leaves a sandbox Customer; `fixture.sh up` and `cleanup.sh` delete the fixture's, and
  `fixture.sh guests` deletes the guests' (which cancels their subscriptions).
- A repeat `welcome-checkout` without `fixture.sh up` uses the same `4242` card, now a repeat: the Trial ends at once
  and the Account is charged. The fixture's purge of `trial_cards` is what makes a fresh Trial repeatable, so reset the
  fixture between runs.
