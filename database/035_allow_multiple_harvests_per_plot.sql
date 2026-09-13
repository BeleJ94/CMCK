-- Each planned plot can have multiple harvests, with a distinct stock per harvest.
-- Add the replacement index first to preserve the foreign-key supporting index.
ALTER TABLE agricultural_harvests ADD INDEX idx_ag_harvest_cp (campaign_plot_id);
ALTER TABLE agricultural_harvests DROP INDEX uq_ag_harvest_cp;
