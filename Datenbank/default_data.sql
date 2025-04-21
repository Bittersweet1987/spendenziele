-- Standardeinstellungen
INSERT IGNORE INTO einstellungen (schluessel, wert) VALUES 
    ('zeitzone', 'Europe/Berlin'),
    ('logout_redirect', 'spendenziele.php');

-- Standardzeitzonen
INSERT IGNORE INTO zeitzonen (name) VALUES 
    ('America/Chicago'),
    ('America/Denver'),
    ('America/Los_Angeles'),
    ('America/Mexico_City'),
    ('America/New_York'),
    ('America/Sao_Paulo'),
    ('Asia/Bangkok'),
    ('Asia/Dubai'),
    ('Asia/Hong_Kong'),
    ('Asia/Jakarta'),
    ('Asia/Kolkata'),
    ('Asia/Seoul'),
    ('Asia/Singapore'),
    ('Asia/Tokyo'),
    ('Australia/Melbourne'),
    ('Australia/Sydney'),
    ('Europe/Athens'),
    ('Europe/Berlin'),
    ('Europe/London'),
    ('Europe/Madrid'),
    ('Europe/Paris'),
    ('Europe/Rome'),
    ('UTC'); 