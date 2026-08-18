-- Optional demo data for a clean hosting panel database.
INSERT INTO artists(name,slug,bio) VALUES ('DJ Example','dj-example','Demo artist for the starter site.');
INSERT INTO labels(name,slug) VALUES ('RecordStore Digital','recordstore-digital');
-- Tracks can now be completely standalone; no release row is required.
-- Add a real master/preview through the web admin rather than inserting fake paths here.
