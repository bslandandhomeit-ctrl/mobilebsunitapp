-- Local migration workspace only. Business tables are the first property-domain draft.
-- No app is switched to this schema until the remaining domains and adapters are implemented.
CREATE TABLE IF NOT EXISTS schema_versions (
  version VARCHAR(100) PRIMARY KEY,
  applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS migration_runs (
  id CHAR(36) PRIMARY KEY,
  status ENUM('LOADING','READY_FOR_REVIEW','FAILED') NOT NULL,
  source_databases JSON NOT NULL,
  started_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  completed_at TIMESTAMP NULL,
  summary JSON NULL
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS migration_source_records (
  run_id CHAR(36) NOT NULL,
  source_system VARCHAR(20) NOT NULL,
  source_table VARCHAR(100) NOT NULL,
  source_id VARCHAR(100) NOT NULL,
  entity_type VARCHAR(30) NOT NULL,
  candidate_key CHAR(64) NULL,
  source_payload JSON NOT NULL,
  payload_sha256 CHAR(64) NOT NULL,
  PRIMARY KEY (run_id,source_system,source_table,source_id),
  KEY candidate_lookup(run_id,entity_type,candidate_key),
  FOREIGN KEY (run_id) REFERENCES migration_runs(id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS migration_match_groups (
  run_id CHAR(36) NOT NULL,
  entity_type VARCHAR(30) NOT NULL,
  candidate_key CHAR(64) NOT NULL,
  classification ENUM('SOURCE_ONLY','UNAMBIGUOUS','AMBIGUOUS','INCOMPLETE_KEY') NOT NULL,
  member_count INT UNSIGNED NOT NULL,
  members JSON NOT NULL,
  field_conflicts JSON NOT NULL,
  decision ENUM('PENDING','MERGE','KEEP_SEPARATE') NOT NULL DEFAULT 'PENDING',
  reviewed_by VARCHAR(100) NULL,
  reviewed_at TIMESTAMP NULL,
  review_reason TEXT NULL,
  PRIMARY KEY (run_id,entity_type,candidate_key),
  FOREIGN KEY (run_id) REFERENCES migration_runs(id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS organisations (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name_km VARCHAR(255) NULL,
  name_en VARCHAR(255) NULL,
  name_zh VARCHAR(255) NULL,
  tax_number VARCHAR(100) NULL
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS branches (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  organisation_id BIGINT UNSIGNED NULL,
  code VARCHAR(255) NOT NULL,
  name VARCHAR(255) NOT NULL,
  UNIQUE KEY organisation_branch_code(organisation_id,code),
  FOREIGN KEY (organisation_id) REFERENCES organisations(id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS projects (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  organisation_id BIGINT UNSIGNED NOT NULL,
  branch_id BIGINT UNSIGNED NULL,
  code VARCHAR(255) NOT NULL,
  name_km VARCHAR(255) NULL,
  name_en VARCHAR(255) NULL,
  name_zh VARCHAR(255) NULL,
  is_published BOOLEAN NOT NULL DEFAULT FALSE,
  deleted_at DATETIME NULL,
  UNIQUE KEY organisation_project_code(organisation_id,code),
  FOREIGN KEY (organisation_id) REFERENCES organisations(id),
  FOREIGN KEY (branch_id) REFERENCES branches(id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS unit_types (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  project_id BIGINT UNSIGNED NOT NULL,
  code VARCHAR(400) NOT NULL,
  name_km VARCHAR(255) NULL,
  name_en VARCHAR(255) NULL,
  name_zh VARCHAR(255) NULL,
  attributes JSON NOT NULL,
  deleted_at DATETIME NULL,
  UNIQUE KEY project_type_code(project_id,code),
  FOREIGN KEY (project_id) REFERENCES projects(id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS units (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  unit_type_id BIGINT UNSIGNED NOT NULL,
  code VARCHAR(255) NOT NULL,
  list_price DECIMAL(18,2) NULL,
  currency CHAR(3) NULL,
  is_saleable BOOLEAN NOT NULL DEFAULT FALSE,
  specifications JSON NOT NULL,
  deleted_at DATETIME NULL,
  UNIQUE KEY type_unit_code(unit_type_id,code),
  FOREIGN KEY (unit_type_id) REFERENCES unit_types(id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS source_record_map (
  source_system VARCHAR(20) NOT NULL,
  source_table VARCHAR(100) NOT NULL,
  source_id VARCHAR(100) NOT NULL,
  entity_type VARCHAR(30) NOT NULL,
  target_id BIGINT UNSIGNED NOT NULL,
  run_id CHAR(36) NOT NULL,
  PRIMARY KEY (source_system,source_table,source_id),
  KEY target_lookup(entity_type,target_id),
  FOREIGN KEY (run_id) REFERENCES migration_runs(id)
) ENGINE=InnoDB;
INSERT IGNORE INTO schema_versions(version) VALUES ('001_property_review_workspace');
ALTER TABLE migration_runs ADD COLUMN IF NOT EXISTS domain_scope VARCHAR(30) NOT NULL DEFAULT 'PROPERTY';
