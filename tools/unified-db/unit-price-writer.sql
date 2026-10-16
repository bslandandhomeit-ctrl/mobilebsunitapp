CREATE TABLE IF NOT EXISTS unit_price_changes (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 unit_id BIGINT UNSIGNED NOT NULL,
 actor_id BIGINT UNSIGNED NOT NULL,
 old_price DECIMAL(18,2) NOT NULL, new_price DECIMAL(18,2) NOT NULL,
 request_key VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
 reason VARCHAR(500) NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(unit_id) REFERENCES units(id), FOREIGN KEY(actor_id) REFERENCES actors(id)
) ENGINE=InnoDB;
CREATE USER IF NOT EXISTS 'unified_unit_price_writer'@'%' IDENTIFIED BY 'local-only-unified-price-writer';
GRANT SELECT ON bsunits_beloan_final.units TO 'unified_unit_price_writer'@'%';
GRANT UPDATE(list_price) ON bsunits_beloan_final.units TO 'unified_unit_price_writer'@'%';
GRANT SELECT ON bsunits_beloan_final.actor_source_map TO 'unified_unit_price_writer'@'%';
GRANT SELECT, INSERT ON bsunits_beloan_final.unit_price_changes TO 'unified_unit_price_writer'@'%';
