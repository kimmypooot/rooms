-- Migration: index to support the range-based year filter.
-- The app used to query YEAR(issuance_date) = :year, which can't use an
-- index. It now queries issuance_date >= :start AND issuance_date < :end,
-- which this composite index can serve directly (also covers the plain
-- reference_type lookup used by rood_fetch_orders.php).

ALTER TABLE `rood_orders`
  ADD KEY IF NOT EXISTS `idx_rood_orders_reftype_date` (`reference_type`, `issuance_date`);
