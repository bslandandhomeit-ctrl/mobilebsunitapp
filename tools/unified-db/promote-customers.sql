-- Local rehearsal only. Customer accounts remain separate from people.
-- Source staging and signed buyer snapshots remain unchanged.
CREATE TABLE IF NOT EXISTS customer_accounts (
 id BIGINT UNSIGNED PRIMARY KEY,
 account_number VARCHAR(255) NULL,
 display_name TEXT NULL,
 account_type ENUM('SINGLE','JOINT','GROUP','UNKNOWN') NOT NULL,
 identity_review_state ENUM('PENDING','VERIFIED') NOT NULL DEFAULT 'PENDING',
 source_system VARCHAR(30) NOT NULL,
 source_id BIGINT UNSIGNED NOT NULL,
 UNIQUE KEY source_identity(source_system,source_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS persons (
 id BIGINT UNSIGNED PRIMARY KEY,
 name_en TEXT NULL, name_km TEXT NULL,
 birthdate DATE NULL, gender VARCHAR(20) NULL,
 identity_review_state ENUM('PENDING','VERIFIED') NOT NULL DEFAULT 'PENDING',
 source_system VARCHAR(30) NOT NULL, source_profile_id BIGINT UNSIGNED NOT NULL,
 UNIQUE KEY source_identity(source_system,source_profile_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS customer_account_members (
 account_id BIGINT UNSIGNED NOT NULL, person_id BIGINT UNSIGNED NOT NULL,
 source_applicant_type VARCHAR(20) NULL,
 PRIMARY KEY(account_id,person_id),
 FOREIGN KEY(account_id) REFERENCES customer_accounts(id),
 FOREIGN KEY(person_id) REFERENCES persons(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DELIMITER //
DROP PROCEDURE IF EXISTS rehearse_customer_import//
CREATE PROCEDURE rehearse_customer_import()
BEGIN
 DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;
 IF (SELECT COUNT(*) FROM customer_accounts)+(SELECT COUNT(*) FROM persons)>0 THEN
   SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Customer target must be empty; review existing rehearsal before rerunning';
 END IF;
 START TRANSACTION;
 INSERT INTO customer_accounts(id,account_number,display_name,account_type,source_system,source_id)
 SELECT id,cus_acc,client_name,
 CASE UPPER(TRIM(account_cbc_type)) WHEN 'S' THEN 'SINGLE' WHEN 'J' THEN 'JOINT'
 WHEN 'G' THEN 'GROUP' ELSE 'UNKNOWN' END,'beloan',id
 FROM beloan_migration_stage.tb_clients;
 INSERT INTO persons(id,name_en,name_km,birthdate,gender,source_system,source_profile_id)
 SELECT g.id,NULLIF(TRIM(CONCAT_WS(' ',g.family_name,g.first_name,g.second_name,g.third_name)),''),
 NULLIF(TRIM(CONCAT_WS(' ',g.family_name_kh,g.first_name_kh,g.second_name_kh,g.third_name_kh)),''),
 CASE WHEN g.date_of_birth>='1000-01-01' THEN g.date_of_birth ELSE NULL END,
 NULLIF(TRIM(g.gender),''),'beloan',g.id
 FROM beloan_migration_stage.tb_client_cbc_general g
 JOIN customer_accounts a ON a.id=g.client_id;
 INSERT INTO customer_account_members(account_id,person_id,source_applicant_type)
 SELECT g.client_id,g.id,g.applicant_type
 FROM beloan_migration_stage.tb_client_cbc_general g JOIN persons p ON p.id=g.id;
 IF (SELECT COUNT(*) FROM customer_accounts)<>(SELECT COUNT(*) FROM beloan_migration_stage.tb_clients)
 OR (SELECT COUNT(*) FROM persons)<>(SELECT COUNT(*) FROM beloan_migration_stage.tb_client_cbc_general g JOIN customer_accounts a ON a.id=g.client_id)
 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Customer source coverage failed'; END IF;
 COMMIT;
END//
DELIMITER ;
CALL rehearse_customer_import();
DROP PROCEDURE rehearse_customer_import;
