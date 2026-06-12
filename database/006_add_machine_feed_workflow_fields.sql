USE cmck_milltrack;

ALTER TABLE machine_feeds
  ADD COLUMN ended_at DATETIME NULL AFTER fed_at,
  ADD COLUMN observation TEXT NULL AFTER ended_at;
