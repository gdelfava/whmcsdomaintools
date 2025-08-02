-- Update user email for all domains from gdelfava@gmail.com to webmaster@kaldera.co.za

-- Update domains table
UPDATE domains 
SET user_email = 'webmaster@kaldera.co.za' 
WHERE user_email = 'gdelfava@gmail.com';

-- Update domain_nameservers table
UPDATE domain_nameservers 
SET user_email = 'webmaster@kaldera.co.za' 
WHERE user_email = 'gdelfava@gmail.com';

-- Update user_settings table (if there are any settings for the old email)
UPDATE user_settings 
SET user_email = 'webmaster@kaldera.co.za' 
WHERE user_email = 'gdelfava@gmail.com';

-- Update sync_logs table
UPDATE sync_logs 
SET user_email = 'webmaster@kaldera.co.za' 
WHERE user_email = 'gdelfava@gmail.com';

-- Verify the changes
SELECT 'domains' as table_name, COUNT(*) as count FROM domains WHERE user_email = 'webmaster@kaldera.co.za'
UNION ALL
SELECT 'domain_nameservers' as table_name, COUNT(*) as count FROM domain_nameservers WHERE user_email = 'webmaster@kaldera.co.za'
UNION ALL
SELECT 'user_settings' as table_name, COUNT(*) as count FROM user_settings WHERE user_email = 'webmaster@kaldera.co.za'
UNION ALL
SELECT 'sync_logs' as table_name, COUNT(*) as count FROM sync_logs WHERE user_email = 'webmaster@kaldera.co.za'; 