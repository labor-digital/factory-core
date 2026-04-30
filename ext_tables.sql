--
-- Passthrough pointer columns that let a tt_content row point at a
-- tx_factorycore_property (or any other record type adopting the same pattern).
-- Used by Configuration/TCA/Overrides/tx_factorycore_property.php.
--
CREATE TABLE tt_content (
    tx_factorycore_property_parent INT DEFAULT 0 NOT NULL,
    tx_factorycore_property_parent_table VARCHAR(255) DEFAULT '' NOT NULL
);
