-- ============================================================
--  demo_accounts.sql
--  Run this in phpMyAdmin → SQL tab
--  Password for ALL accounts = Demo@1234
-- ============================================================

USE nagrik_seva;

INSERT INTO users (name, email, phone, password_hash, role, zone, is_active) VALUES

-- CITIZEN demo account
('Rahul Naik',
 'citizen@demo.com',
 '9876543210',
 '$2y$12$6T3tz6G1J5oVaRZJ8qNvTOHyDvzuJ4BhHVG8TP3DT9NblE.lE1i2K',
 'citizen', NULL, 1),

-- OFFICER demo account
('Officer Parab',
 'officer@demo.com',
 '9876543211',
 '$2y$12$6T3tz6G1J5oVaRZJ8qNvTOHyDvzuJ4BhHVG8TP3DT9NblE.lE1i2K',
 'officer', 'Panaji', 1),

-- REGULATOR demo account
('Inspector Dias',
 'regulator@demo.com',
 '9876543212',
 '$2y$12$6T3tz6G1J5oVaRZJ8qNvTOHyDvzuJ4BhHVG8TP3DT9NblE.lE1i2K',
 'regulator', NULL, 1)

ON DUPLICATE KEY UPDATE
  name         = VALUES(name),
  phone        = VALUES(phone),
  password_hash= VALUES(password_hash),
  is_active    = 1;

-- ── Add some dummy complaints for the citizen dashboard ──
-- (Only runs if citizen account exists)
SET @cid = (SELECT id FROM users WHERE email='citizen@demo.com' LIMIT 1);
SET @oid = (SELECT id FROM users WHERE email='officer@demo.com' LIMIT 1);

INSERT INTO complaints
  (complaint_no, citizen_id, officer_id, category, title, description, location, zone, status, priority, created_at)
VALUES
  ('GRV-0001', @cid, @oid,  'road',        'Large pothole on NH17 near Panaji bypass',    'Dangerous pothole causing accidents',         'Panaji bypass, NH17',     'Panaji', 'in_progress', 'high',   NOW() - INTERVAL 5 DAY),
  ('GRV-0002', @cid, NULL,  'water',       'No water supply for 3 days in our area',      'Entire colony without water since Monday',    'Margao, Ward 7',          'Margao', 'new',         'high',   NOW() - INTERVAL 3 DAY),
  ('GRV-0003', @cid, @oid,  'electricity', 'Street light not working near school',         'Children face danger while returning at night','MG Road, near Vasco school','Vasco','assigned',   'medium', NOW() - INTERVAL 7 DAY),
  ('GRV-0004', @cid, @oid,  'sanitation',  'Garbage not collected for a week',             'Bin overflowing, causing smell and disease',  'Ponda market area',       'Ponda', 'resolved',    'medium', NOW() - INTERVAL 14 DAY),
  ('GRV-0005', @cid, NULL,  'property',    'Broken bench in public park',                  'Bench broken, dangerous for elderly',         'Campal Garden, Panaji',   'Panaji','new',         'low',    NOW() - INTERVAL 1 DAY)
ON DUPLICATE KEY UPDATE complaint_no=VALUES(complaint_no);

-- ── Add dummy notifications for citizen ──
INSERT INTO notifications (user_id, complaint_id, type, message, is_read, created_at)
SELECT @cid, id, 'status_update',
  CONCAT('Your complaint "', title, '" status updated to: ', status),
  0, created_at + INTERVAL 1 HOUR
FROM complaints WHERE citizen_id = @cid
ON DUPLICATE KEY UPDATE message=VALUES(message);