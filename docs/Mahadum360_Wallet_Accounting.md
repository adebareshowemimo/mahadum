# Family wallet accounting

The family owns the cash wallet. A parent may allocate cash to a learner as an allowance; this moves the same amount between wallet balances and writes paired `wallet_currency_transactions` entries. The child cannot use that allowance to buy content or make payments. The parent may also transfer coins from the family wallet to a learner; existing `coin_transactions` records both sides. Chore and assignment rewards transfer existing parent coins on approval.

`GET /wallet` returns the parent wallet, recent funding attempts, all family and learner balances, activity, net coins by source, and reconciliation flags. The cash household total is the sum of family and learner balances. It should equal successful funding after refunds. The source of cash is `wallet_funding_transactions`; only verified gateway settlements credit the wallet. Funding refunds reclaim unspent child allowance if the family wallet alone cannot cover the reversal. No child wallet payment path exists.

Balances are cached. The coin ledger and funding plus allowance records provide the independent check. A mismatch is shown to the parent for support review and is never silently corrected. Older demo fixtures may contain cash balances that do not match their seeded funding records.
