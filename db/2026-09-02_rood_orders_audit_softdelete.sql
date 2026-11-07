-- Migration: audit trail + soft-delete for rooms (rood_orders)
-- Run manually against the production DB. Idempotent-safe to re-run on MariaDB
-- (ADD COLUMN IF NOT EXISTS) — if your MySQL/MariaDB version doesn't support
-- that syntax, drop the "IF NOT EXISTS" clauses and run once.
--
-- Adds:
--   created_by / updated_by / deleted_by -> users_cscro8.id (who did what)
--   deleted_at                            -> soft-delete marker (NULL = active)
--
-- After running this, the app code in backend/insert, backend/update,
-- backend/delete, and all three backend/fetch/*.php already expects these
-- columns to exist (see accompanying PHP changes).

ALTER TABLE `rood_orders`
  ADD COLUMN IF NOT EXISTS `created_by` INT NULL AFTER `upload_file`,
  ADD COLUMN IF NOT EXISTS `updated_by` INT NULL AFTER `created_by`,
  ADD COLUMN IF NOT EXISTS `deleted_by` INT NULL AFTER `updated_by`,
  ADD COLUMN IF NOT EXISTS `deleted_at` DATETIME NULL AFTER `deleted_by`;

ALTER TABLE `rood_orders`
  ADD KEY IF NOT EXISTS `idx_rood_orders_deleted_at` (`deleted_at`);

-- Foreign keys are intentionally NOT enforced against users_cscro8 here:
-- ON DELETE SET NULL would require users_cscro8.id to never be hard-deleted
-- for old audit rows to stay meaningful, and this codebase does not currently
-- guarantee that. Add FKs later if/when user deletion policy is settled:
--
-- ALTER TABLE `rood_orders`
--   ADD CONSTRAINT `fk_rood_orders_created_by` FOREIGN KEY (`created_by`) REFERENCES `users_cscro8` (`id`) ON DELETE SET NULL,
--   ADD CONSTRAINT `fk_rood_orders_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users_cscro8` (`id`) ON DELETE SET NULL,
--   ADD CONSTRAINT `fk_rood_orders_deleted_by` FOREIGN KEY (`deleted_by`) REFERENCES `users_cscro8` (`id`) ON DELETE SET NULL;
