-- User confirmed on 2026-10-07: BS Units controls current availability.
-- Historical financial status is retained; only current catalog action flags change.
CREATE TABLE IF NOT EXISTS unit_actions LIKE bsunits_migration_stage.unit_actions;
INSERT INTO unit_actions SELECT * FROM bsunits_migration_stage.unit_actions
WHERE NOT EXISTS (SELECT 1 FROM unit_actions LIMIT 1);
ALTER TABLE units ADD COLUMN IF NOT EXISTS current_action_id BIGINT UNSIGNED NULL;
ALTER TABLE migration_unit_availability_review
 ADD COLUMN IF NOT EXISTS resolved_state VARCHAR(30) NULL,
 ADD COLUMN IF NOT EXISTS resolution_policy VARCHAR(100) NULL,
 ADD COLUMN IF NOT EXISTS bs_current_action VARCHAR(255) NULL,
 ADD COLUMN IF NOT EXISTS bs_current_action_status VARCHAR(191) NULL;
START TRANSACTION;
UPDATE units u JOIN bsunits_migration_stage.units bs ON bs.id=u.id
LEFT JOIN unit_actions a ON a.id=bs.unit_action_id AND a.unit_id=bs.id
SET u.current_action_id=a.id,
 u.availability_state=CASE
 WHEN bs.deleted_at IS NOT NULL OR bs.saleable<>1 THEN 'UNAVAILABLE'
 WHEN a.action='AVAILABLE' THEN 'AVAILABLE'
 WHEN a.action IN ('HOLD','DEPOSIT') OR (a.action='CONTRACT' AND a.status_action='PENDING') THEN 'RESERVED'
 WHEN a.action IN ('CONTRACT','HANDOVERED') THEN 'SOLD'
 WHEN a.action IN ('UNAVAILABLE','NON-CONTRACT','REPAIR-MAINTENANCE') THEN 'UNAVAILABLE'
 ELSE 'UNVERIFIED' END,
 u.is_saleable=CASE WHEN bs.saleable=1 AND bs.deleted_at IS NULL THEN 1 ELSE 0 END
WHERE u.catalog_state='CURRENT';
UPDATE migration_unit_availability_review r JOIN units u ON u.id=r.unit_id
LEFT JOIN unit_actions a ON a.id=u.current_action_id
SET r.resolved_state=u.availability_state,
 r.bs_current_action=a.action,r.bs_current_action_status=a.status_action,
 r.resolution_policy='USER_APPROVED_BS_UNITS_CURRENT_ACTION_AND_ELIGIBILITY',r.decision='APPLIED'
WHERE u.catalog_state='CURRENT';
COMMIT;
