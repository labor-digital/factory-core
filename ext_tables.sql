--
-- Shared passthrough pointer columns that let a tt_content row point at the
-- record it belongs to — ONE pair for every record type, because
-- `foreign_table_field` stores the owning table name and so disambiguates.
-- Used by Configuration/TCA/Overrides/factory_record_types.php (DL #030).
--
CREATE TABLE tt_content (
    tx_factorycore_record_parent INT DEFAULT 0 NOT NULL,
    tx_factorycore_record_parent_table VARCHAR(255) DEFAULT '' NOT NULL
);
