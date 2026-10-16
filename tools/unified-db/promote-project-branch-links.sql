-- Accounting branches and current company ownership are independent.
CREATE TABLE IF NOT EXISTS project_accounting_branch_links (
 project_id BIGINT UNSIGNED NOT NULL,
 branch_id BIGINT UNSIGNED NOT NULL,
 source_system VARCHAR(20) NOT NULL,
 source_project_id BIGINT UNSIGNED NOT NULL,
 PRIMARY KEY(project_id,branch_id),
 UNIQUE KEY source_project(source_system,source_project_id),
 FOREIGN KEY(project_id) REFERENCES projects(id),
 FOREIGN KEY(branch_id) REFERENCES branches(id)
) ENGINE=InnoDB;
INSERT INTO project_accounting_branch_links
SELECT m.target_id,b.id,'beloan',p.id
FROM beloan_migration_stage.tb_projects p
JOIN source_record_map m ON m.source_system='beloan' AND m.source_table='tb_projects'
 AND m.source_id=(CAST(p.id AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci)
JOIN branches b ON b.id=p.company_id;
