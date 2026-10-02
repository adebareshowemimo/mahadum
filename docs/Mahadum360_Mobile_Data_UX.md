# Mobile data purchase page

Implemented at `/billing/data`; Billing’s Buy data link and the role-filtered sidebar lead here. This completes the dedicated-page refinement of the Phase 6 Monnify data store.

## Research and decisions

- [Airalo’s Nigeria plan comparison](https://www.airalo.com/nigeria-esim/nigercell-7days-1gb) separates data amount, validity, and price. Our cards use that hierarchy; the provider’s original name remains available in the selected plan’s details. Product identity and checkout price remain unchanged.
- [Revolut’s data-plan flow](https://help.revolut.com/en-US/help/revpoints/esim/data-plan/question-managing-my-esim-data-plan/) moves from choosing coverage to data amount to checkout. Our local equivalent is network, plan, recipient, then payment.
- [Baymard’s payment UX research](https://baymard.com/blog/payment-ux) recommends an accessible order summary, visible total, and an explicit next-action label. The desktop summary stays beside the catalogue; mobile stacks it below the recipient field with a review shortcut. The action says Continue to payment and clearly identifies Monnify checkout.

## Design

An emerald introduction complements the existing navy, green, and gold theme. Network buttons provide visible selection states without requiring a dropdown. Plan cards give data size and price room to breathe. Search, validity filters, and price sorting help with larger catalogues. Provider names with underscores remain in the details disclosure instead of dominating cards.

Filters use provider validity metadata. Size labels are extracted only when a unit is present in the provider name; otherwise the name is displayed. No invented plans, popularity badges, discounts, or delivery-time promises are added. Empty networks and provider errors remain explicit.

Network or recipient changes reset confirmation. Changing network also clears the selected plan. The existing current-price check, stable retry key, recipient validation, verified payment, single vend attempt, requery recovery, and saved purchase resumption are preserved in `useDataStore`.

## Verification

- Browser checks at desktop and mobile breakpoints using the actual sandbox catalogue; selection updates the summary and the mobile review shortcut works. No horizontal overflow at the mobile breakpoint.
- Automated checks cover search, sorting, validity filtering, network switching, error states, stable retry keys, saved purchase resumption, and axe accessibility rules supported by the test environment. Visual contrast still relies on the shared theme and browser inspection.
- No payment was submitted during design verification.
